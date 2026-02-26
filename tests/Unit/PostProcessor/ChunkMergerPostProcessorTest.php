<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\PostProcessor;

use Ecourty\TextChunker\PostProcessor\ChunkMergerPostProcessor;
use Ecourty\TextChunker\ValueObject\Chunk;
use PHPUnit\Framework\TestCase;

class ChunkMergerPostProcessorTest extends TestCase
{
    /** @param Chunk[] $chunks */
    private function makeGenerator(array $chunks): \Generator
    {
        yield from $chunks;
    }

    public function testMergesSmallChunksUntilMinSize(): void
    {
        $pp = new ChunkMergerPostProcessor(minChunkSize: 20);
        $chunks = [
            new Chunk('Hello', 0),
            new Chunk('world', 1),
            new Chunk('this is enough now', 2),
        ];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Hello', $result[0]->getText());
        $this->assertStringContainsString('world', $result[0]->getText());
    }

    public function testYieldsImmediatelyWhenChunkReachesMinSize(): void
    {
        $pp = new ChunkMergerPostProcessor(minChunkSize: 5);
        $chunks = [
            new Chunk('Hello', 0),   // 5 chars — threshold reached
            new Chunk('World', 1),   // 5 chars — threshold reached
        ];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertCount(2, $result);
    }

    public function testRemainingBufferYieldedAtEnd(): void
    {
        $pp = new ChunkMergerPostProcessor(minChunkSize: 1000);
        $chunks = [
            new Chunk('Short A', 0),
            new Chunk('Short B', 1),
        ];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Short A', $result[0]->getText());
        $this->assertStringContainsString('Short B', $result[0]->getText());
    }

    public function testSeparatorIsUsedWhenMerging(): void
    {
        $pp = new ChunkMergerPostProcessor(minChunkSize: 1000, separator: ' | ');
        $chunks = [
            new Chunk('Part A', 0),
            new Chunk('Part B', 1),
        ];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertStringContainsString('Part A | Part B', $result[0]->getText());
    }

    public function testMetadataContainsMergedCount(): void
    {
        $pp = new ChunkMergerPostProcessor(minChunkSize: 1000);
        $chunks = [new Chunk('A', 0), new Chunk('B', 1), new Chunk('C', 2)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals(3, $result[0]->getMetadata()['merged_count']);
    }

    public function testInvalidMinChunkSizeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChunkMergerPostProcessor(minChunkSize: 0);
    }

    public function testSingleChunkPassesThrough(): void
    {
        $pp = new ChunkMergerPostProcessor(minChunkSize: 5);
        $chunks = [new Chunk('Hello', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertCount(1, $result);
        $this->assertEquals('Hello', $result[0]->getText());
    }
}
