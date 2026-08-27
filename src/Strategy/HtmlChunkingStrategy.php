<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Strategy;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

/**
 * Splits HTML documents into chunks.
 *
 * Two modes:
 *  - Tags mode (default): streaming, splits on opening tags of the configured list.
 *    The scanner works on byte offsets (ASCII tag delimiters can never split a
 *    multibyte sequence), which keeps parsing linear on large documents.
 *  - XPath mode: when a selector is provided, the whole document is buffered and parsed
 *    with DOMDocument; every node matching the expression becomes a chunk (outer HTML).
 *
 * HTML comments are excluded from chunk text unless keepComments is enabled (both modes).
 * With stripTags enabled, markup, <script> and <style> content are removed
 * and only the visible text is kept (both modes).
 *
 * In tags mode, a self-closing <script/> or <style/> is treated as an empty element,
 * diverging from the HTML5 tokenizer which ignores the trailing slash.
 */
final class HtmlChunkingStrategy implements ChunkingStrategyInterface
{
    private const string STATE_NORMAL = 'normal';
    private const string STATE_COMMENT = 'comment';
    private const string STATE_OPAQUE = 'opaque';

    private string $buffer = '';
    private int $position = 0;

    /**
     * Byte offset into $this->buffer from which the next STATE_COMMENT /
     * STATE_OPAQUE delimiter search should resume. The buffer's start stays
     * pinned to the comment/opaque section start for as long as the state is
     * unresolved (new data is only appended), so this offset remains valid
     * across process() calls and avoids rescanning already-checked bytes
     * from 0 every call. Reset to 0 when the state resolves, on reset(), and
     * before a new comment/opaque region begins.
     */
    private int $scanResumeOffset = 0;

    /** @var list<string> */
    private array $sectionParts = [];

    private string $state = self::STATE_NORMAL;
    private ?string $opaqueTag = null;
    private ?string $currentTag = null;

    /** @var array<string, string> */
    private array $currentAttributes = [];

    /** @var list<string> */
    private array $normalizedTags;

    /**
     * @param array<int, mixed> $tags         Tag names opening a new chunk; strings expected, validated at construction
     * @param bool              $stripTags    Whether to strip markup and keep text only
     * @param bool              $keepComments Whether to keep HTML comments in chunk text (tags mode)
     */
    public function __construct(
        private readonly array $tags = ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'],
        private readonly bool $stripTags = false,
        private readonly ?string $selector = null,
        private readonly bool $keepComments = false,
    ) {
        if ($this->tags === []) {
            throw new \InvalidArgumentException('tags must not be empty');
        }

        $this->normalizedTags = [];

        foreach ($this->tags as $tag) {
            if (!\is_string($tag) || preg_match('/^[a-zA-Z][a-zA-Z0-9]*$/', $tag) !== 1) {
                $display = \is_string($tag) ? $tag : get_debug_type($tag);

                throw new \InvalidArgumentException(\sprintf('Invalid HTML tag name: "%s"', $display));
            }

            $this->normalizedTags[] = mb_strtolower($tag);
        }

        if ($this->selector !== null) {
            if (trim($this->selector) === '') {
                throw new \InvalidArgumentException('selector must be a non-empty XPath expression');
            }

            $xpath = new \DOMXPath(new \DOMDocument());
            if (@$xpath->query(trim($this->selector)) === false) {
                throw new \InvalidArgumentException(\sprintf('Invalid XPath expression: "%s"', trim($this->selector)));
            }
        }
    }

    public function reset(): void
    {
        $this->buffer = '';
        $this->position = 0;
        $this->sectionParts = [];
        $this->state = self::STATE_NORMAL;
        $this->opaqueTag = null;
        $this->currentTag = null;
        $this->currentAttributes = [];
        $this->scanResumeOffset = 0;
    }

    public function process(string $data, bool $isEnd): \Generator
    {
        if ($this->selector !== null) {
            yield from $this->processWithDom($data, $isEnd);

            return;
        }

        $this->buffer .= $data;

        yield from $this->parseBuffer($isEnd);
    }

