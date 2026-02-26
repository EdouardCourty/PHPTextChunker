<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Strategy;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class RegexChunkingStrategy implements ChunkingStrategyInterface
{
    private string $buffer = '';
    private int $position = 0;

    public function __construct(
        private readonly string $pattern,
        private readonly RegexDelimiterPosition $delimiterPosition = RegexDelimiterPosition::None,
    ) {
        if (@preg_match($this->pattern, '') === false) {
            throw new \InvalidArgumentException(\sprintf('Invalid regex pattern: %s', $this->pattern));
        }
    }

    public function reset(): void
    {
        $this->buffer = '';
        $this->position = 0;
    }

    public function process(string $data, bool $isEnd): \Generator
    {
        $this->buffer .= $data;

        $flags = $this->delimiterPosition === RegexDelimiterPosition::None
            ? \PREG_SPLIT_NO_EMPTY
            : \PREG_SPLIT_DELIM_CAPTURE;

        $parts = preg_split($this->pattern, $this->buffer, -1, $flags);

        if ($parts === false) {
            return;
        }

        if (!$isEnd) {
            // Keep the last part buffered as it may be incomplete
            $this->buffer = array_pop($parts) ?? '';
        } else {
            $this->buffer = '';
        }

        yield from $this->yieldParts($parts, $isEnd);
    }

    /**
     * @param string[] $parts
     *
     * @return \Generator<Chunk>
     */
    private function yieldParts(array $parts, bool $isEnd): \Generator
    {
        if ($this->delimiterPosition === RegexDelimiterPosition::None) {
            foreach ($parts as $part) {
                $trimmed = mb_trim($part);
                if ($trimmed === '') {
                    continue;
                }
                yield $this->createChunk($trimmed);
            }

            return;
        }

        // With PREG_SPLIT_DELIM_CAPTURE, parts alternate: [text, delimiter, text, delimiter, ...]
        $i = 0;
        while ($i < \count($parts)) {
            $text = $parts[$i];
            $delimiter = $parts[$i + 1] ?? null;

            if ($this->delimiterPosition === RegexDelimiterPosition::Suffix) {
                $combined = $delimiter !== null ? $text . $delimiter : $text;
                $trimmed = mb_trim($combined);
                if ($trimmed !== '') {
                    yield $this->createChunk($trimmed);
                }
            } elseif ($this->delimiterPosition === RegexDelimiterPosition::Prefix) {
                $trimmed = mb_trim($text);
                if ($trimmed !== '') {
                    yield $this->createChunk($trimmed);
                }
                // The delimiter will be prepended to the next text part on the next iteration
                if ($delimiter !== null && isset($parts[$i + 2])) {
                    $parts[$i + 2] = $delimiter . $parts[$i + 2];
                } elseif ($delimiter !== null && !isset($parts[$i + 2])) {
                    // delimiter is the last part; emit it alone if at end
                    if ($isEnd) {
                        $trimmed = mb_trim($delimiter);
                        if ($trimmed !== '') {
                            yield $this->createChunk($trimmed);
                        }
                    }
                }
            }

            $i += 2;
        }
    }

    private function createChunk(string $text): Chunk
    {
        $chunk = new Chunk(
            text: $text,
            position: $this->position,
            metadata: [
                'strategy' => 'regex',
                'length' => mb_strlen($text),
                'pattern' => $this->pattern,
            ],
        );

        ++$this->position;

        return $chunk;
    }
}
