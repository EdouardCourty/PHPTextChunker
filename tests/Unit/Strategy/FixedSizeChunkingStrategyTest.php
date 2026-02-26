<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Strategy;

use Ecourty\TextChunker\Strategy\FixedSizeChunkingStrategy;
use PHPUnit\Framework\TestCase;

class FixedSizeChunkingStrategyTest extends TestCase
{
    public function testChunksToExactSize(): void
    {
        $strategy = new FixedSizeChunkingStrategy(10);
        $strategy->reset();

        $chunks = iterator_to_array($strategy->process(str_repeat('A', 25), true));

        $this->assertEquals(10, mb_strlen($chunks[0]->getText()));
        $this->assertEquals(10, mb_strlen($chunks[1]->getText()));
        $this->assertEquals(5, mb_strlen($chunks[2]->getText()));
    }

    public function testCharOffsets(): void
    {
        $strategy = new FixedSizeChunkingStrategy(10);
        $strategy->reset();

        $chunks = iterator_to_array($strategy->process(str_repeat('A', 20), true));

        $this->assertEquals(0, $chunks[0]->getMetadata()['char_start']);
        $this->assertEquals(10, $chunks[0]->getMetadata()['char_end']);
        $this->assertEquals(10, $chunks[1]->getMetadata()['char_start']);
        $this->assertEquals(20, $chunks[1]->getMetadata()['char_end']);
    }

    public function testThrowsOnInvalidSize(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new FixedSizeChunkingStrategy(0);
    }

    public function testMetadataContainsStrategy(): void
    {
        $strategy = new FixedSizeChunkingStrategy(100);
        $strategy->reset();

        $chunks = iterator_to_array($strategy->process('Hello world', true));

        $this->assertEquals('fixed_size', $chunks[0]->getMetadata()['strategy']);
    }
}