    /**
     * @return list<Chunk>
     */
    private function parseBuffer(bool $isEnd): array
    {
        $chunks = [];
        $pos = 0;
        $length = \strlen($this->buffer);

        while ($pos < $length) {
            switch ($this->state) {
                case self::STATE_COMMENT:
                    $searchFrom = max($pos, $this->scanResumeOffset);
                    $end = strpos($this->buffer, '-->', $searchFrom);

                    if ($end === false) {
                        if ($isEnd) {
                            if ($this->keepComments && !$this->stripTags) {
                                $this->appendSection(substr($this->buffer, $pos));
                            }
                            $pos = $length;
                            continue 2;
                        }

                        // No "-->" yet: remember how far we've scanned so the next
                        // call resumes here instead of rescanning the whole buffer
                        // from $pos. The offset is stored relative to $pos (the
                        // region start), because the buffer is trimmed to $pos at
                        // the end of this call, which re-bases index 0 to $pos on
                        // the next call. Keep a 2-byte lookback ("-->".length - 1)
                        // so a delimiter split across the chunk boundary is still found.
                        $this->scanResumeOffset = max($this->scanResumeOffset, $length - $pos - 2);

                        break 2;
                    }

                    if ($this->keepComments && !$this->stripTags) {
                        $this->appendSection(substr($this->buffer, $pos, $end + 3 - $pos));
                    }

                    $pos = $end + 3;
                    $this->state = self::STATE_NORMAL;
                    $this->scanResumeOffset = 0;

                    continue 2;
                case self::STATE_OPAQUE:
                    $close = null;
                    $searchFrom = max($pos, $this->scanResumeOffset);

                    if (preg_match(
                        '~</' . (string) $this->opaqueTag . '(?=[\s/>])~i',
                        $this->buffer,
                        $closeMatches,
                        \PREG_OFFSET_CAPTURE,
                        $searchFrom,
                    ) === 1) {
                        $close = $closeMatches[0][1];
                    }

                    if ($close === null) {
                        if ($isEnd && !$this->stripTags) {
                            $this->appendSection(substr($this->buffer, $pos));
                        }
                        if ($isEnd) {
                            $pos = $length;
                            continue 2;
                        }

                        // No closing tag yet: remember how far we've scanned. The
                        // offset is stored relative to $pos (the region start) for
                        // the same re-basing reason as STATE_COMMENT above. The
                        // lookback covers "</" + the tag name plus one extra byte,
                        // because a buffer ending exactly on the full "</script"
                        // literal still failed to match this call (the lookahead
                        // assertion had no next character to test) and must be
                        // retried from the same start once more data arrives.
                        $this->scanResumeOffset = max(
                            $this->scanResumeOffset,
                            $length - $pos - \strlen((string) $this->opaqueTag) - 2,
                        );

                        break 2;
                    }

                    $tagEnd = strpos($this->buffer, '>', $close);

                    if ($tagEnd === false) {
                        if (!$isEnd) {
                            break 2;
                        }
                        if (!$this->stripTags) {
                            $this->appendSection(substr($this->buffer, $pos));
                        }
                        $pos = $length;
                        continue 2;
                    }

                    if (!$this->stripTags) {
                        $this->appendSection(substr($this->buffer, $pos, $close - $pos));
                        $this->appendSection(substr($this->buffer, $close, $tagEnd - $close + 1));
                    }

                    $pos = $tagEnd + 1;
                    $this->state = self::STATE_NORMAL;
                    $this->opaqueTag = null;
                    $this->scanResumeOffset = 0;

                    continue 2;
            }

            $nextTagStart = strpos($this->buffer, '<', $pos);

            if ($nextTagStart === false) {
                $this->appendSection(substr($this->buffer, $pos));
                $pos = $length;

                break;
            }

            if ($nextTagStart > $pos) {
                $this->appendSection(substr($this->buffer, $pos, $nextTagStart - $pos));
            }
            $pos = $nextTagStart;

            $tagEnd = $this->findTagEnd($nextTagStart);

            if ($tagEnd === null) {
                if ($isEnd) {
                    $tail = substr($this->buffer, $nextTagStart);

                    if (!str_starts_with(ltrim(substr($tail, 1)), '!--') || ($this->keepComments && !$this->stripTags)) {
                        $this->appendSection($tail);
                    }

                    $pos = $length;
                }

                break;
            }

            $token = substr($this->buffer, $nextTagStart, $tagEnd - $nextTagStart + 1);

            if (str_starts_with(ltrim(substr($token, 1)), '!--')) {
                $commentEnd = strpos($this->buffer, '-->', $nextTagStart + 4);

                if ($commentEnd === false) {
                    if ($isEnd) {
                        if ($this->keepComments && !$this->stripTags) {
                            $this->appendSection(substr($this->buffer, $nextTagStart));
                        }
                        $pos = $length;
                        continue;
                    }

                    $this->state = self::STATE_COMMENT;

                    break;
                }

                if ($this->keepComments && !$this->stripTags) {
                    $this->appendSection(substr($this->buffer, $nextTagStart, $commentEnd + 3 - $nextTagStart));
                }

                $pos = $commentEnd + 3;

                continue;
            }

            if (preg_match('/^<([a-zA-Z][a-zA-Z0-9]*)/', $token, $matches) !== 1) {
                $isMarkupToken = preg_match('/^<\/|^<[!?]/', $token) === 1;

                if (!$this->stripTags || !$isMarkupToken) {
                    $this->appendSection($token);
                }
                $pos = $tagEnd + 1;

                continue;
            }

            $tagName = mb_strtolower($matches[1]);

            if (\in_array($tagName, $this->normalizedTags, true)) {
                $chunks = $this->flushSection($chunks);
                $this->currentTag = $tagName;
                $this->currentAttributes = $this->parseAttributes($token);

                if (!$this->stripTags) {
                    $this->appendSection($token);
                }

                $pos = $tagEnd + 1;

                continue;
            }

            $isSelfClosing = str_ends_with($token, '/>');
            $isOpaqueTag = \in_array($tagName, ['script', 'style'], true);

            if ($isOpaqueTag && !$isSelfClosing) {
                if (!$this->stripTags) {
                    $this->appendSection($token);
                }
                $this->state = self::STATE_OPAQUE;
                $this->opaqueTag = $tagName;
                $pos = $tagEnd + 1;

                continue;
            }

            if (!$this->stripTags) {
                $this->appendSection($token);
            }
            $pos = $tagEnd + 1;
        }

        if ($isEnd) {
            $chunks = $this->flushSection($chunks);
            $this->buffer = '';

            return $chunks;
        }

        $this->buffer = $pos < $length ? substr($this->buffer, $pos) : '';

        return $chunks;
    }

