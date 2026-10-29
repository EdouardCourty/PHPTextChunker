<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Tests\Functional;

use Ecourty\TextChunker\Contract\ChunkingStrategyInterface;
use Ecourty\TextChunker\Contract\ReaderInterface;
use Ecourty\TextChunker\Strategy\DialogueChunkingStrategy;
use Ecourty\TextChunker\Strategy\FixedSizeChunkingStrategy;
use Ecourty\TextChunker\Strategy\HtmlChunkingStrategy;
use Ecourty\TextChunker\Strategy\LineChunkingStrategy;
use Ecourty\TextChunker\Strategy\MarkdownChunkingStrategy;
use Ecourty\TextChunker\Strategy\ParagraphChunkingStrategy;
use Ecourty\TextChunker\Strategy\RecursiveChunkingStrategy;
use Ecourty\TextChunker\Strategy\RegexChunkingStrategy;
use Ecourty\TextChunker\Strategy\SentenceChunkingStrategy;
use Ecourty\TextChunker\Strategy\WordCountChunkingStrategy;
use Ecourty\TextChunker\TextChunker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A strategy must produce the same chunks whether the input arrives as one string,
 * as a file read with the default buffer, or as a stream of arbitrarily small fragments.
 */
class InputModeConsistencyTest extends TestCase
{
    private const string DATASETS_DIR = __DIR__ . '/../../datasets';

    /**
     * @param \Closure(): ChunkingStrategyInterface $strategyFactory
     */
    #[DataProvider('provideStrategyAndInput')]
    public function testFileInputMatchesTextInput(\Closure $strategyFactory, string $input, ?int $fragmentSize): void
    {
        $expected = $this->collect((new TextChunker())->setText($input), $strategyFactory());

        $path = $this->writeTempFile($input);

        try {
            $chunker = (new TextChunker())->setFile($path);
            if ($fragmentSize !== null) {
                $chunker->withReader(new FragmentReader($fragmentSize));
            }

            $actual = $this->collect($chunker, $strategyFactory());
        } finally {
            unlink($path);
        }

        $this->assertNotSame([], $expected, 'Test input produced no chunk, it does not exercise the strategy.');
        // Compared by hand: PHPUnit's diff of two large arrays is too slow to be usable
        $this->assertCount(\count($expected), $actual, 'Number of chunks differs.');
        foreach ($expected as $index => $chunk) {
            $this->assertSame(
                $chunk,
                $actual[$index],
                \sprintf("Chunk #%d differs.\nExpected: %s\nActual:   %s", $index, json_encode($chunk, \JSON_UNESCAPED_UNICODE), json_encode($actual[$index], \JSON_UNESCAPED_UNICODE)),
            );
        }
    }

    /**
     * @return \Generator<string, array{\Closure(): ChunkingStrategyInterface, string, ?int}>
     */
    public static function provideStrategyAndInput(): \Generator
    {
        $textInputs = [
            'prose' => [self::syntheticProse(), [null, 1, 7, 251]],
            'markdown' => [self::syntheticMarkdown(), [null, 1, 7, 251]],
            'les_miserables' => [mb_strcut((string) file_get_contents(self::DATASETS_DIR . '/les_miserables.txt'), 0, 120_000), [null, 1021]],
        ];

        $htmlInputs = [
            'html' => [self::syntheticHtml(), [null, 1, 7, 251]],
            'wikipedia_eiffel_tower' => [(string) file_get_contents(self::DATASETS_DIR . '/html/wikipedia_eiffel_tower_fr.html'), [null, 1021]],
        ];

        $textStrategies = [
            'paragraph' => static fn () => new ParagraphChunkingStrategy(),
            'sentence' => static fn () => new SentenceChunkingStrategy(),
            'fixed_size' => static fn () => new FixedSizeChunkingStrategy(100),
            'dialogue' => static fn () => new DialogueChunkingStrategy(),
            'word_count' => static fn () => new WordCountChunkingStrategy(20),
            'line' => static fn () => new LineChunkingStrategy(3),
            'markdown' => static fn () => new MarkdownChunkingStrategy(),
            'markdown_h2_h3' => static fn () => new MarkdownChunkingStrategy(2, 3),
            'regex' => static fn () => new RegexChunkingStrategy('/\n{2,}/'),
            'recursive' => static fn () => new RecursiveChunkingStrategy([new ParagraphChunkingStrategy(), new SentenceChunkingStrategy()], 300),
        ];

        $htmlStrategies = [
            'html_tags' => static fn () => new HtmlChunkingStrategy(),
            'html_tags_stripped' => static fn () => new HtmlChunkingStrategy(['h2', 'h3'], stripTags: true),
            'html_xpath' => static fn () => new HtmlChunkingStrategy(selector: '//h2'),
        ];

        yield from self::combine($textStrategies, $textInputs);
        yield from self::combine($htmlStrategies, $htmlInputs);
    }

