<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\Benchmarks;

use Ecourty\TextChunker\Strategy\DialogueChunkingStrategy;
use Ecourty\TextChunker\Strategy\FixedSizeChunkingStrategy;
use Ecourty\TextChunker\Strategy\LineChunkingStrategy;
use Ecourty\TextChunker\Strategy\ParagraphChunkingStrategy;
use Ecourty\TextChunker\Strategy\RecursiveChunkingStrategy;
use Ecourty\TextChunker\Strategy\RegexChunkingStrategy;
use Ecourty\TextChunker\Strategy\SentenceChunkingStrategy;
use Ecourty\TextChunker\Strategy\WordCountChunkingStrategy;
use Ecourty\TextChunker\TextChunker;
use PhpBench\Attributes\BeforeMethods;
use PhpBench\Attributes\Groups;
use PhpBench\Attributes\Iterations;
use PhpBench\Attributes\OutputTimeUnit;
use PhpBench\Attributes\Revs;
use PhpBench\Attributes\Subject;
use PhpBench\Attributes\Timeout;
use PhpBench\Attributes\Warmup;

/**
 * Benchmarks all chunking strategies against large real-world datasets.
 * Metrics: execution time, peak memory, chunk count, throughput (MB/s).
 */
#[Revs(3)]
#[Iterations(3)]
#[Warmup(1)]
#[OutputTimeUnit('milliseconds', precision: 2)]
class StrategyBench
{
    private TextChunker $chunker;

    private int $chunkCount = 0;

    public function setUp(): void
    {
        $this->chunker = new TextChunker();
        $this->chunkCount = 0;
    }

    private function runOnFile(string $file, mixed $strategy): void
    {
        foreach ($this->chunker->setFile($file)->chunk($strategy) as $chunk) {
            ++$this->chunkCount;
        }
    }

    // -------------------------------------------------------------------------
    // Bible KJV (~4.5 MB)
    // -------------------------------------------------------------------------

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['bible', 'paragraph'])]
    public function benchBibleParagraph(): void
    {
        $this->runOnFile(DATASET_BIBLE, new ParagraphChunkingStrategy());
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['bible', 'sentence'])]
    public function benchBibleSentence(): void
    {
        $this->runOnFile(DATASET_BIBLE, new SentenceChunkingStrategy());
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['bible', 'fixed'])]
    public function benchBibleFixedSize(): void
    {
        $this->runOnFile(DATASET_BIBLE, new FixedSizeChunkingStrategy(1000));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['bible', 'word'])]
    public function benchBibleWordCount(): void
    {
        $this->runOnFile(DATASET_BIBLE, new WordCountChunkingStrategy(200));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['bible', 'line'])]
    public function benchBibleLine(): void
    {
        $this->runOnFile(DATASET_BIBLE, new LineChunkingStrategy(10));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['bible', 'regex'])]
    public function benchBibleRegex(): void
    {
        $this->runOnFile(DATASET_BIBLE, new RegexChunkingStrategy('/\n\n+/'));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['bible', 'recursive'])]
    public function benchBibleRecursive(): void
    {
        $this->runOnFile(DATASET_BIBLE, new RecursiveChunkingStrategy(
            [new ParagraphChunkingStrategy(), new SentenceChunkingStrategy()],
            500,
        ));
    }

    // -------------------------------------------------------------------------
    // Les Misérables (~2.6 MB)
    // -------------------------------------------------------------------------

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['miserables', 'paragraph'])]
    public function benchMiserablesParagraph(): void
    {
        $this->runOnFile(DATASET_LES_MISERABLES, new ParagraphChunkingStrategy());
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['miserables', 'sentence'])]
    public function benchMiserablesSentence(): void
    {
        $this->runOnFile(DATASET_LES_MISERABLES, new SentenceChunkingStrategy());
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['miserables', 'fixed'])]
    public function benchMiserablesFixedSize(): void
    {
        $this->runOnFile(DATASET_LES_MISERABLES, new FixedSizeChunkingStrategy(1000));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['miserables', 'word'])]
    public function benchMiserablesWordCount(): void
    {
        $this->runOnFile(DATASET_LES_MISERABLES, new WordCountChunkingStrategy(200));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['miserables', 'line'])]
    public function benchMiserablesLine(): void
    {
        $this->runOnFile(DATASET_LES_MISERABLES, new LineChunkingStrategy(10));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['miserables', 'dialogue'])]
    public function benchMiserablesDialogue(): void
    {
        $this->runOnFile(DATASET_LES_MISERABLES, new DialogueChunkingStrategy(500, 100));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['miserables', 'recursive'])]
    public function benchMiserablesRecursive(): void
    {
        $this->runOnFile(DATASET_LES_MISERABLES, new RecursiveChunkingStrategy(
            [new ParagraphChunkingStrategy(), new SentenceChunkingStrategy()],
            500,
        ));
    }

    // -------------------------------------------------------------------------
    // Encyclopaedia Britannica (~105 MB) — lighter strategies only
    // -------------------------------------------------------------------------

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['britannica', 'paragraph'])]
    #[Timeout(120)]
    public function benchBritannicaParagraph(): void
    {
        $this->runOnFile(DATASET_BRITANNICA, new ParagraphChunkingStrategy());
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['britannica', 'fixed'])]
    #[Timeout(120)]
    public function benchBritannicaFixedSize(): void
    {
        $this->runOnFile(DATASET_BRITANNICA, new FixedSizeChunkingStrategy(1000));
    }

    #[Subject]
    #[BeforeMethods('setUp')]
    #[Groups(['britannica', 'word'])]
    #[Timeout(120)]
    public function benchBritannicaWordCount(): void
    {
        $this->runOnFile(DATASET_BRITANNICA, new WordCountChunkingStrategy(200));
    }
}
