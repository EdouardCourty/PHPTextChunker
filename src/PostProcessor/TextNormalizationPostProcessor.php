<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\PostProcessor;

use Ecourty\TextChunker\Contract\ChunkPostProcessorInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class TextNormalizationPostProcessor implements ChunkPostProcessorInterface
{
    public function __construct(
        private readonly bool $collapseWhitespace = true,
        private readonly bool $trimLines = true,
        private readonly bool $stripControlChars = true,
    ) {
    }

    public function process(\Generator $chunks, string $source = ''): \Generator
    {
        foreach ($chunks as $chunk) {
            $text = $chunk->getText();
            $text = $this->normalize($text);

            yield new Chunk(
                text: $text,
                position: $chunk->getPosition(),
                metadata: $chunk->getMetadata(),
            );
        }
    }

    private function normalize(string $text): string
    {
        if ($this->stripControlChars) {
            // Remove control characters except \n, \r, \t
            $text = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text);
        }

        if ($this->trimLines) {
            $lines = explode("\n", $text);
            $lines = array_map(mb_trim(...), $lines);
            $text = implode("\n", $lines);
        }

        if ($this->collapseWhitespace) {
            // Collapse multiple blank lines into one
            $text = (string) preg_replace('/\n{3,}/', "\n\n", $text);
            // Collapse multiple spaces/tabs on a single line
            $text = (string) preg_replace('/[ \t]+/', ' ', $text);
        }

        return mb_trim($text);
    }
}
