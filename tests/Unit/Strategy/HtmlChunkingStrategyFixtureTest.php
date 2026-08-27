<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Unit\Strategy;

use Ecourty\TextChunker\Strategy\HtmlChunkingStrategy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class HtmlChunkingStrategyFixtureTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideFixtures(): array
    {
        $baseDir = \dirname(__DIR__, 3) . '/datasets/html/';

        return [
            'wikipedia_php_en' => [$baseDir . 'wikipedia_php_en.html', 'History'],
            'wikipedia_paris_fr' => [$baseDir . 'wikipedia_paris_fr.html', 'Histoire'],
            'wikipedia_eiffel_tower_fr' => [$baseDir . 'wikipedia_eiffel_tower_fr.html', 'Historique'],
        ];
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function provideFixturePaths(): array
    {
        return array_map(
            static fn (array $dataSet): array => [$dataSet[0]],
            self::provideFixtures(),
        );
    }

    #[DataProvider('provideFixtures')]
    public function testTagsModeProducesMeaningfulChunks(string $fixturePath, string $expectedHeading): void
    {
        $strategy = new HtmlChunkingStrategy();
        $html = (string) file_get_contents($fixturePath);

        $chunks = iterator_to_array($strategy->process($html, true));

        $this->assertGreaterThan(10, \count($chunks));

        foreach ($chunks as $index => $chunk) {
            $metadata = $chunk->getMetadata();

            $this->assertNotSame('', $chunk->getText(), "Chunk $index must not be empty");
            $this->assertSame('html', $metadata['strategy']);
            $this->assertSame(mb_strlen($chunk->getText()), $metadata['length']);
            $this->assertSame($index, $chunk->getPosition());
        }

        $taggedChunks = array_filter(
            $chunks,
            fn ($c) => \is_string($c->getMetadata()['tag']) && \in_array($c->getMetadata()['tag'], ['h1', 'h2', 'h3'], true),
        );
        $this->assertNotEmpty($taggedChunks, 'At least one chunk must start with a heading');

        $headingIds = array_map(
            fn ($c) => \is_array($c->getMetadata()['attributes']) ? ($c->getMetadata()['attributes']['id'] ?? null) : null,
            $taggedChunks,
        );
        $this->assertContains($expectedHeading, $headingIds, "The \"$expectedHeading\" section heading must be found");
    }

    #[DataProvider('provideFixturePaths')]
    public function testStripTagsModeRemovesAllMarkup(string $fixturePath): void
    {
        $strategy = new HtmlChunkingStrategy(stripTags: true);
        $html = (string) file_get_contents($fixturePath);

        $chunks = iterator_to_array($strategy->process($html, true));

        $this->assertNotEmpty($chunks);

        foreach ($chunks as $chunk) {
            $this->assertStringNotContainsString('<script', $chunk->getText());
            $this->assertStringNotContainsString('</p>', $chunk->getText());
            $this->assertStringNotContainsString('<h2', $chunk->getText());
        }
    }

    #[DataProvider('provideFixturePaths')]
    public function testXPathModeExtractsHeadings(string $fixturePath): void
    {
        $strategy = new HtmlChunkingStrategy(selector: '//h2');
        $html = (string) file_get_contents($fixturePath);

        $chunks = iterator_to_array($strategy->process($html, true));

        $this->assertGreaterThan(5, \count($chunks));

        foreach ($chunks as $chunk) {
            $this->assertSame('h2', $chunk->getMetadata()['tag']);
            $this->assertStringStartsWith('<h2', $chunk->getText());
        }
    }

    public function testXPathModeExtractsSectionsByClass(): void
    {
        $strategy = new HtmlChunkingStrategy(selector: "//div[contains(@class, 'mw-heading')]");
        $html = (string) file_get_contents(\dirname(__DIR__, 3) . '/datasets/html/wikipedia_php_en.html');

        $chunks = iterator_to_array($strategy->process($html, true));

        $this->assertNotEmpty($chunks);
        $this->assertSame('div', $chunks[0]->getMetadata()['tag']);
    }

    public function testTextChunkerIntegrationWithFixture(): void
    {
        $chunker = new \Ecourty\TextChunker\TextChunker();
        $chunks = iterator_to_array(
            $chunker
                ->setFile(\dirname(__DIR__, 3) . '/datasets/html/wikipedia_php_en.html')
                ->withMetadata(['source' => 'wikipedia'])
                ->chunk(new HtmlChunkingStrategy()),
        );

        $this->assertNotEmpty($chunks);
        $this->assertSame('wikipedia', $chunks[0]->getMetadata()['source']);
    }
}
