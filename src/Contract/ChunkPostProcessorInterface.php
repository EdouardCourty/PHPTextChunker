<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Contract;

use Ecourty\TextChunker\ValueObject\Chunk;

interface ChunkPostProcessorInterface
{
    /**
     * Wrap a generator of chunks and yield processed chunks.
     * Post-processors stream chunks without loading all into memory.
     *
     * @param \Generator<Chunk> $chunks
     * @param string            $source Original source identifier (file path, label, etc.)
     *
     * @return \Generator<Chunk>
     */
    public function process(\Generator $chunks, string $source = ''): \Generator;
}
