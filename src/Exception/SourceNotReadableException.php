<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Exception;

class SourceNotReadableException extends AbstractReadException
{
    public function __construct(string $path)
    {
        parent::__construct(\sprintf('Source not readable: %s', $path));
    }
}
