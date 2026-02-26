<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Strategy;

use Ecourty\TextChunker\Strategy\DialogueChunkingStrategy;
use PHPUnit\Framework\TestCase;

class DialogueChunkingStrategyTest extends TestCase
{
    public function testProducesChunks(): void
    {
        $strategy = new DialogueChunkingStrategy(50, 10);
        $strategy->reset();

        $content = implode("\n", [
            'Alice: Hello there!',
            'Bob: Hi! How are you?',
            'Alice: Doing great, thanks!',
            'Bob: That is wonderful to hear.',
        ]);

        $chunks = iterator_to_array($strategy->process($content, true));

        $this->assertNotEmpty($chunks);
    }

    public function testMetadataContainsStrategy(): void
    {
        $strategy = new DialogueChunkingStrategy();
        $strategy->reset();

        $chunks = iterator_to_array($strategy->process("A: Hello.\nB: World.", true));

        foreach ($chunks as $chunk) {
            $this->assertEquals('dialogue', $chunk->getMetadata()['strategy']);
        }
    }

    public function testResetClearsState(): void
    {
        $strategy = new DialogueChunkingStrategy(50, 10);
        $strategy->reset();
        iterator_to_array($strategy->process("Line one.\nLine two.", true));

        $strategy->reset();
        $chunks = iterator_to_array($strategy->process('New content.', true));

        $this->assertEquals(0, $chunks[0]->getPosition());
    }
}
