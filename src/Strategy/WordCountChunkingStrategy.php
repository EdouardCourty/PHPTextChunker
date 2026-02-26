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
            $words = preg_split('/\s+/u', mb_trim($this->buffer), -1, \PREG_SPLIT_NO_EMPTY);

            if ($words === false) {
                break;
            }

            if (\count($words) < $this->wordCount) {
                break;
            }

            $chunkWords = \array_slice($words, 0, $this->wordCount);
            $chunkText = implode(' ', $chunkWords);

            // Advance the buffer past the consumed words
            $consumed = mb_strlen($chunkText);
            $offset = mb_strpos($this->buffer, $chunkWords[0]);
            if ($offset === false) {
                break;
            }
            $this->buffer = mb_trim(mb_substr($this->buffer, $offset + $consumed));

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
