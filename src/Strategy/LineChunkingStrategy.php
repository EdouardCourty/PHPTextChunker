<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Strategy;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class LineChunkingStrategy implements ChunkingStrategyInterface
{
    private string $buffer = '';
    private int $position = 0;
    /** @var string[] */
    private array $pendingLines = [];

    public function __construct(
        private readonly int $linesPerChunk = 10,
    ) {
        if ($this->linesPerChunk <= 0) {
            throw new \InvalidArgumentException('Lines per chunk must be > 0');
        }
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

        $lines = explode("\n", $this->buffer);

        if (!$isEnd) {
            $this->buffer = (string) array_pop($lines);
        } else {
            $this->buffer = '';
        }

        foreach ($lines as $line) {
            $this->pendingLines[] = $line;

            if (\count($this->pendingLines) >= $this->linesPerChunk) {
                $chunk = $this->buildChunk();
                if ($chunk !== null) {
                    yield $chunk;
                }
            }
        }

        if ($isEnd && \count($this->pendingLines) > 0) {
            $chunk = $this->buildChunk();
            if ($chunk !== null) {
                yield $chunk;
            }
        }
    }

    private function buildChunk(): ?Chunk
    {
        $text = implode("\n", $this->pendingLines);
        $trimmed = mb_trim($text);
        $this->pendingLines = [];

        if ($trimmed === '') {
            return null;
        }

        $chunk = new Chunk(
            text: $trimmed,
            position: $this->position,
            metadata: [
                'strategy' => 'line',
                'length' => mb_strlen($trimmed),
                'line_count' => \count(explode("\n", $trimmed)),
            ],
        );

        ++$this->position;

        return $chunk;
    }
}
