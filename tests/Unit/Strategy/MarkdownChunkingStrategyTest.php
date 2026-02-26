<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Strategy;

use Ecourty\TextChunker\Strategy\MarkdownChunkingStrategy;
use PHPUnit\Framework\TestCase;

class MarkdownChunkingStrategyTest extends TestCase
{
    public function testSplitsOnH1Headers(): void
    {
        $strategy = new MarkdownChunkingStrategy();
        $input = "# Title One\nContent one.\n\n# Title Two\nContent two.";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertStringContainsString('Title One', $chunks[0]->getText());
        $this->assertStringContainsString('Content one', $chunks[0]->getText());
        $this->assertStringContainsString('Title Two', $chunks[1]->getText());
    }

    public function testMetadataContainsHeadingLevelAndText(): void
    {
        $strategy = new MarkdownChunkingStrategy();
        $input = "## Section A\nSome content.";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(1, $chunks);
        $this->assertEquals('markdown', $chunks[0]->getMetadata()['strategy']);
        $this->assertEquals(2, $chunks[0]->getMetadata()['heading_level']);
        $this->assertEquals('Section A', $chunks[0]->getMetadata()['heading_text']);
    }

    public function testContentBeforeFirstHeadingIsYieldedSeparately(): void
    {
        $strategy = new MarkdownChunkingStrategy();
        $input = "Intro text.\n\n# Title\nContent.";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertCount(2, $chunks);
        $this->assertEquals('Intro text.', $chunks[0]->getText());
        $this->assertNull($chunks[0]->getMetadata()['heading_level']);
    }

    public function testMinHeadingLevelFiltersLowerHeaders(): void
    {
        $strategy = new MarkdownChunkingStrategy(minHeadingLevel: 2);
        $input = "# H1 ignored\nContent A.\n\n## H2 kept\nContent B.";

        $chunks = iterator_to_array($strategy->process($input, true));

        // H1 content becomes pre-heading content, H2 is a proper section
        $texts = array_map(fn ($c) => $c->getText(), $chunks);
        $this->assertCount(2, $chunks);

        $headingLevels = array_filter(array_map(fn ($c) => $c->getMetadata()['heading_level'], $chunks));
        $this->assertContains(2, $headingLevels);
        $this->assertNotContains(1, $headingLevels);
    }

    public function testMaxHeadingLevelFiltersHigherHeaders(): void
    {
        $strategy = new MarkdownChunkingStrategy(maxHeadingLevel: 2);
        $input = "# H1\nContent A.\n\n## H2\nContent B.\n\n### H3 not split\nContent C.";

        $chunks = iterator_to_array($strategy->process($input, true));

        $levels = array_filter(array_map(fn ($c) => $c->getMetadata()['heading_level'], $chunks));
        $this->assertNotContains(3, $levels);
    }

    public function testPositionIncrement(): void
    {
        $strategy = new MarkdownChunkingStrategy();
        $input = "# A\nContent A.\n\n# B\nContent B.\n\n# C\nContent C.";

        $chunks = iterator_to_array($strategy->process($input, true));

        $this->assertEquals(0, $chunks[0]->getPosition());
        $this->assertEquals(1, $chunks[1]->getPosition());
        $this->assertEquals(2, $chunks[2]->getPosition());
    }

    public function testResetClearsState(): void
    {
        $strategy = new MarkdownChunkingStrategy();
        iterator_to_array($strategy->process("# A\nContent A.", true));

        $strategy->reset();
        $chunks = iterator_to_array($strategy->process("# B\nContent B.", true));

        $this->assertEquals(0, $chunks[0]->getPosition());
    }

    public function testInvalidMinHeadingLevelThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MarkdownChunkingStrategy(minHeadingLevel: 0);
    }

    public function testInvalidHeadingLevelRangeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new MarkdownChunkingStrategy(minHeadingLevel: 3, maxHeadingLevel: 2);
    }
}