    /**
     * @param array<string, \Closure(): ChunkingStrategyInterface> $strategies
     * @param array<string, array{string, list<?int>}>             $inputs
     *
     * @return \Generator<string, array{\Closure(): ChunkingStrategyInterface, string, ?int}>
     */
    private static function combine(array $strategies, array $inputs): \Generator
    {
        foreach ($strategies as $strategyName => $factory) {
            foreach ($inputs as $inputName => [$input, $fragmentSizes]) {
                foreach ($fragmentSizes as $fragmentSize) {
                    $mode = $fragmentSize === null ? 'file' : \sprintf('fragments of %d bytes', $fragmentSize);

                    yield \sprintf('%s / %s / %s', $strategyName, $inputName, $mode) => [$factory, $input, $fragmentSize];
                }
            }
        }
    }

    /**
     * @return list<array{text: string, position: int, metadata: array<string, mixed>}>
     */
    private function collect(TextChunker $chunker, ChunkingStrategyInterface $strategy): array
    {
        $chunks = [];

        foreach ($chunker->chunk($strategy) as $chunk) {
            $chunks[] = [
                'text' => $chunk->getText(),
                'position' => $chunk->getPosition(),
                'metadata' => $chunk->getMetadata(),
            ];
        }

        return $chunks;
    }

    private function writeTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'chunker_');
        $this->assertNotFalse($path);
        file_put_contents($path, $content);

        return $path;
    }

    private static function syntheticProse(): string
    {
        $block = "Été à côté — «naïve» 日本語のテキスト. Another sentence here! Really? Yes.\n"
            . "- Hello, who is there?\n- It is me, the friend from Zürich.\n\n"
            . "Un deuxième paragraphe, avec des accents éàüç. Et une phrase de plus!\n\n";

        return str_repeat($block, 60);
    }

    private static function syntheticMarkdown(): string
    {
        $document = "Intro avant tout titre.\n\n";
        for ($i = 1; $i <= 12; ++$i) {
            $document .= \sprintf("# Titre %d é\n\n%s\n\n", $i, str_repeat('Contenu éàü 日本. ', 8 * $i));
            $document .= \sprintf("## Sous-titre %d\n\n%s\n\n", $i, str_repeat('Ça «va» bien. ', 5 * $i));
            $document .= \sprintf("### Détail %d\n\nCourt.\n\n", $i);
        }

        return $document;
    }

    private static function syntheticHtml(): string
    {
        $document = "<html><body>\n<p>Intro é</p>\n";
        for ($i = 1; $i <= 12; ++$i) {
            $document .= \sprintf("<h2 id=\"s%1\$d\">Section %1\$d é</h2>\n<p>%2\$s</p>\n", $i, str_repeat('Texte «accentué» 日本. ', 6 * $i));
            $document .= \sprintf("<h3>Détail %d</h3>\n<p>Court <b>gras</b>.</p>\n<!-- commentaire %d -->\n", $i, $i);
        }

        return $document . "</body></html>\n";
    }
}

/**
 * @internal Reader yielding the source in fixed-size byte fragments, ignoring the requested buffer size
 */
final readonly class FragmentReader implements ReaderInterface
{
    public function __construct(private int $fragmentSize)
    {
    }

    public function readChunks(string $path, int $bufferSize): \Generator
    {
        yield from str_split((string) file_get_contents($path), max(1, $this->fragmentSize));
    }
}
