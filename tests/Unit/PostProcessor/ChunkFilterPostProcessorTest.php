<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\PostProcessor;

use Ecourty\TextChunker\PostProcessor\ChunkFilterPostProcessor;
use Ecourty\TextChunker\ValueObject\Chunk;
use PHPUnit\Framework\TestCase;

class ChunkFilterPostProcessorTest extends TestCase
{
    /** @param Chunk[] $chunks */
    private function makeGenerator(array $chunks): \Generator
    {
        yield from $chunks;
    }

    public function testFiltersShortChunks(): void
    {
        $pp = new ChunkFilterPostProcessor(10);
        $chunks = [
            new Chunk('Short', 0),
            new Chunk('This is long enough text.', 1),
        ];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)));

        $this->assertCount(1, $result);
        $this->assertStringContainsString('long enough', $result[0]->getText());
    }

    public function testFiltersEmptyChunks(): void
    {
        $pp = new ChunkFilterPostProcessor(0, true);
        $chunks = [new Chunk('', 0), new Chunk('   ', 1), new Chunk('Hello', 2)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)));

        $this->assertCount(1, $result);
        $this->assertEquals('Hello', $result[0]->getText());
    }

    public function testThrowsOnNegativeMinLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ChunkFilterPostProcessor(-1);
    }

    public function testPassesThroughWhenCriteriaAreMet(): void
    {
        $pp = new ChunkFilterPostProcessor(5);
        $chunks = [new Chunk('Hello world!', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)));

        $this->assertCount(1, $result);
    }
}
