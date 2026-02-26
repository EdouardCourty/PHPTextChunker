<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\PostProcessor;

use Ecourty\TextChunker\Contract\ChunkPostProcessorInterface;

final class MetadataEnricherPostProcessor implements ChunkPostProcessorInterface
{
    public function process(\Generator $chunks, string $source = ''): \Generator
    {
        $bufferedChunks = [];

        foreach ($chunks as $chunk) {
            $bufferedChunks[] = $chunk;
        }

        $totalChunks = \count($bufferedChunks);

        foreach ($bufferedChunks as $index => $chunk) {
            $text = $chunk->getText();

            yield $chunk->withMetadata([
                'chunk_index' => $index,
                'total_chunks' => $totalChunks,
                'position_percentage' => $totalChunks > 0 ? round(($index / $totalChunks) * 100, 2) : 0,
                'word_count' => str_word_count($text),
                'char_count' => mb_strlen($text),
                'source' => $source !== '' ? basename($source) : '',
            ]);
        }
    }
}
