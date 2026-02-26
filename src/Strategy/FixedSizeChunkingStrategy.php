<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Strategy;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class FixedSizeChunkingStrategy implements ChunkingStrategyInterface
{
    private string $buffer = '';
    private int $position = 0;
    private int $charOffset = 0;

    public function __construct(
        private readonly int $chunkSize = 1000,
    ) {
        if ($this->chunkSize <= 0) {
            throw new \InvalidArgumentException('Chunk size must be > 0');
        }
    }

    public function reset(): void
    {
        $this->buffer = '';
        $this->position = 0;
        $this->charOffset = 0;
    }

    public function process(string $data, bool $isEnd): \Generator
    {
        $this->buffer .= $data;

        while (mb_strlen($this->buffer) >= $this->chunkSize) {
            $text = mb_substr($this->buffer, 0, $this->chunkSize);
            $this->buffer = mb_substr($this->buffer, $this->chunkSize);

            $start = $this->charOffset;
            $this->charOffset += $this->chunkSize;

            yield new Chunk(
                text: $text,
                position: $this->position,
                metadata: [
                    'strategy' => 'fixed_size',
                    'chunk_size' => $this->chunkSize,
                    'char_start' => $start,
                    'char_end' => $this->charOffset,
                ],
            );

            ++$this->position;
        }

        if ($isEnd && $this->buffer !== '') {
            $trimmed = mb_trim($this->buffer);
            if ($trimmed !== '') {
                $start = $this->charOffset;

                yield new Chunk(
                    text: $trimmed,
                    position: $this->position,
                    metadata: [
                        'strategy' => 'fixed_size',
                        'chunk_size' => $this->chunkSize,
                        'char_start' => $start,
                        'char_end' => $start + mb_strlen($trimmed),
                    ],
                );
            }
        }
    }
}
