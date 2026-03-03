<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Reader;

use Ecourty\TextChunker\Contract\ReaderInterface;
use Ecourty\TextChunker\Exception\SourceNotFoundException;
use Ecourty\TextChunker\Exception\SourceNotReadableException;
use Ecourty\TextChunker\Exception\SourceOpenException;

class LocalFileReader implements ReaderInterface
{
    /**
     * @return \Generator<string>
     */
    public function readChunks(string $path, int $bufferSize): \Generator
    {
        if (!file_exists($path)) {
            throw new SourceNotFoundException($path);
        }

        if (!is_readable($path)) {
            throw new SourceNotReadableException($path);
        }

        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new SourceOpenException($path);
        }

        $bufferSize = max(1, $bufferSize);

        try {
            while (!feof($handle)) {
                $data = fread($handle, $bufferSize);

                if ($data === false) {
                    break;
                }

                yield $data;
            }
        } finally {
            fclose($handle);
        }
    }
}
