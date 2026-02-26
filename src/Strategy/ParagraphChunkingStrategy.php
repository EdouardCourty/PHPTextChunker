<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Strategy;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class ParagraphChunkingStrategy implements ChunkingStrategyInterface
{
    private string $buffer = '';
    private int $position = 0;

    public function reset(): void
    {
        $this->buffer = '';
        $this->position = 0;
    }

    public function process(string $data, bool $isEnd): \Generator
    {
        $this->buffer .= $data;

        $parts = explode("\n\n", $this->buffer);

        if (!$isEnd) {
            $this->buffer = array_pop($parts) ?: '';
        } else {
            $this->buffer = '';
        }

        foreach ($parts as $paragraph) {
            $trimmed = mb_trim($paragraph);

            if ($trimmed === '') {
                continue;
            }

            yield new Chunk(
                text: $trimmed,
                position: $this->position,
                metadata: [
                    'strategy' => 'paragraph',
                    'length' => mb_strlen($trimmed),
                ],
            );

            ++$this->position;
        }

        if ($isEnd && $this->buffer !== '') {
            $trimmed = mb_trim($this->buffer);
            if ($trimmed !== '') {
                yield new Chunk(
                    text: $trimmed,
                    position: $this->position,
                    metadata: [
                        'strategy' => 'paragraph',
                        'length' => mb_strlen($trimmed),
                    ],
                );
            }
        }
    }
}
