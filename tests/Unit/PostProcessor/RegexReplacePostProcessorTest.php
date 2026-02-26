<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\PostProcessor;

use Ecourty\TextChunker\PostProcessor\RegexReplacePostProcessor;
use Ecourty\TextChunker\ValueObject\Chunk;
use PHPUnit\Framework\TestCase;

class RegexReplacePostProcessorTest extends TestCase
{
    /** @param Chunk[] $chunks */
    private function makeGenerator(array $chunks): \Generator
    {
        yield from $chunks;
    }

    public function testAppliesSingleReplacement(): void
    {
        $pp = new RegexReplacePostProcessor(['/\d+/' => '#']);
        $chunks = [new Chunk('Call 1234 now', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals('Call # now', $result[0]->getText());
    }

    public function testAppliesMultipleReplacementsInOrder(): void
    {
        $pp = new RegexReplacePostProcessor([
            '/https?:\/\/\S+/' => '[URL]',
            '/\b[\w.]+@[\w.]+\b/' => '[EMAIL]',
        ]);
        $chunks = [new Chunk('Visit https://example.com or mail me@test.com', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertStringContainsString('[URL]', $result[0]->getText());
        $this->assertStringContainsString('[EMAIL]', $result[0]->getText());
    }

    public function testPreservesPositionAndMetadata(): void
    {
        $pp = new RegexReplacePostProcessor(['/foo/' => 'bar']);
        $chunks = [new Chunk('foo', 5, ['strategy' => 'line'])];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals(5, $result[0]->getPosition());
        $this->assertEquals('line', $result[0]->getMetadata()['strategy']);
    }

    public function testNoMatchLeavesTextUnchanged(): void
    {
        $pp = new RegexReplacePostProcessor(['/xyz/' => 'replaced']);
        $chunks = [new Chunk('Hello world', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals('Hello world', $result[0]->getText());
    }

    public function testInvalidPatternThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new RegexReplacePostProcessor(['not_a_valid_pattern' => 'x']);
    }

    public function testEmptyReplacementsPassesThrough(): void
    {
        $pp = new RegexReplacePostProcessor([]);
        $chunks = [new Chunk('Hello', 0)];

        $result = iterator_to_array($pp->process($this->makeGenerator($chunks)), false);

        $this->assertEquals('Hello', $result[0]->getText());
    }
}
