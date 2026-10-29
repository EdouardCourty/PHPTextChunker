<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Strategy;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class SentenceChunkingStrategy implements ChunkingStrategyInterface
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

        $parts = preg_split('/(?<=[.!?])\s+/', $this->buffer, -1, \PREG_SPLIT_NO_EMPTY);

        if ($parts === false) {
            return;
        }

        if ($isEnd || preg_match('/[.!?]\s+$/', $this->buffer) === 1) {
            // Everything is complete: the input ended, or the last sentence is already followed by whitespace
            $this->buffer = '';
        } else {
            // The last part may be an incomplete sentence: keep it for the next call
            $this->buffer = array_pop($parts) ?? '';
        }

        foreach ($parts as $sentence) {
            $trimmed = mb_trim($sentence);

            if ($trimmed === '') {
                continue;
            }

            yield new Chunk(
                text: $trimmed,
                position: $this->position,
                metadata: [
                    'strategy' => 'sentence',
                    'length' => mb_strlen($trimmed),
                ],
            );

            ++$this->position;
        }
    }
}
