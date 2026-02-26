<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\PostProcessor;

use Ecourty\TextChunker\Contract\ChunkPostProcessorInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class TokenLimitPostProcessor implements ChunkPostProcessorInterface
{
    private const int CHARACTERS_PER_TOKEN = 4;

    public function __construct(
        private readonly int $maxTokens,
        private readonly int $charactersPerToken = self::CHARACTERS_PER_TOKEN,
    ) {
        if ($this->maxTokens <= 0) {
            throw new \InvalidArgumentException('Max tokens must be > 0');
        }
        if ($this->charactersPerToken <= 0) {
            throw new \InvalidArgumentException('Characters per token must be > 0');
        }
    }

    /**
     * @param \Generator<Chunk> $chunks
     *
     * @return \Generator<Chunk>
     */
    public function process(\Generator $chunks, string $source = ''): \Generator
    {
        foreach ($chunks as $chunk) {
            $estimatedTokens = $this->estimateTokens($chunk->getText());

            if ($estimatedTokens <= $this->maxTokens) {
                yield $chunk->withMetadata([
                    'estimated_tokens' => $estimatedTokens,
                    'token_limit' => $this->maxTokens,
                ]);
                continue;
            }

            yield from $this->splitChunk($chunk);
        }
    }

    /** @return \Generator<Chunk> */
    private function splitChunk(Chunk $chunk): \Generator
    {
        $text = $chunk->getText();
        $maxChars = $this->maxTokens * $this->charactersPerToken;
        $offset = 0;
        $subPosition = 0;

        while ($offset < mb_strlen($text)) {
            $subText = mb_substr($text, $offset, $maxChars);

            yield new Chunk(
                text: $subText,
                position: $chunk->getPosition(),
                metadata: array_merge($chunk->getMetadata(), [
                    'estimated_tokens' => $this->estimateTokens($subText),
                    'token_limit' => $this->maxTokens,
                    'split_from_original' => true,
                    'sub_position' => $subPosition,
                ]),
            );

            $offset += $maxChars;
            ++$subPosition;
        }
    }

    private function estimateTokens(string $text): int
    {
        return (int) ceil(mb_strlen($text) / $this->charactersPerToken);
    }
}
