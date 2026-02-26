<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Strategy;

use Ecourty\TextChunker\Strategy\WordCountChunkingStrategy;
use PHPUnit\Framework\TestCase;

class WordCountChunkingStrategyTest extends TestCase
{
    public function testSplitsOnWordCount(): void
    {
        $strategy = new WordCountChunkingStrategy(wordCount: 3);
        $input = 'one two three four five six';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertEquals('one two three', $chunks[0]->getText());
        $this->assertEquals('four five six', $chunks[1]->getText());
    }

    public function testRemainingWordsYieldedAtEnd(): void
    {
        $strategy = new WordCountChunkingStrategy(wordCount: 3);
        $input = 'one two three four five';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertEquals('four five', $chunks[1]->getText());
    }

    public function testMetadataContainsWordCount(): void
    {
        $strategy = new WordCountChunkingStrategy(wordCount: 3);
        $input = 'one two three';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertEquals('word_count', $chunks[0]->getMetadata()['strategy']);
        $this->assertEquals(3, $chunks[0]->getMetadata()['word_count']);
    }

    public function testPositionIncrement(): void
    {
        $strategy = new WordCountChunkingStrategy(wordCount: 2);
        $input = 'a b c d';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertEquals(0, $chunks[0]->getPosition());
        $this->assertEquals(1, $chunks[1]->getPosition());
    }

    public function testResetClearsState(): void
    {
        $strategy = new WordCountChunkingStrategy(wordCount: 2);
        iterator_to_array($strategy->process('a b c d', true));

        $strategy->reset();
        $chunks = iterator_to_array($strategy->process('x y', true));

        $this->assertEquals(0, $chunks[0]->getPosition());
    }

    public function testInvalidWordCountThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new WordCountChunkingStrategy(wordCount: 0);
    }

    public function testFewerWordsThanLimitYieldsOneChunk(): void
    {
        $strategy = new WordCountChunkingStrategy(wordCount: 100);
        $input = 'just a few words';

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(1, $chunks);
        $this->assertEquals('just a few words', $chunks[0]->getText());
    }
}
