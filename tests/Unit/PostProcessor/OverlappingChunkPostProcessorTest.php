<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\PostProcessor;

use Ecourty\TextChunker\PostProcessor\OverlappingChunkPostProcessor;
use Ecourty\TextChunker\ValueObject\Chunk;
use PHPUnit\Framework\TestCase;

class OverlappingChunkPostProcessorTest extends TestCase
{
    /** @param Chunk[] $chunks */
    private function makeGenerator(array $chunks): \Generator
    {
        yield from $chunks;
    }

    public function testAddsOverlapFromPreviousChunk(): void
    {
        $pp = new OverlappingChunkPostProcessor(5);
        $chunks = [
            new Chunk('Hello World', 0),
            new Chunk('Foo Bar', 1),
        ];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)));

        $this->assertStringStartsWith('World', $result[1]->getText());
        $this->assertTrue($result[1]->getMetadata()['has_overlap']);
        $this->assertEquals(5, $result[1]->getMetadata()['overlap_size']);
    }

    public function testFirstChunkHasNoOverlap(): void
    {
        $pp = new OverlappingChunkPostProcessor(10);
        $chunks = [new Chunk('Hello', 0), new Chunk('World', 1)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)));

        $this->assertArrayNotHasKey('has_overlap', $result[0]->getMetadata());
    }

    public function testZeroOverlapPassesThrough(): void
    {
        $pp = new OverlappingChunkPostProcessor(0);
        $chunks = [new Chunk('A', 0), new Chunk('B', 1)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)));

        $this->assertEquals('A', $result[0]->getText());
        $this->assertEquals('B', $result[1]->getText());
    }

    public function testThrowsOnNegativeOverlap(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new OverlappingChunkPostProcessor(-1);
    }
}
