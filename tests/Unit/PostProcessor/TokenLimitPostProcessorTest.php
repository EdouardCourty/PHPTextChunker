<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\PostProcessor;

use Ecourty\TextChunker\PostProcessor\TokenLimitPostProcessor;
use Ecourty\TextChunker\ValueObject\Chunk;
use PHPUnit\Framework\TestCase;

class TokenLimitPostProcessorTest extends TestCase
{
    /** @param Chunk[] $chunks */
    private function makeGenerator(array $chunks): \Generator
    {
        yield from $chunks;
    }

    public function testPassesThroughSmallChunk(): void
    {
        $pp = new TokenLimitPostProcessor(100);
        $chunk = new Chunk('Hello', 0);

        $result = iterator_to_array($pp->process($this->makeGenerator([$chunk])));

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('estimated_tokens', $result[0]->getMetadata());
    }

    public function testSplitsOversizedChunk(): void
    {
        $pp = new TokenLimitPostProcessor(5, 4); // max 20 chars
        $chunk = new Chunk(str_repeat('A', 50), 0);

        $result = iterator_to_array($pp->process($this->makeGenerator([$chunk])));

        $this->assertGreaterThan(1, \count($result));
        foreach ($result as $r) {
            $this->assertTrue($r->getMetadata()['split_from_original']);
        }
    }

    public function testThrowsOnZeroMaxTokens(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new TokenLimitPostProcessor(0);
    }
}
