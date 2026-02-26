<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\PostProcessor;

use Ecourty\TextChunker\Contract\ChunkPostProcessorInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class RegexReplacePostProcessor implements ChunkPostProcessorInterface
{
    /**
     * @param array<string, string> $replacements Map of [pattern => replacement]
     */
    public function __construct(
        private readonly array $replacements,
    ) {
        foreach (array_keys($this->replacements) as $pattern) {
            if (@preg_match($pattern, '') === false) {
                throw new \InvalidArgumentException(\sprintf('Invalid regex pattern: %s', $pattern));
            }
        }
    }

    public function process(\Generator $chunks, string $source = ''): \Generator
    {
        foreach ($chunks as $chunk) {
            $text = $chunk->getText();

            foreach ($this->replacements as $pattern => $replacement) {
                $text = (string) preg_replace($pattern, $replacement, $text);
            }

            yield new Chunk(
                text: $text,
                position: $chunk->getPosition(),
                metadata: $chunk->getMetadata(),
            );
        }
    }
}
