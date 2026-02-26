# php-text-chunker

[![PHP CI](https://github.com/EdouardCourty/PHPTextChunker/actions/workflows/ci.yml/badge.svg)](https://github.com/EdouardCourty/PHPTextChunker/actions/workflows/ci.yml)

A framework-agnostic PHP library for splitting text and files into meaningful chunks, using pluggable strategies and a composable post-processing pipeline.

## Table of Contents

- [Installation](#installation)
- [Core Features](#core-features)
- [Quick Start](#quick-start)
- [Chunking Strategies](#chunking-strategies)
- [Post-Processors](#post-processors)
- [Configuration Reference](#configuration-reference)
- [Performance](#performance)
- [Datasets](#datasets)
- [Development](#development)

---

## Installation

```bash
composer require ecourty/text-chunker
```

**Requirements**: PHP >= 8.3

---

## Core Features

- **9 built-in strategies**: paragraph, sentence, fixed-size, dialogue, markdown, word count, regex, line, recursive
- **8 built-in post-processors**: overlap, token limit, metadata enrichment, filtering, chunk merger, text normalization, deduplication, regex replace
- **Streaming architecture**: processes large files in 8KB buffers — minimal memory usage
- **Works with files and strings**: `setFile()` or `setText()`
- **Fully extensible**: implement your own strategies and post-processors
- **Zero framework dependencies**

---

## Quick Start

```php
use Ecourty\TextChunker\TextChunker;
use Ecourty\TextChunker\Strategy\ParagraphChunkingStrategy;

$chunker = new TextChunker();

foreach ($chunker->setFile('document.txt')->chunk(new ParagraphChunkingStrategy()) as $chunk) {
    echo $chunk->getText();       // chunk content
    echo $chunk->getPosition();   // index in the sequence
    print_r($chunk->getMetadata()); // strategy, length, etc.
}
```

Chunk from a string:

```php
$chunker = new TextChunker();

foreach ($chunker->setText($myText)->chunk(new SentenceChunkingStrategy()) as $chunk) {
    // ...
}
```

---

## Chunking Strategies

| Strategy                    | Splits on                                  | Key options                                                     |
|-----------------------------|--------------------------------------------|-----------------------------------------------------------------|
| `ParagraphChunkingStrategy` | Double newlines (`\n\n`)                   | —                                                               |
| `SentenceChunkingStrategy`  | Sentence-ending punctuation (`. ! ?`)      | —                                                               |
| `FixedSizeChunkingStrategy` | Fixed character count                      | `chunkSize` (default: 1000)                                     |
| `DialogueChunkingStrategy`  | Dialogue lines, context-aware grouping     | `targetChunkSize`, `minChunkSize`                               |
| `MarkdownChunkingStrategy`  | Markdown headers (`#` to `######`)         | `minHeadingLevel`, `maxHeadingLevel`                            |
| `WordCountChunkingStrategy` | Fixed word count, respects word boundaries | `wordCount` (default: 200)                                      |
| `RegexChunkingStrategy`     | Configurable regex pattern                 | `pattern`, `delimiterPosition` (`None` \| `Prefix` \| `Suffix`) |
| `LineChunkingStrategy`      | N consecutive lines per chunk              | `linesPerChunk` (default: 10)                                   |
| `RecursiveChunkingStrategy` | Cascade of strategies with a size limit    | `strategies[]`, `maxChunkSize`                                  |

`RecursiveChunkingStrategy` applies `strategies[0]` to the stream, and immediately re-splits any chunk exceeding `maxChunkSize` using `strategies[1]`, then `strategies[2]`, etc. Streaming-safe — never buffers more than one chunk at a time.

---

## Post-Processors

Post-processors are applied in sequence after chunking. Chain them with `withPostProcessor()`.

| Post-processor                   | Description                                                                   | Key options                                            |
|----------------------------------|-------------------------------------------------------------------------------|--------------------------------------------------------|
| `OverlappingChunkPostProcessor`  | Prepends the tail of the previous chunk for context continuity                | `overlapSize` (default: 200)                           |
| `TokenLimitPostProcessor`        | Splits chunks exceeding a token budget                                        | `maxTokens`, `charactersPerToken`                      |
| `MetadataEnricherPostProcessor`  | Adds `chunk_index`, `total_chunks`, `word_count`, `char_count`, `source`      | —                                                      |
| `ChunkFilterPostProcessor`       | Removes empty or too-short chunks                                             | `minLength`, `removeEmpty`                             |
| `ChunkMergerPostProcessor`       | Merges consecutive small chunks until `minChunkSize` is reached               | `minChunkSize` (default: 200), `separator`             |
| `TextNormalizationPostProcessor` | Collapses whitespace, trims lines, strips control characters                  | `collapseWhitespace`, `trimLines`, `stripControlChars` |
| `DeduplicationPostProcessor`     | Removes duplicate chunks by md5 content hash; adds `content_hash` metadata    | —                                                      |
| `RegexReplacePostProcessor`      | Applies ordered `[pattern => replacement]` substitutions to each chunk's text | `replacements[]`                                       |

---

## Configuration Reference

### TextChunker

| Method                             | Description                                     |
|------------------------------------|-------------------------------------------------|
| `setFile(string $path)`            | Set source file (streamed)                      |
| `setText(string $text)`            | Set source string                               |
| `withMetadata(array $meta)`        | Attach global metadata to every chunk           |
| `withPostProcessor(...)`           | Add a post-processor to the pipeline            |
| `withPostProcessors(...)`          | Add multiple post-processors at once (variadic) |
| `chunk(ChunkingStrategyInterface)` | Returns a `Generator<Chunk>`                    |

### Chunk

| Method                | Returns                          |
|-----------------------|----------------------------------|
| `getText()`           | `string` — the chunk content     |
| `getPosition()`       | `int` — index in the sequence    |
| `getMetadata()`       | `array` — associated metadata    |
| `getLength()`         | `int` — character count          |
| `withMetadata(array)` | New `Chunk` with merged metadata |

---

## Performance

Benchmarked with PHPBench on real-world datasets (Bible KJV, Les Misérables, Encyclopaedia Britannica 11th Ed.). See [BENCHMARKS.md](BENCHMARKS.md) for the full results.

**Strategy throughput** (Bible KJV, 4.26 MB):

| Strategy                    | Time   | Throughput |
|-----------------------------|--------|------------|
| `SentenceChunkingStrategy`  | 43 ms  | ~98 MB/s   |
| `FixedSizeChunkingStrategy` | 44 ms  | ~98 MB/s   |
| `LineChunkingStrategy`      | 46 ms  | ~93 MB/s   |
| `ParagraphChunkingStrategy` | 293 ms | ~15 MB/s   |
| `WordCountChunkingStrategy` | 377 ms | ~11 MB/s   |

**Post-processor overhead** (50 KB excerpt): all 8 processors run in **< 3 ms**. Chain freely.

The library is **streaming-first** — most strategies hold only ~2 MB in memory regardless of input file size.

---

## Datasets

The `datasets/` directory contains large text corpora used for benchmarking chunking strategies. All texts are public domain sourced from [Project Gutenberg](https://www.gutenberg.org/).

| File                 | Source                                                                                                                 | Size    | Notes                                               |
|----------------------|------------------------------------------------------------------------------------------------------------------------|---------|-----------------------------------------------------|
| `bible_kjv.txt`      | [King James Bible (PG #10)](https://www.gutenberg.org/ebooks/10)                                                       | ~4.5 MB | Great for sentence and paragraph benchmarks         |
| `les_miserables.txt` | [Les Misérables by Victor Hugo (PG #17489–17496)](https://www.gutenberg.org/ebooks/17489)                              | ~2.6 MB | All 5 tomes in French, ideal for paragraph chunking |
| `britannica/`        | [Encyclopaedia Britannica, 11th Edition](https://www.gutenberg.org/ebooks/search/?query=encyclopaedia+britannica+11th) | ~118 MB | 92 volumes of dense encyclopaedic text              |

> Headers and Project Gutenberg license preambles can be stripped before benchmarking to work with clean content only.

---

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Run PHPStan (level max)
composer phpstan

# Run CS fixer
composer cs-fix

# Run all checks
composer qa
```

### Extending the library

Implement `ChunkingStrategyInterface` to create a custom strategy, or `ChunkPostProcessorInterface` for a custom post-processor. See `AGENTS.md` for detailed guidelines.
