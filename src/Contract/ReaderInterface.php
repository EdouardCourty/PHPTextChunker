<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Contract;

interface ReaderInterface
{
    /**
     * Yield string chunks of data read from the given path.
     * Implementations are responsible for validation and error handling.
     *
     * @throws \Ecourty\TextChunker\Exception\AbstractReadException if the file cannot be opened or read
     *
     * @return \Generator<string>
     */
    public function readChunks(string $path, int $bufferSize): \Generator;
}
