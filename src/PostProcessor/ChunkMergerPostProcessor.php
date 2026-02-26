<?php

declare(strict_types=1);

namespace Ecourty\TextChunker\PostProcessor;

use Ecourty\TextChunker\Contract\ChunkPostProcessorInterface;
use Ecourty\TextChunker\ValueObject\Chunk;

final class ChunkMergerPostProcessor implements ChunkPostProcessorInterface
{
    public function __construct(
        private readonly int $minChunkSize = 200,
        private readonly string $separator = "\n\n",
    ) {
        if ($this->minChunkSize <= 0) {
            throw new \InvalidArgumentException('minChunkSize must be > 0');
        }
    }

    public function process(\Generator $chunks, string $source = ''): \Generator
    {
        /** @var Chunk[] $buffer */
        $buffer = [];
        $bufferLength = 0;

        foreach ($chunks as $chunk) {
            $buffer[] = $chunk;
            $bufferLength += mb_strlen($chunk->getText());

            if ($bufferLength >= $this->minChunkSize) {
                yield $this->mergeBuffer($buffer);
                $buffer = [];
                $bufferLength = 0;
            }
        }

        if ($buffer !== []) {
            yield $this->mergeBuffer($buffer);
        }
    }

    /** @param Chunk[] $buffer */
    private function mergeBuffer(array $buffer): Chunk
    {
        $texts = array_map(fn (Chunk $c) => $c->getText(), $buffer);
        $merged = implode($this->separator, $texts);

        return new Chunk(
            text: $merged,
            position: $buffer[0]->getPosition(),
            metadata: array_merge($buffer[0]->getMetadata(), [
                'merged_count' => \count($buffer),
                'length' => mb_strlen($merged),
            ]),
        );
    }
}
