<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\PostProcessor;

use Ecourty\TextChunker\PostProcessor\DeduplicationPostProcessor;
use Ecourty\TextChunker\ValueObject\Chunk;
use PHPUnit\Framework\TestCase;

class DeduplicationPostProcessorTest extends TestCase
{
    /** @param Chunk[] $chunks */
    private function makeGenerator(array $chunks): \Generator
    {
        yield from $chunks;
    }

    public function testRemovesDuplicateChunks(): void
    {
        $pp = new DeduplicationPostProcessor();
        $chunks = [
            new Chunk('Hello world', 0),
            new Chunk('Hello world', 1),
            new Chunk('Different', 2),
        ];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertCount(2, $result);
        $this->assertEquals('Hello world', $result[0]->getText());
        $this->assertEquals('Different', $result[1]->getText());
    }

    public function testKeepsUniqueChunks(): void
    {
        $pp = new DeduplicationPostProcessor();
        $chunks = [
            new Chunk('A', 0),
            new Chunk('B', 1),
            new Chunk('C', 2),
        ];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertCount(3, $result);
    }

    public function testAddsContentHashMetadata(): void
    {
        $pp = new DeduplicationPostProcessor();
        $chunks = [new Chunk('Hello', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertArrayHasKey('content_hash', $result[0]->getMetadata());
        $this->assertEquals(md5('Hello'), $result[0]->getMetadata()['content_hash']);
    }

    public function testPreservesPositionAndMetadata(): void
    {
        $pp = new DeduplicationPostProcessor();
        $chunks = [new Chunk('Hello', 7, ['strategy' => 'paragraph'])];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals(7, $result[0]->getPosition());
        $this->assertEquals('paragraph', $result[0]->getMetadata()['strategy']);
    }

    public function testSeenStateResetsBetweenCalls(): void
    {
        $pp = new DeduplicationPostProcessor();

        iterator_to_array($pp->process($this->makeGenerator([new Chunk('Hello', 0)])), false);
        $result = iterator_to_array($pp->process($this->makeGenerator([new Chunk('Hello', 0)])), false);

        $this->assertCount(1, $result);
    }
}
