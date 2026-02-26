<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Strategy;

use Ecourty\TextChunker\Strategy\LineChunkingStrategy;
use PHPUnit\Framework\TestCase;

class LineChunkingStrategyTest extends TestCase
{
    public function testGroupsLinesIntoChunks(): void
    {
        $strategy = new LineChunkingStrategy(linesPerChunk: 2);
        $input = "line1\nline2\nline3\nline4";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertEquals("line1\nline2", $chunks[0]->getText());
        $this->assertEquals("line3\nline4", $chunks[1]->getText());
    }

    public function testRemainingLinesYieldedAtEnd(): void
    {
        $strategy = new LineChunkingStrategy(linesPerChunk: 3);
        $input = "a\nb\nc\nd\ne";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertEquals("a\nb\nc", $chunks[0]->getText());
        $this->assertEquals("d\ne", $chunks[1]->getText());
    }

    public function testMetadataContainsLineCount(): void
    {
        $strategy = new LineChunkingStrategy(linesPerChunk: 3);
        $input = "one\ntwo\nthree";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertEquals('line', $chunks[0]->getMetadata()['strategy']);
        $this->assertEquals(3, $chunks[0]->getMetadata()['line_count']);
    }

    public function testPositionIncrement(): void
    {
        $strategy = new LineChunkingStrategy(linesPerChunk: 1);
        $input = "a\nb\nc";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertEquals(0, $chunks[0]->getPosition());
        $this->assertEquals(1, $chunks[1]->getPosition());
        $this->assertEquals(2, $chunks[2]->getPosition());
    }

    public function testEmptyLinesDoNotProduceEmptyChunks(): void
    {
        $strategy = new LineChunkingStrategy(linesPerChunk: 2);
        $input = "\n\n\n\n";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(0, $chunks);
    }

    public function testResetClearsState(): void
    {
        $strategy = new LineChunkingStrategy(linesPerChunk: 2);
        iterator_to_array($strategy->process("a\nb\nc\nd", true));

        $strategy->reset();
        $chunks = iterator_to_array($strategy->process("x\ny", true));

        $this->assertEquals(0, $chunks[0]->getPosition());
    }

    public function testInvalidLinesPerChunkThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LineChunkingStrategy(linesPerChunk: 0);
    }

    public function testSingleLinePerChunk(): void
    {
        $strategy = new LineChunkingStrategy(linesPerChunk: 1);
        $input = "alpha\nbeta\ngamma";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(3, $chunks);
        $this->assertEquals('alpha', $chunks[0]->getText());
        $this->assertEquals('beta', $chunks[1]->getText());
        $this->assertEquals('gamma', $chunks[2]->getText());
    }
}
