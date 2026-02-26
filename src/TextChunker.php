<?php

declare(strict_types=1);

namespace Ecourty\TextChunker;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\Contract\ChunkPostProcessorInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

class TextChunker
{
    private const int READ_BUFFER_SIZE = 8192;

    private ?string $filePath = null;
    private ?string $text = null;
    /** @var array<string, mixed> */
    private array $globalMetadata = [];
    /** @var ChunkPostProcessorInterface[] */
    private array $postProcessors = [];

    public function setFile(string $filePath): self
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException(\sprintf('File not found: %s', $filePath));
        }

        if (!is_readable($filePath)) {
            throw new \InvalidArgumentException(\sprintf('File not readable: %s', $filePath));
        }

        $this->filePath = $filePath;
        $this->text = null;

        return $this;
    }

    public function setText(string $text): self
    {
        $this->text = $text;
        $this->filePath = null;

        return $this;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function withMetadata(array $metadata): self
    {
        $this->globalMetadata = array_merge($this->globalMetadata, $metadata);

        return $this;
    }

    public function withPostProcessor(ChunkPostProcessorInterface $postProcessor): self
    {
        $this->postProcessors[] = $postProcessor;

        return $this;
    }

    public function withPostProcessors(ChunkPostProcessorInterface ...$postProcessors): self
    {
        foreach ($postProcessors as $postProcessor) {
            $this->withPostProcessor($postProcessor);
        }

        return $this;
    }

    /**
     * @return \Generator<Chunk>
     */
    public function chunk(ChunkingStrategyInterface $strategy): \Generator
    {
        if ($this->filePath === null && $this->text === null) {
            throw new \RuntimeException('No source set. Call setFile() or setText() first.');
        }

        $strategy->reset();

        $baseChunks = $this->filePath !== null
            ? $this->generateChunksFromFile($strategy)
            : $this->generateChunksFromText($strategy);

        yield from $this->applyPostProcessors($baseChunks);
    }

    /**
     * @return \Generator<Chunk>
     */
    private function generateChunksFromFile(ChunkingStrategyInterface $strategy): \Generator
    {
        $fileHandle = fopen((string) $this->filePath, 'r');

        if ($fileHandle === false) {
            throw new \RuntimeException(\sprintf('Failed to open file: %s', $this->filePath));
        }

        try {
            while (!feof($fileHandle)) {
                $data = fread($fileHandle, self::READ_BUFFER_SIZE);

                if ($data === false) {
                    break;
                }

                $isEnd = feof($fileHandle);

                foreach ($strategy->process($data, $isEnd) as $chunk) {
                    yield $this->applyGlobalMetadata($chunk);
                }
            }
        } finally {
            fclose($fileHandle);
        }
    }

    /**
     * @return \Generator<Chunk>
     */
    private function generateChunksFromText(ChunkingStrategyInterface $strategy): \Generator
    {
        foreach ($strategy->process((string) $this->text, true) as $chunk) {
            yield $this->applyGlobalMetadata($chunk);
        }
    }

    private function applyGlobalMetadata(Chunk $chunk): Chunk
    {
        if ($this->globalMetadata !== []) {
            return $chunk->withMetadata($this->globalMetadata);
        }

        return $chunk;
    }

    /**
     * @param \Generator<Chunk> $chunks
     *
     * @return \Generator<Chunk>
     */
    private function applyPostProcessors(\Generator $chunks): \Generator
    {
        $source = $this->filePath ?? '';
        /** @var \Generator<Chunk> $current */
        $current = $chunks;

        foreach ($this->postProcessors as $postProcessor) {
            $current = $postProcessor->process($current, $source);
        }

        yield from $current;
    }
}