    /**
     * Finds the position of the '>' closing a tag, ignoring '>' inside quoted attribute values.
     */
    private function findTagEnd(int $offset): ?int
    {
        $searchFrom = $offset + 1;

        while (true) {
            $tagEnd = strpos($this->buffer, '>', $searchFrom);

            if ($tagEnd === false) {
                return null;
            }

            $window = substr($this->buffer, $searchFrom, $tagEnd - $searchFrom);

            $doubleQuote = strpos($window, '"');
            $singleQuote = strpos($window, "'");

            if ($doubleQuote === false && $singleQuote === false) {
                return $tagEnd;
            }

            if ($doubleQuote === false) {
                $quote = "'";
                $quoteOffset = $singleQuote;
            } elseif ($singleQuote === false) {
                $quote = '"';
                $quoteOffset = $doubleQuote;
            } elseif ($doubleQuote <= $singleQuote) {
                $quote = '"';
                $quoteOffset = $doubleQuote;
            } else {
                $quote = "'";
                $quoteOffset = $singleQuote;
            }

            $closingQuote = strpos($this->buffer, $quote, $searchFrom + $quoteOffset + 1);

            if ($closingQuote === false) {
                return null;
            }

            $searchFrom = $closingQuote + 1;
        }
    }

    private function appendSection(string $part): void
    {
        if ($part !== '') {
            $this->sectionParts[] = $part;
        }
    }

