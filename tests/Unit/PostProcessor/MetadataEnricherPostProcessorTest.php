<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\PostProcessor;

use Ecourty\TextChunker\PostProcessor\MetadataEnricherPostProcessor;
use Ecourty\TextChunker\ValueObject\Chunk;
use PHPUnit\Framework\TestCase;

class MetadataEnricherPostProcessorTest extends TestCase
{
    /** @param Chunk[] $chunks */
    private function makeGenerator(array $chunks): \Generator
    {
        yield from $chunks;
    }

    public function testEnrichesWithIndexAndTotal(): void
    {
        $pp = new MetadataEnricherPostProcessor();
        $chunks = [new Chunk('First', 0), new Chunk('Second', 1)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)));

        $this->assertEquals(0, $result[0]->getMetadata()['chunk_index']);
        $this->assertEquals(2, $result[0]->getMetadata()['total_chunks']);
        $this->assertEquals(1, $result[1]->getMetadata()['chunk_index']);
    }

    public function testEnrichesWithWordAndCharCount(): void
    {
        $pp = new MetadataEnricherPostProcessor();
        $chunks = [new Chunk('Hello world', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)));

        $this->assertEquals(2, $result[0]->getMetadata()['word_count']);
        $this->assertEquals(11, $result[0]->getMetadata()['char_count']);
    }

    public function testSourceUsesBasename(): void
    {
        $pp = new MetadataEnricherPostProcessor();
        $chunks = [new Chunk('Text', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks), '/path/to/file.txt'));

        $this->assertEquals('file.txt', $result[0]->getMetadata()['source']);
    }
}
