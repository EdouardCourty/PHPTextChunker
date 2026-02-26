<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Contract;

use Ecourty\TextChunker\ValueObject\Chunk;

interface ChunkingStrategyInterface
{
    /**
     * Process a chunk of content and yield complete chunks when ready.
     * Called repeatedly as content is streamed.
     *
     * @param string $data  Current chunk of data
     * @param bool   $isEnd Whether this is the last chunk
     *
     * @return \Generator<Chunk>
     */
    public function process(string $data, bool $isEnd): \Generator;

    /**
     * Reset internal state for a new chunking session.
     */
    public function reset(): void;
}
