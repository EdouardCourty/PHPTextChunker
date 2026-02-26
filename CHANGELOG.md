# php-text-chunker Changelog

This file contains information about every addition, update and deletion in the `ecourty/text-chunker` library.  
It is recommended to read this file before updating the library to a new version.

## v1.0.0

Initial release of the library.

#### Additions

- Added [`TextChunker`](./src/TextChunker.php) as the main entry point
  - Fluent interface: `setFile()`, `setText()`, `withMetadata()`, `withPostProcessor()`
  - Accepts both file paths and raw strings as input
  - Returns a memory-efficient `Generator` of [`Chunk`](./src/ValueObject/Chunk.php) objects

- Added [`Chunk`](./src/ValueObject/Chunk.php) immutable value object
  - Holds `text`, `position`, and `metadata`
  - `withMetadata()` returns a new instance (immutable)

- Added [`ChunkingStrategyInterface`](./src/Contract/ChunkingStrategyInterface.php) and [`ChunkPostProcessorInterface`](./src/Contract/ChunkPostProcessorInterface.php) contracts

- Added 9 built-in chunking strategies:
  - [`ParagraphChunkingStrategy`](./src/Strategy/ParagraphChunkingStrategy.php) — splits on double newlines (`\n\n`)
  - [`SentenceChunkingStrategy`](./src/Strategy/SentenceChunkingStrategy.php) — splits on sentence-ending punctuation
  - [`FixedSizeChunkingStrategy`](./src/Strategy/FixedSizeChunkingStrategy.php) — splits on fixed character count
  - [`MarkdownChunkingStrategy`](./src/Strategy/MarkdownChunkingStrategy.php) — splits on Markdown headers (`#` to `######`)
  - [`WordCountChunkingStrategy`](./src/Strategy/WordCountChunkingStrategy.php) — splits on fixed word count
  - [`RegexChunkingStrategy`](./src/Strategy/RegexChunkingStrategy.php) — splits on a configurable regex pattern
  - [`LineChunkingStrategy`](./src/Strategy/LineChunkingStrategy.php) — splits into groups of N consecutive lines
  - [`DialogueChunkingStrategy`](./src/Strategy/DialogueChunkingStrategy.php) — splits dialogue transcripts by speaker turns
  - [`RecursiveChunkingStrategy`](./src/Strategy/RecursiveChunkingStrategy.php) — cascades multiple strategies with a max chunk size

- Added 8 built-in post-processors:
  - [`OverlappingChunkPostProcessor`](./src/PostProcessor/OverlappingChunkPostProcessor.php) — prepends tail of previous chunk for context continuity
  - [`TokenLimitPostProcessor`](./src/PostProcessor/TokenLimitPostProcessor.php) — splits oversized chunks to respect a token budget
  - [`MetadataEnricherPostProcessor`](./src/PostProcessor/MetadataEnricherPostProcessor.php) — enriches chunks with word count, char count, index, total, and source
  - [`ChunkFilterPostProcessor`](./src/PostProcessor/ChunkFilterPostProcessor.php) — removes empty or too-short chunks
  - [`ChunkMergerPostProcessor`](./src/PostProcessor/ChunkMergerPostProcessor.php) — merges consecutive small chunks up to a minimum size
  - [`TextNormalizationPostProcessor`](./src/PostProcessor/TextNormalizationPostProcessor.php) — cleans whitespace, control characters, and trims lines
  - [`DeduplicationPostProcessor`](./src/PostProcessor/DeduplicationPostProcessor.php) — removes duplicate chunks by content hash
  - [`RegexReplacePostProcessor`](./src/PostProcessor/RegexReplacePostProcessor.php) — applies regex substitutions on each chunk's text

- Added unit tests for all strategies and post-processors under [`tests/Unit/`](./tests/Unit/)
- Added benchmarks under [`benchmarks/`](./benchmarks/) using [phpbench](https://github.com/phpbench/phpbench)
