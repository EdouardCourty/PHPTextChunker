<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Strategy;

use Ecourty\TextChunker\Strategy\ParagraphChunkingStrategy;
use PHPUnit\Framework\TestCase;

class ParagraphChunkingStrategyTest extends TestCase
{
    public function testSplitsOnDoubleNewline(): void
    {
        $strategy = new ParagraphChunkingStrategy();
        $strategy->reset();

        $chunks = iterator_to_array($strategy->process("First.\n\nSecond.\n\nThird.", true));

        $this->assertCount(3, $chunks);
        $this->assertEquals('First.', $chunks[0]->getText());
        $this->assertEquals('Second.', $chunks[1]->getText());
        $this->assertEquals('Third.', $chunks[2]->getText());
    }

    public function testSkipsEmptyParagraphs(): void
    {
        $strategy = new ParagraphChunkingStrategy();
        $strategy->reset();

        $chunks = iterator_to_array($strategy->process("Hello.\n\n\n\nWorld.", true));

        $this->assertCount(2, $chunks);
    }

    public function testPositionIncrement(): void
    {
        $strategy = new ParagraphChunkingStrategy();
        $strategy->reset();

        $chunks = iterator_to_array($strategy->process("A.\n\nB.\n\nC.", true));

        $this->assertEquals(0, $chunks[0]->getPosition());
        $this->assertEquals(1, $chunks[1]->getPosition());
        $this->assertEquals(2, $chunks[2]->getPosition());
    }

    public function testMetadataContainsStrategy(): void
    {
        $strategy = new ParagraphChunkingStrategy();
        $strategy->reset();

        $chunks = iterator_to_array($strategy->process('Hello world.', true));

        $this->assertArrayHasKey('strategy', $chunks[0]->getMetadata());
        $this->assertEquals('paragraph', $chunks[0]->getMetadata()['strategy']);
    }

    public function testResetClearsState(): void
    {
        $strategy = new ParagraphChunkingStrategy();
        $strategy->reset();
        iterator_to_array($strategy->process("A.\n\nB.", true));

        $strategy->reset();
        $chunks = iterator_to_array($strategy->process('X.', true));

        $this->assertEquals(0, $chunks[0]->getPosition());
    }
}
