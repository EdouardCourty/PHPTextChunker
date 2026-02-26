<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Strategy;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class RecursiveChunkingStrategy implements ChunkingStrategyInterface
{
    private int $position = 0;

    /**
     * @param ChunkingStrategyInterface[] $strategies Ordered list of fallback strategies
     */
    public function __construct(
        private readonly array $strategies,
        private readonly int $maxChunkSize,
    ) {
        if ($this->strategies === []) {
            throw new \InvalidArgumentException('At least one strategy must be provided');
        }
        if ($this->maxChunkSize <= 0) {
            throw new \InvalidArgumentException('maxChunkSize must be > 0');
        }
        foreach ($this->strategies as $strategy) {
            if (!$strategy instanceof ChunkingStrategyInterface) { // @phpstan-ignore-line instanceof.alwaysTrue
                throw new \InvalidArgumentException('All strategies must implement ChunkingStrategyInterface');
            }
        }
    }

    public function reset(): void
    {
        $this->position = 0;
        foreach ($this->strategies as $strategy) {
            $strategy->reset();
        }
    }

    public function process(string $data, bool $isEnd): \Generator
    {
        $primary = $this->strategies[0];

        foreach ($primary->process($data, $isEnd) as $chunk) {
            yield from $this->splitChunk($chunk->getText(), depth: 0);
        }
    }

    /** @return \Generator<Chunk> */
    private function splitChunk(string $text, int $depth): \Generator
    {
        if (mb_strlen($text) <= $this->maxChunkSize || !isset($this->strategies[$depth + 1])) {
            yield $this->createChunk($text, $depth);

            return;
        }

        $nextStrategy = $this->strategies[$depth + 1];
        $nextStrategy->reset();

        $subChunks = iterator_to_array($nextStrategy->process($text, true), false);

        if ($subChunks === []) {
            yield $this->createChunk($text, $depth);

            return;
        }

        foreach ($subChunks as $subChunk) {
            yield from $this->splitChunk($subChunk->getText(), depth: $depth + 1);
        }
    }

    private function createChunk(string $text, int $depth): Chunk
    {
        $chunk = new Chunk(
            text: $text,
            position: $this->position,
            metadata: [
                'strategy' => 'recursive',
                'length' => mb_strlen($text),
                'depth' => $depth,
            ],
        );

        ++$this->position;

        return $chunk;
    }
}
