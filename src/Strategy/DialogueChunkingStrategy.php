<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Strategy;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class DialogueChunkingStrategy implements ChunkingStrategyInterface
{
    private string $buffer = '';
    private int $position = 0;
    /** @var string[] */
    private array $pendingLines = [];

    public function __construct(
        private readonly int $targetChunkSize = 500,
        private readonly int $minChunkSize = 100,
    ) {
    }

    public function reset(): void
    {
        $this->buffer = '';
        $this->position = 0;
        $this->pendingLines = [];
    }

    public function process(string $data, bool $isEnd): \Generator
    {
        $this->buffer .= $data;

        $normalized = preg_replace('/\n{3,}/', "\n\n", $this->buffer);

        $lines = explode("\n", (string) $normalized);

        if (!$isEnd) {
            $this->buffer = array_pop($lines);
        } else {
            $this->buffer = '';
        }

        foreach ($lines as $line) {
            $trimmed = mb_trim($line);

            if ($trimmed === '') {
                if (\count($this->pendingLines) > 0) {
                    $this->pendingLines[] = '';
                }
                continue;
            }

            $this->pendingLines[] = $trimmed;

            $currentSize = mb_strlen(implode(' ', array_filter($this->pendingLines)));

            if ($currentSize >= $this->targetChunkSize) {
                yield from $this->emitChunk();
            }
        }

        if ($isEnd) {
            if ($this->buffer !== '') {
                $trimmed = mb_trim($this->buffer);
                if ($trimmed !== '') {
                    $this->pendingLines[] = $trimmed;
                }
            }

            yield from $this->emitChunk();
        }
    }

    /** @return \Generator<Chunk> */
    private function emitChunk(): \Generator
    {
        if (\count($this->pendingLines) === 0) {
            return;
        }

        $chunkLines = [];
        $chunkSize = 0;

        foreach ($this->pendingLines as $line) {
            if ($line === '') {
                if ($chunkSize >= $this->minChunkSize && \count($chunkLines) > 0) {
                    yield $this->createChunk($chunkLines);
                    $chunkLines = [];
                    $chunkSize = 0;
                } else {
                    $chunkLines[] = '';
                }
                continue;
            }

            $chunkLines[] = $line;
            $chunkSize += mb_strlen($line);

            if ($chunkSize >= $this->targetChunkSize && $this->isGoodBreakPoint($line)) {
                yield $this->createChunk($chunkLines);
                $chunkLines = [];
                $chunkSize = 0;
            }
        }

        if (\count($chunkLines) > 0) {
            yield $this->createChunk($chunkLines);
        }

        $this->pendingLines = [];
    }

    /** @param string[] $lines */
    private function createChunk(array $lines): Chunk
    {
        $text = $this->joinLines($lines);

        $chunk = new Chunk(
            text: $text,
            position: $this->position,
            metadata: [
                'strategy' => 'dialogue',
                'length' => mb_strlen($text),
                'line_count' => \count(array_filter($lines)),
            ],
        );

        ++$this->position;

        return $chunk;
    }

    /** @param string[] $lines */
    private function joinLines(array $lines): string
    {
        $result = [];
        $previousWasEmpty = false;

        foreach ($lines as $line) {
            if ($line === '') {
                if (!$previousWasEmpty) {
                    $result[] = '';
                }
                $previousWasEmpty = true;
            } else {
                $result[] = $line;
                $previousWasEmpty = false;
            }
        }

        return implode("\n", $result);
    }

    private function isGoodBreakPoint(string $line): bool
    {
        $line = mb_trim($line);

        if ($line === '') {
            return false;
        }

        if (preg_match('/[.!?]"?\s*$/', $line)) {
            return true;
        }

        if (mb_strlen($line) > 100) {
            return true;
        }

        return false;
    }
}
