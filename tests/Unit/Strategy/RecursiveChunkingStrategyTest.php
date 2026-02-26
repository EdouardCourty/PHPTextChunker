<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Strategy;

use Ecourty\TextChunker\Strategy\ParagraphChunkingStrategy;
use Ecourty\TextChunker\Strategy\RecursiveChunkingStrategy;
use Ecourty\TextChunker\Strategy\SentenceChunkingStrategy;
use Ecourty\TextChunker\Strategy\WordCountChunkingStrategy;
use PHPUnit\Framework\TestCase;

class RecursiveChunkingStrategyTest extends TestCase
{
    public function testYieldsChunkDirectlyWhenUnderMaxSize(): void
    {
        $strategy = new RecursiveChunkingStrategy(
            strategies: [new ParagraphChunkingStrategy()],
            maxChunkSize: 1000,
        );

        $chunks = iterator_to_array($strategy->process('Short paragraph.', true), false);

        $this->assertCount(1, $chunks);
        $this->assertEquals('Short paragraph.', $chunks[0]->getText());
        $this->assertEquals(0, $chunks[0]->getMetadata()['depth']);
    }

    public function testFallsBackToNextStrategyWhenChunkTooLarge(): void
    {
        $longParagraph = str_repeat('word ', 100); // 500 chars
        $input = $longParagraph . "\n\n" . 'Short.';

        $strategy = new RecursiveChunkingStrategy(
            strategies: [
                new ParagraphChunkingStrategy(),
                new WordCountChunkingStrategy(wordCount: 10),
            ],
            maxChunkSize: 50,
        );

        $chunks = iterator_to_array($strategy->process($input, true), false);

        $this->assertGreaterThan(1, \count($chunks));

        // The short paragraph should not be re-split
        $shortChunks = array_filter($chunks, fn ($c) => $c->getText() === 'Short.');
        $this->assertNotEmpty($shortChunks);
        $short = array_values($shortChunks)[0];
        $this->assertEquals(0, $short->getMetadata()['depth']);
    }

    public function testDepthReflectsRecursionLevel(): void
    {
        $longText = str_repeat('word ', 100);

        $strategy = new RecursiveChunkingStrategy(
            strategies: [
                new ParagraphChunkingStrategy(),
                new SentenceChunkingStrategy(),
                new WordCountChunkingStrategy(wordCount: 5),
            ],
            maxChunkSize: 10,
        );

        $chunks = iterator_to_array($strategy->process($longText, true), false);

        $depths = array_unique(array_map(fn ($c) => $c->getMetadata()['depth'], $chunks));
        $this->assertNotEmpty($depths);
        \assert($depths !== []);
        $this->assertGreaterThanOrEqual(1, max($depths));
    }

    public function testMetadataContainsStrategyAndLength(): void
    {
        $strategy = new RecursiveChunkingStrategy(
            strategies: [new ParagraphChunkingStrategy()],
            maxChunkSize: 1000,
        );

        $chunks = iterator_to_array($strategy->process('Hello world.', true), false);

        $this->assertEquals('recursive', $chunks[0]->getMetadata()['strategy']);
        $this->assertEquals(mb_strlen('Hello world.'), $chunks[0]->getMetadata()['length']);
    }

    public function testPositionIncrement(): void
    {
        $input = "Para one.\n\nPara two.\n\nPara three.";
        $strategy = new RecursiveChunkingStrategy(
            strategies: [new ParagraphChunkingStrategy()],
            maxChunkSize: 1000,
        );

        $chunks = iterator_to_array($strategy->process($input, true), false);

        $this->assertEquals(0, $chunks[0]->getPosition());
        $this->assertEquals(1, $chunks[1]->getPosition());
        $this->assertEquals(2, $chunks[2]->getPosition());
    }

    public function testResetClearsState(): void
    {
        $strategy = new RecursiveChunkingStrategy(
            strategies: [new ParagraphChunkingStrategy()],
            maxChunkSize: 1000,
        );

        iterator_to_array($strategy->process("A.\n\nB.", true), false);
        $strategy->reset();

        $chunks = iterator_to_array($strategy->process('X.', true), false);
        $this->assertEquals(0, $chunks[0]->getPosition());
    }

    public function testYieldsOversizedChunkWhenNoMoreStrategies(): void
    {
        $bigText = str_repeat('x', 200);

        $strategy = new RecursiveChunkingStrategy(
            strategies: [new ParagraphChunkingStrategy()],
            maxChunkSize: 10,
        );

        $chunks = iterator_to_array($strategy->process($bigText, true), false);

        // No more fallback strategy — yield as-is
        $this->assertCount(1, $chunks);
        $this->assertEquals($bigText, $chunks[0]->getText());
    }

    public function testEmptyStrategiesThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RecursiveChunkingStrategy(strategies: [], maxChunkSize: 100);
    }

    public function testInvalidMaxChunkSizeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RecursiveChunkingStrategy(
            strategies: [new ParagraphChunkingStrategy()],
            maxChunkSize: 0,
        );
    }
}
