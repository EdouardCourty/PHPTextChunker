# Benchmarks

Benchmarks run with [PHPBench](https://phpbench.readthedocs.io/) — 3 iterations × 3 revs, 1 warmup, no Xdebug, no OPcache.

```
PHP 8.4.7 | PHPBench 1.4.3
```

To reproduce:

```bash
composer bench
```

---

## Datasets

| Dataset                                      | File                                         | Size     |
|----------------------------------------------|----------------------------------------------|----------|
| King James Bible                             | `datasets/bible_kjv.txt`                     | 4.26 MB  |
| Les Misérables (5 tomes, FR)                 | `datasets/les_miserables.txt`                | 2.54 MB  |
| Encyclopaedia Britannica 11th Ed. (92 vols.) | `datasets/encyclopaedia_britannica_11th.txt` | 100.8 MB |

All texts are public domain, sourced from [Project Gutenberg](https://www.gutenberg.org/). PG headers and footers were stripped before benchmarking.

---

## Chunking Strategies

Throughput is computed as `file_size / time_avg`. Memory is the peak RSS reported by PHPBench.

### Bible KJV (4.26 MB)

| Strategy                          | Time (ms) | Throughput | Peak memory |
|-----------------------------------|-----------|------------|-------------|
| `FixedSizeChunkingStrategy(1000)` | 43.7 ms   | 97.6 MB/s  | 1.9 MB      |
| `LineChunkingStrategy(10)`        | 45.6 ms   | 93.3 MB/s  | 1.9 MB      |
| `SentenceChunkingStrategy`        | 43.4 ms   | 98.1 MB/s  | 1.9 MB      |
| `RegexChunkingStrategy(/\n\n+/)`  | 265 ms    | 16.1 MB/s  | 12.1 MB     |
| `ParagraphChunkingStrategy`       | 293 ms    | 14.5 MB/s  | 12.1 MB     |
| `RecursiveChunkingStrategy`       | 362 ms    | 11.8 MB/s  | 32.1 MB     |
| `WordCountChunkingStrategy(200)`  | 377 ms    | 11.3 MB/s  | 1.9 MB      |

> `ParagraphChunkingStrategy` and `RegexChunkingStrategy` buffer content between delimiters, which explains the higher peak memory on long-paragraph texts. `RecursiveChunkingStrategy` accumulates chunks for re-splitting, hence the highest memory cost.

### Les Misérables — 5 tomes (2.54 MB)

| Strategy                             | Time (ms) | Throughput | Peak memory |
|--------------------------------------|-----------|------------|-------------|
| `LineChunkingStrategy(10)`           | 26.1 ms   | 97.4 MB/s  | 1.9 MB      |
| `FixedSizeChunkingStrategy(1000)`    | 29.3 ms   | 86.7 MB/s  | 1.9 MB      |
| `SentenceChunkingStrategy`           | 31.3 ms   | 81.1 MB/s  | 1.9 MB      |
| `DialogueChunkingStrategy(500, 100)` | 50.6 ms   | 50.2 MB/s  | 1.9 MB      |
| `ParagraphChunkingStrategy`          | 104 ms    | 24.4 MB/s  | 7.8 MB      |
| `RecursiveChunkingStrategy`          | 146 ms    | 17.4 MB/s  | 24.6 MB     |
| `WordCountChunkingStrategy(200)`     | 219 ms    | 11.6 MB/s  | 1.9 MB      |

### Encyclopaedia Britannica 11th Ed. (100.8 MB)

| Strategy                          | Time (ms) | Throughput | Peak memory |
|-----------------------------------|-----------|------------|-------------|
| `ParagraphChunkingStrategy`       | 743 ms    | 135.6 MB/s | 1.9 MB      |
| `FixedSizeChunkingStrategy(1000)` | 1,031 ms  | 97.7 MB/s  | 1.9 MB      |
| `WordCountChunkingStrategy(200)`  | 7,739 ms  | 13.0 MB/s  | 1.9 MB      |

> The Britannica file has short, dense paragraphs after header stripping — `ParagraphChunkingStrategy` keeps memory flat at ~2 MB despite the 100 MB input, thanks to streaming.

---

## Post-Processors

Measured in isolation on a **~50 KB excerpt** of the Bible KJV (pre-chunked with `ParagraphChunkingStrategy`). 5 revs × 3 iterations.

| Post-processor                       | Time (ms) | Throughput |
|--------------------------------------|-----------|------------|
| `OverlappingChunkPostProcessor(200)` | 0.29 ms   | 166 MB/s   |
| `DeduplicationPostProcessor`         | 0.34 ms   | 142 MB/s   |
| `ChunkMergerPostProcessor(200)`      | 0.34 ms   | 142 MB/s   |
| `RegexReplacePostProcessor`          | 0.39 ms   | 125 MB/s   |
| `MetadataEnricherPostProcessor`      | 0.44 ms   | 111 MB/s   |
| `ChunkFilterPostProcessor(50)`       | 0.55 ms   | 89 MB/s    |
| `TextNormalizationPostProcessor`     | 1.17 ms   | 42 MB/s    |
| `TokenLimitPostProcessor(256, 4)`    | 2.87 ms   | 17 MB/s    |

> All post-processors run in **< 3 ms** on a 50 KB corpus. `TokenLimitPostProcessor` is the heaviest due to substring splitting on every oversized chunk. `TextNormalizationPostProcessor` applies multiple regex passes per chunk, which explains its mid-range cost.

---

## Key Takeaways

- **Fastest strategies**: `FixedSizeChunkingStrategy`, `LineChunkingStrategy`, and `SentenceChunkingStrategy` consistently process text at **80–100 MB/s**.
- **Slowest strategy**: `WordCountChunkingStrategy` — word boundary counting is inherently O(n words), making it **7–8× slower** than fixed-size strategies.
- **Memory**: The library is streaming-first. Most strategies stay at the PHP baseline (~2 MB) regardless of input size. `ParagraphChunkingStrategy` and `RegexChunkingStrategy` buffer between delimiters; `RecursiveChunkingStrategy` buffers chunks for re-splitting.
- **Post-processors**: All 8 processors add negligible overhead (< 3 ms / 50 KB). Chain freely.