    /**
     * @param list<Chunk> $chunks
     *
     * @return list<Chunk>
     */
    private function flushSection(array $chunks): array
    {
        $text = mb_trim(implode('', $this->sectionParts));

        $this->sectionParts = [];

        if ($text !== '') {
            $chunks[] = new Chunk(
                text: $text,
                position: $this->position,
                metadata: [
                    'strategy' => 'html',
                    'length' => mb_strlen($text),
                    'tag' => $this->currentTag,
                    'attributes' => $this->currentAttributes,
                ],
            );
            ++$this->position;
        }

        $this->currentTag = null;
        $this->currentAttributes = [];

        return $chunks;
    }

    /**
     * @return array<string, string>
     */
    private function parseAttributes(string $token): array
    {
        if (preg_match_all(
            '/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'>]+))/',
            $token,
            $matches,
            \PREG_SET_ORDER,
        ) === false) {
            return [];
        }

        $attributes = [];

        foreach ($matches as $match) {
            /** @var array{0: string, 1: string, 2?: string, 3?: string, 4?: string} $match */
            $name = mb_strtolower($match[1]);

            if (\array_key_exists($name, $attributes)) {
                continue;
            }

            $value = $match[2] ?? $match[3] ?? $match[4] ?? '';
            $attributes[$name] = html_entity_decode($value, \ENT_QUOTES | \ENT_HTML5, 'UTF-8');
        }

        return $attributes;
    }

    /**
     * @return list<Chunk>
     */
    private function processWithDom(string $data, bool $isEnd): array
    {
        $this->buffer .= $data;

        if (!$isEnd) {
            return [];
        }

        $html = $this->buffer;
        $this->buffer = '';

        $document = new \DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $encodingPseudoInstruction = $document->firstChild;

        if ($encodingPseudoInstruction instanceof \DOMProcessingInstruction && $encodingPseudoInstruction->nodeName === 'xml') {
            $document->removeChild($encodingPseudoInstruction);
        }

        $nodes = (new \DOMXPath($document))->query(trim((string) $this->selector));

        if ($nodes === false) {
            return [];
        }

        $chunks = [];

        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement) {
                $attributes = [];

                foreach ($node->attributes as $attribute) {
                    $attributes[$attribute->nodeName] = $attribute->nodeValue ?? '';
                }

                $needsMutation = !$this->keepComments || $this->stripTags;
                $outputNode = $needsMutation ? $node->cloneNode(true) : $node;

                if (!$this->keepComments) {
                    $this->removeCommentNodes($outputNode);
                }

                if ($this->stripTags) {
                    if (\in_array(mb_strtolower($node->nodeName), ['script', 'style'], true)) {
                        // The matched node is itself a <script>/<style> element: its own
                        // content counts as script/style content, not visible text.
                        $text = '';
                    } else {
                        $this->removeScriptAndStyleElements($outputNode);
                        $text = mb_trim((string) $outputNode->textContent);
                    }
                } else {
                    $text = mb_trim((string) $document->saveHTML($outputNode));
                }
            } else {
                if ($node instanceof \DOMComment && !$this->keepComments) {
                    continue;
                }

                $text = mb_trim((string) $node->nodeValue);
                $attributes = [];
            }

            if ($text === '') {
                continue;
            }

            $chunks[] = new Chunk(
                text: $text,
                position: $this->position,
                metadata: [
                    'strategy' => 'html',
                    'length' => mb_strlen($text),
                    'tag' => $node instanceof \DOMElement ? $node->nodeName : null,
                    'attributes' => $attributes,
                ],
            );
            ++$this->position;
        }

        return $chunks;
    }

    private function removeCommentNodes(\DOMNode $node): void
    {
        if (!$node->hasChildNodes()) {
            return;
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMComment) {
                $node->removeChild($child);
            } elseif ($child instanceof \DOMElement) {
                $this->removeCommentNodes($child);
            }
        }
    }

    private function removeScriptAndStyleElements(\DOMNode $node): void
    {
        if (!$node instanceof \DOMElement) {
            return;
        }

        foreach (['script', 'style'] as $tagName) {
            $elements = $node->getElementsByTagName($tagName);

            while (($element = $elements->item(0)) instanceof \DOMElement) {
                $parent = $element->parentNode;

                if ($parent === null) {
                    break;
                }

                $parent->removeChild($element);
            }
        }
    }
}
