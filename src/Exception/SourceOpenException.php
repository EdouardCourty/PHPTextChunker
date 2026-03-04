<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Exception;

class SourceOpenException extends AbstractReadException
{
    public function __construct(string $path)
    {
        parent::__construct(\sprintf('Failed to open source: %s', $path));
    }
}
