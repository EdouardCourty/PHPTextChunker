<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\PostProcessor;

use Ecourty\TextChunker\PostProcessor\TextNormalizationPostProcessor;
use Ecourty\TextChunker\ValueObject\Chunk;
use PHPUnit\Framework\TestCase;

class TextNormalizationPostProcessorTest extends TestCase
{
    /** @param Chunk[] $chunks */
    private function makeGenerator(array $chunks): \Generator
    {
        yield from $chunks;
    }

    public function testCollapsesMultipleSpaces(): void
    {
        $pp = new TextNormalizationPostProcessor();
        $chunks = [new Chunk('Hello   world', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals('Hello world', $result[0]->getText());
    }

    public function testCollapsesMultipleBlankLines(): void
    {
        $pp = new TextNormalizationPostProcessor();
        $chunks = [new Chunk("Line 1\n\n\n\nLine 2", 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals("Line 1\n\nLine 2", $result[0]->getText());
    }

    public function testTrimsEachLine(): void
    {
        $pp = new TextNormalizationPostProcessor();
        $chunks = [new Chunk("  Hello  \n  World  ", 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals("Hello\nWorld", $result[0]->getText());
    }

    public function testStripsControlCharacters(): void
    {
        $pp = new TextNormalizationPostProcessor();
        $chunks = [new Chunk("Hello\x01\x02World", 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals('HelloWorld', $result[0]->getText());
    }

    public function testPreservesNewlinesWhenStrippingControls(): void
    {
        $pp = new TextNormalizationPostProcessor();
        $chunks = [new Chunk("Line 1\nLine 2", 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertStringContainsString("\n", $result[0]->getText());
    }

    public function testCanDisableCollapseWhitespace(): void
    {
        $pp = new TextNormalizationPostProcessor(collapseWhitespace: false);
        $chunks = [new Chunk('Hello   world', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals('Hello   world', $result[0]->getText());
    }

    public function testCanDisableTrimLines(): void
    {
        $pp = new TextNormalizationPostProcessor(collapseWhitespace: false, trimLines: false);
        $chunks = [new Chunk('  Hello  ', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals('Hello', $result[0]->getText()); // mb_trim at the end still applies
    }

    public function testPositionIsPreserved(): void
    {
        $pp = new TextNormalizationPostProcessor();
        $chunks = [new Chunk('Hello', 42)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals(42, $result[0]->getPosition());
    }

    public function testMetadataIsPreserved(): void
    {
        $pp = new TextNormalizationPostProcessor();
        $chunks = [new Chunk('Hello', 0, ['strategy' => 'paragraph'])];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals('paragraph', $result[0]->getMetadata()['strategy']);
    }
}
