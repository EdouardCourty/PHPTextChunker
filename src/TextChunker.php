<?php

declare(strict_types=1);

namespace Ecourty\TextChunker;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\Contract\ChunkPostProcessorInterface;
use Ecourty\TextChunker\Contract\ReaderInterface;
use Ecourty\TextChunker\Reader\LocalFileReader;
use Ecourty\TextChunker\ValueObject\Chunk;

class TextChunker
{
    private const int READ_BUFFER_SIZE = 8192; // 8 KB

    private ?string $filePath = null;
    private ?string $text = null;
    /** @var array<string, mixed> */
    private array $globalMetadata = [];
    /** @var ChunkPostProcessorInterface[] */
    private array $postProcessors = [];
    private ?ReaderInterface $reader = null;

    public function setFile(string $filePath): self
    {
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

    public function withReader(ReaderInterface $reader): self
    {
        $this->reader = $reader;

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
        $reader = $this->reader ?? new LocalFileReader();
        $buffer = null;
        $hasBuffer = false;

        foreach ($reader->readChunks((string) $this->filePath, self::READ_BUFFER_SIZE) as $data) {
            if ($hasBuffer) {
                foreach ($strategy->process((string) $buffer, false) as $chunk) {
                    yield $this->applyGlobalMetadata($chunk);
                }
            }

            $buffer = $data;
            $hasBuffer = true;
        }

        if ($hasBuffer) {
            foreach ($strategy->process((string) $buffer, true) as $chunk) {
                yield $this->applyGlobalMetadata($chunk);
            }
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
