<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Strategy;

use Ecourty\TextChunker\Strategy\RegexChunkingStrategy;
use Ecourty\TextChunker\Strategy\RegexDelimiterPosition;
use PHPUnit\Framework\TestCase;

class RegexChunkingStrategyTest extends TestCase
{
    public function testSplitsOnPatternWithNoneMode(): void
    {
        $strategy = new RegexChunkingStrategy('/\|/');
        $input = 'part1|part2|part3';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(3, $chunks);
        $this->assertEquals('part1', $chunks[0]->getText());
        $this->assertEquals('part2', $chunks[1]->getText());
        $this->assertEquals('part3', $chunks[2]->getText());
    }

    public function testDelimiterAsSuffix(): void
    {
        $strategy = new RegexChunkingStrategy('/([.!?])/', RegexDelimiterPosition::Suffix);
        $input = 'Hello. World! How are you?';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(3, $chunks);
        $this->assertStringEndsWith('.', $chunks[0]->getText());
        $this->assertStringEndsWith('!', $chunks[1]->getText());
        $this->assertStringEndsWith('?', $chunks[2]->getText());
    }

    public function testDelimiterAsPrefix(): void
    {
        $strategy = new RegexChunkingStrategy('/(#{1,6} )/', RegexDelimiterPosition::Prefix);
        $input = "# Title\nContent\n## Section\nMore";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertGreaterThanOrEqual(2, \count($chunks));
        $this->assertStringStartsWith('## ', $chunks[1]->getText());
    }

    public function testMetadataContainsPatternAndStrategy(): void
    {
        $strategy = new RegexChunkingStrategy('/\|/');
        $chunks = iterator_to_array($strategy->process('a|b', true));

        $this->assertEquals('regex', $chunks[0]->getMetadata()['strategy']);
        $this->assertEquals('/\|/', $chunks[0]->getMetadata()['pattern']);
    }

    public function testPositionIncrement(): void
    {
        $strategy = new RegexChunkingStrategy('/,/');
        $chunks = iterator_to_array($strategy->process('x,y,z', true));

        $this->assertEquals(0, $chunks[0]->getPosition());
        $this->assertEquals(1, $chunks[1]->getPosition());
        $this->assertEquals(2, $chunks[2]->getPosition());
    }

    public function testResetClearsState(): void
    {
        $strategy = new RegexChunkingStrategy('/,/');
        iterator_to_array($strategy->process('a,b,c', true));

        $strategy->reset();
        $chunks = iterator_to_array($strategy->process('x,y', true));

        $this->assertEquals(0, $chunks[0]->getPosition());
    }

    public function testInvalidPatternThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RegexChunkingStrategy('invalid_no_delimiters');
    }

    public function testEmptyChunksAreSkipped(): void
    {
        $strategy = new RegexChunkingStrategy('/,+/');
        $chunks = iterator_to_array($strategy->process('a,,b,,,c', true));

        $this->assertCount(3, $chunks);
    }
}
