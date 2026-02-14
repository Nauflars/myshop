<?php

declare(strict_types=1);

namespace App\Domain\Repository;

/**
 * EmbeddingServiceInterface - Contract for embedding generation service.
 *
 * Abstracts OpenAI API or other embedding providers.
 * Part of spec-010: Semantic Product Search
 */
interface EmbeddingServiceInterface
{
    /**
     * Generate embedding vector for given text.
     *
     * @param string $text Text to embed (product name + description)
     *
     * @return array Float array of embedding dimensions (1536 for text-embedding-3-small)
     *
     * @throws \RuntimeException on API failure
     */
    public function generateEmbedding(string $text): array;

    /**
     * Generate embeddings for multiple texts in batch.
     *
     * @param array<string> $texts Array of texts to embed
     *
     * @return array<array> Array of embedding vectors
     *
     * @throws \RuntimeException on API failure
     */
    public function generateBatchEmbeddings(array $texts): array;

    /**
     * Get embedding model name being used.
     *
     * @return string Model identifier (e.g., "text-embedding-3-small")
     */
    public function getModelName(): string;

    /**
     * Get embedding dimensions for current model.
     *
     * @return int Number of dimensions (e.g., 1536)
     */
    public function getDimensions(): int;
}
