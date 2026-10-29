<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Strategy;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class WordCountChunkingStrategy implements ChunkingStrategyInterface
{
    private string $buffer = '';
    private int $position = 0;

    public function __construct(
        private readonly int $wordCount = 200,
    ) {
        if ($this->wordCount <= 0) {
            throw new \InvalidArgumentException('Word count must be > 0');
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

        while (true) {
            $found = preg_match_all('/\S+/u', $this->buffer, $matches, \PREG_OFFSET_CAPTURE);

            if ($found === false) {
                break;
            }

            // Without the end of the input, the last word may be incomplete unless whitespace follows it
            $available = $found;
            if (!$isEnd && $found > 0 && preg_match('/\s$/u', $this->buffer) !== 1) {
                --$available;
            }

            if ($available < $this->wordCount) {
                break;
            }

            $chunkWords = array_column(\array_slice($matches[0], 0, $this->wordCount), 0);
            $chunkText = implode(' ', $chunkWords);

            // Advance the buffer past the byte range of the consumed words, whatever whitespace separated them
            $lastWord = $matches[0][$this->wordCount - 1];
            $this->buffer = mb_ltrim(substr($this->buffer, $lastWord[1] + \strlen($lastWord[0])));

            yield new Chunk(
                text: $chunkText,
                position: $this->position,
                metadata: [
                    'strategy' => 'word_count',
                    'length' => mb_strlen($chunkText),
                    'word_count' => \count($chunkWords),
                ],
            );

            ++$this->position;
        }

        if ($isEnd && $this->buffer !== '') {
            $trimmed = mb_trim($this->buffer);
            if ($trimmed !== '') {
                $words = preg_split('/\s+/u', $trimmed, -1, \PREG_SPLIT_NO_EMPTY);

                yield new Chunk(
                    text: $trimmed,
                    position: $this->position,
                    metadata: [
                        'strategy' => 'word_count',
                        'length' => mb_strlen($trimmed),
                        'word_count' => \is_array($words) ? \count($words) : 0,
                    ],
                );

                $this->buffer = '';
            }
        }
    }
}
