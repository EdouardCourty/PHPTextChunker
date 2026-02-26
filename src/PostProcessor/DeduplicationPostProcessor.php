<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\PostProcessor;

use Ecourty\TextChunker\Contract\ChunkPostProcessorInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class DeduplicationPostProcessor implements ChunkPostProcessorInterface
{
    /** @var array<string, true> */
    private array $seen = [];

    public function process(\Generator $chunks, string $source = ''): \Generator
    {
        $this->seen = [];

        foreach ($chunks as $chunk) {
            $hash = md5($chunk->getText());

            if (isset($this->seen[$hash])) {
                continue;
            }

            $this->seen[$hash] = true;

            yield new Chunk(
                text: $chunk->getText(),
                position: $chunk->getPosition(),
                metadata: array_merge($chunk->getMetadata(), [
                    'content_hash' => $hash,
                ]),
            );
        }
    }
}
