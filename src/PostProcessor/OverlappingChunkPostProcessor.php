<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\PostProcessor;

use Ecourty\TextChunker\Contract\ChunkPostProcessorInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class OverlappingChunkPostProcessor implements ChunkPostProcessorInterface
{
    public function __construct(
        private readonly int $overlapSize,
    ) {
        if ($this->overlapSize < 0) {
            throw new \InvalidArgumentException('Overlap size must be >= 0');
        }
    }

    public function process(\Generator $chunks, string $source = ''): \Generator
    {
        $previousChunk = null;

        foreach ($chunks as $chunk) {
            if ($previousChunk !== null && $this->overlapSize > 0) {
                $overlapText = mb_substr($previousChunk->getText(), -$this->overlapSize);

                $chunk = new Chunk(
                    text: $overlapText . $chunk->getText(),
                    position: $chunk->getPosition(),
                    metadata: array_merge($chunk->getMetadata(), [
                        'overlap_size' => $this->overlapSize,
                        'has_overlap' => true,
                    ]),
                );
            }

            yield $chunk;
            $previousChunk = $chunk;
        }
    }
}
