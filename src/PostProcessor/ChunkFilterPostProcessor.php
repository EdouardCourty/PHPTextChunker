<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\PostProcessor;

use Ecourty\TextChunker\Contract\ChunkPostProcessorInterface;

final class ChunkFilterPostProcessor implements ChunkPostProcessorInterface
{
    public function __construct(
        private readonly int $minLength = 50,
        private readonly bool $removeEmpty = true,
    ) {
        if ($this->minLength < 0) {
            throw new \InvalidArgumentException('Min length must be >= 0');
        }
    }

    public function process(\Generator $chunks, string $source = ''): \Generator
    {
        foreach ($chunks as $chunk) {
            $text = mb_trim($chunk->getText());

            if ($this->removeEmpty && $text === '') {
                continue;
            }

            if (mb_strlen($text) < $this->minLength) {
                continue;
            }

            yield $chunk;
        }
    }
}
