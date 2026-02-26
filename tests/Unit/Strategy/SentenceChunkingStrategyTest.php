<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Strategy;

use Ecourty\TextChunker\Strategy\SentenceChunkingStrategy;
use PHPUnit\Framework\TestCase;

class SentenceChunkingStrategyTest extends TestCase
{
    public function testSplitsOnSentenceBoundaries(): void
    {
        $strategy = new SentenceChunkingStrategy();
        $strategy->reset();

        $chunks = iterator_to_array($strategy->process('First sentence. Second sentence! Third?', true));

        $this->assertGreaterThanOrEqual(3, \count($chunks));
        $this->assertStringContainsString('First sentence', $chunks[0]->getText());
    }

    public function testMetadataContainsStrategy(): void
    {
        $strategy = new SentenceChunkingStrategy();
        $strategy->reset();

        $chunks = iterator_to_array($strategy->process('Hello world. Goodbye.', true));

        foreach ($chunks as $chunk) {
            $this->assertEquals('sentence', $chunk->getMetadata()['strategy']);
        }
    }

    public function testResetClearsState(): void
    {
        $strategy = new SentenceChunkingStrategy();
        $strategy->reset();
        iterator_to_array($strategy->process('Hello.', true));

        $strategy->reset();
        $chunks = iterator_to_array($strategy->process('World.', true));

        $this->assertEquals(0, $chunks[0]->getPosition());
    }
}
