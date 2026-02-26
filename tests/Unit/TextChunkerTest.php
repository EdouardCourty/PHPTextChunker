<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit;

use Ecourty\TextChunker\PostProcessor\ChunkFilterPostProcessor;
use Ecourty\TextChunker\PostProcessor\TextNormalizationPostProcessor;
use Ecourty\TextChunker\Strategy\ParagraphChunkingStrategy;
use Ecourty\TextChunker\TextChunker;
use PHPUnit\Framework\TestCase;

class TextChunkerTest extends TestCase
{
    private string $testFilePath;

    protected function setUp(): void
    {
        $this->testFilePath = sys_get_temp_dir() . '/test_chunking_' . uniqid() . '.txt';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testFilePath)) {
            unlink($this->testFilePath);
        }
    }

    public function testChunkFromFile(): void
    {
        $content = "First paragraph.\n\nSecond paragraph.\n\nThird.";
        file_put_contents($this->testFilePath, $content);

        $chunks = iterator_to_array(
            (new TextChunker())->setFile($this->testFilePath)->chunk(new ParagraphChunkingStrategy()),
        );

        $this->assertCount(3, $chunks);
        $this->assertStringContainsString('First', $chunks[0]->getText());
    }

    public function testChunkFromText(): void
    {
        $chunks = iterator_to_array(
            (new TextChunker())->setText("Para one.\n\nPara two.")->chunk(new ParagraphChunkingStrategy()),
        );

        $this->assertCount(2, $chunks);
        $this->assertEquals('Para one.', $chunks[0]->getText());
        $this->assertEquals('Para two.', $chunks[1]->getText());
    }

    public function testWithMetadata(): void
    {
        $chunks = iterator_to_array(
            (new TextChunker())
                ->setText('Hello world.')
                ->withMetadata(['source' => 'test'])
                ->chunk(new ParagraphChunkingStrategy()),
        );

        $this->assertArrayHasKey('source', $chunks[0]->getMetadata());
        $this->assertEquals('test', $chunks[0]->getMetadata()['source']);
    }

    public function testStreamingLargeFile(): void
    {
        $content = str_repeat('Paragraph ' . str_repeat('x', 90) . ".\n\n", 10000);
        file_put_contents($this->testFilePath, $content);

        $generator = (new TextChunker())->setFile($this->testFilePath)->chunk(new ParagraphChunkingStrategy());

        $count = 0;
        $memoryBefore = memory_get_usage();

        foreach ($generator as $chunk) {
            ++$count;
            if ($count > 100) {
                break;
            }
        }

        $memoryUsed = (memory_get_usage() - $memoryBefore) / 1024 / 1024;

        $this->assertGreaterThan(100, $count);
        $this->assertLessThan(50, $memoryUsed, 'Memory usage should be < 50MB for streaming');
    }

    public function testSetFileThrowsOnMissingFile(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new TextChunker())->setFile('/non/existent/file.txt');
    }

    public function testChunkThrowsWhenNoSource(): void
    {
        $this->expectException(\RuntimeException::class);
        iterator_to_array((new TextChunker())->chunk(new ParagraphChunkingStrategy()));
    }

    public function testSetTextOverridesFile(): void
    {
        file_put_contents($this->testFilePath, 'file content');

        $chunker = (new TextChunker())
            ->setFile($this->testFilePath)
            ->setText("text content.\n\nSecond.");

        $chunks = iterator_to_array($chunker->chunk(new ParagraphChunkingStrategy()));

        $this->assertCount(2, $chunks);
        $this->assertStringContainsString('text content', $chunks[0]->getText());
    }

    public function testWithPostProcessorsAppliesAll(): void
    {
        $chunks = iterator_to_array(
            (new TextChunker())
                ->setText("Hello   world.\n\n\n\nHello   world.")
                ->withPostProcessors(
                    new TextNormalizationPostProcessor(),
                    new ChunkFilterPostProcessor(minLength: 1),
                )
                ->chunk(new ParagraphChunkingStrategy()),
            false,
        );

        // Normalization collapses whitespace; both paragraphs yield the same text
        $this->assertCount(2, $chunks);
        $this->assertEquals('Hello world.', $chunks[0]->getText());
    }

    public function testWithPostProcessorsIsChainable(): void
    {
        $chunker = (new TextChunker())
            ->setText('Hello.')
            ->withPostProcessors(new TextNormalizationPostProcessor())
            ->withPostProcessor(new ChunkFilterPostProcessor(minLength: 1));

        $chunks = iterator_to_array($chunker->chunk(new ParagraphChunkingStrategy()), false);

        $this->assertCount(1, $chunks);
    }
}
