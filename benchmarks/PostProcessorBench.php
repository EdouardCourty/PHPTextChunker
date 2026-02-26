<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Benchmarks;

use Ecourty\TextChunker\Contract\ChunkPostProcessorInterface;
use Ecourty\TextChunker\PostProcessor\ChunkFilterPostProcessor;
use Ecourty\TextChunker\PostProcessor\ChunkMergerPostProcessor;
use Ecourty\TextChunker\PostProcessor\DeduplicationPostProcessor;
use Ecourty\TextChunker\PostProcessor\MetadataEnricherPostProcessor;
use Ecourty\TextChunker\PostProcessor\OverlappingChunkPostProcessor;
use Ecourty\TextChunker\PostProcessor\RegexReplacePostProcessor;
use Ecourty\TextChunker\PostProcessor\TextNormalizationPostProcessor;
use Ecourty\TextChunker\PostProcessor\TokenLimitPostProcessor;
use Ecourty\TextChunker\Strategy\ParagraphChunkingStrategy;
use Ecourty\TextChunker\TextChunker;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use PhpBench\Attributes\Warmup;

/**
 * Benchmarks all post-processors in isolation on a ~50 KB text excerpt.
 *
 * The base chunking (ParagraphChunkingStrategy on the Bible excerpt) is
 * performed inside each subject so that the full pipeline cost is measured.
 * The excerpt is loaded once in setUp() and reused across revisions.
 */
#[Revs(5)]
#[Iterations(3)]
#[Warmup(1)]
#[OutputTimeUnit('milliseconds', precision: 2)]
class PostProcessorBench
{
    private string $excerpt = '';

    private int $chunkCount = 0;

    public function setUp(): void
    {
        $fp = fopen(DATASET_BIBLE, 'r');
        $this->excerpt = fread($fp, 50 * 1024);
        fclose($fp);
        $this->chunkCount = 0;
    }

    private function runWithProcessor(ChunkPostProcessorInterface $processor): void
    {
        $chunker = (new TextChunker())
            ->setText($this->excerpt)
            ->withPostProcessor($processor);

        foreach ($chunker->chunk(new ParagraphChunkingStrategy()) as $chunk) {
            ++$this->chunkCount;
        }
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['postprocessor', 'overlap'])]
    public function benchOverlap(): void
    {
        $this->runWithProcessor(new OverlappingChunkPostProcessor(200));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['postprocessor', 'token-limit'])]
    public function benchTokenLimit(): void
    {
        $this->runWithProcessor(new TokenLimitPostProcessor(256, 4));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['postprocessor', 'metadata'])]
    public function benchMetadataEnricher(): void
    {
        $this->runWithProcessor(new MetadataEnricherPostProcessor());
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['postprocessor', 'filter'])]
    public function benchChunkFilter(): void
    {
        $this->runWithProcessor(new ChunkFilterPostProcessor(50, true));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['postprocessor', 'merger'])]
    public function benchChunkMerger(): void
    {
        $this->runWithProcessor(new ChunkMergerPostProcessor(200));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['postprocessor', 'normalization'])]
    public function benchTextNormalization(): void
    {
        $this->runWithProcessor(new TextNormalizationPostProcessor());
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['postprocessor', 'deduplication'])]
    public function benchDeduplication(): void
    {
        $this->runWithProcessor(new DeduplicationPostProcessor());
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['postprocessor', 'regex-replace'])]
    public function benchRegexReplace(): void
    {
        $this->runWithProcessor(new RegexReplacePostProcessor([
            '/\bLORD\b/' => 'Lord',
            '/\s{2,}/' => ' ',
        ]));
    }
}
