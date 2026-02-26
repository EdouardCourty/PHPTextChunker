<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\ValueObject;

final readonly class Chunk
{
    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private string $text,
        private int $position = 0,
        private array $metadata = [],
    ) {
    }

    public function getText(): string
    {
        return $this->text;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getLength(): int
    {
        return mb_strlen($this->text);
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        return new self($this->text, $this->position, array_merge($this->metadata, $metadata));
    }
}
