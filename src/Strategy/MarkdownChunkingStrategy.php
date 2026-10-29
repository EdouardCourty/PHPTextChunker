<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Strategy;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class MarkdownChunkingStrategy implements ChunkingStrategyInterface
{
    private string $buffer = '';
    private int $position = 0;
    private int $scanOffset = 0;

    public function __construct(
        private readonly int $minHeadingLevel = 1,
        private readonly int $maxHeadingLevel = 6,
    ) {
        if ($this->minHeadingLevel < 1 || $this->minHeadingLevel > 6) {
            throw new \InvalidArgumentException('minHeadingLevel must be between 1 and 6');
        }
        if ($this->maxHeadingLevel < 1 || $this->maxHeadingLevel > 6) {
            throw new \InvalidArgumentException('maxHeadingLevel must be between 1 and 6');
        }
        if ($this->minHeadingLevel > $this->maxHeadingLevel) {
            throw new \InvalidArgumentException('minHeadingLevel must be <= maxHeadingLevel');
        }
    }

    public function reset(): void
    {
        $this->buffer = '';
        $this->position = 0;
        $this->scanOffset = 0;
    }

    public function process(string $data, bool $isEnd): \Generator
    {
        $this->buffer .= $data;

        $pattern = \sprintf('/^(#{%d,%d})\s+(.+)$/m', $this->minHeadingLevel, $this->maxHeadingLevel);

        if (!$isEnd) {
            // Only complete lines can be inspected for headings
            $lastNewline = strrpos($this->buffer, "\n");
            if ($lastNewline === false) {
                return;
            }

            // @phpstan-ignore-next-line regexp.pattern (pattern is valid: constructor guarantees min <= max)
            $found = preg_match_all($pattern, substr($this->buffer, 0, $lastNewline + 1), $matches, \PREG_OFFSET_CAPTURE, $this->scanOffset);
            $this->scanOffset = $lastNewline + 1;

            if ($found === false || $found === 0) {
                return;
            }

            // The last section may continue in the next buffer: hold it back until the next heading or the end
            $lastHeadingStart = $matches[0][$found - 1][1];
            if ($lastHeadingStart === 0) {
                return;
            }

            $toProcess = substr($this->buffer, 0, $lastHeadingStart);
            $this->buffer = substr($this->buffer, $lastHeadingStart);
            $this->scanOffset -= $lastHeadingStart;
        } else {
            $toProcess = $this->buffer;
            $this->buffer = '';
            $this->scanOffset = 0;
        }

        // @phpstan-ignore-next-line regexp.pattern (pattern is valid: constructor guarantees min <= max)
        $parts = preg_split($pattern, $toProcess, -1, \PREG_SPLIT_DELIM_CAPTURE | \PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return;
        }

        /** @var array<int, string> $parts */

        // Remove unused dead-code variables
        $i = 0;

        // Check if there is content before the first heading
        if (\count($parts) > 0 && !preg_match('/^#{1,6}$/', (string) $parts[0])) {
            $pre = mb_trim((string) $parts[0]);
            if ($pre !== '') {
                yield new Chunk(
                    text: $pre,
                    position: $this->position,
                    metadata: [
                        'strategy' => 'markdown',
                        'length' => mb_strlen($pre),
                        'heading_level' => null,
                        'heading_text' => null,
                    ],
                );
                ++$this->position;
            }
            $i = 1;
        }

        while ($i < \count($parts)) {
            $hashes = isset($parts[$i]) ? (string) $parts[$i] : null;
            $headingText = isset($parts[$i + 1]) ? (string) $parts[$i + 1] : null;
            $content = isset($parts[$i + 2]) ? (string) $parts[$i + 2] : '';

            if ($hashes === null || $headingText === null) {
                break;
            }

            $headingLevel = mb_strlen($hashes);
            $trimmedContent = mb_trim($content);
            $sectionText = $trimmedContent !== '' ? $hashes . ' ' . $headingText . "\n" . $trimmedContent : $hashes . ' ' . $headingText;

            yield new Chunk(
                text: $sectionText,
                position: $this->position,
                metadata: [
                    'strategy' => 'markdown',
                    'length' => mb_strlen($sectionText),
                    'heading_level' => $headingLevel,
                    'heading_text' => mb_trim($headingText),
                ],
            );
            ++$this->position;

            $i += 3;
        }
    }
}
