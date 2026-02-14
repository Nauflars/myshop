<?php

declare(strict_types=1);

namespace App\Domain\Repository;

/**
 * ProductEmbeddingRepositoryInterface - Contract for retrieving product embeddings.
 *
 * Implements spec-014 US2: Product embedding retrieval for user embedding updates
 */
interface ProductEmbeddingRepositoryInterface
{
    /**
     * Find product embedding by product ID.
     *
     * @param int $productId Product identifier
     *
     * @return array<float>|null 1536-dimensional embedding vector or null if not found
     */
    public function findEmbeddingByProductId(int $productId): ?array;

    /**
     * Find multiple product embeddings by product IDs.
     *
     * @param array<int> $productIds Array of product identifiers
     *
     * @return array<int, array<float>> Map of product_id => embedding vector
     */
    public function findEmbeddingsByProductIds(array $productIds): array;

    /**
     * Check if product embedding exists.
     *
     * @param int $productId Product identifier
     *
     * @return bool True if embedding exists
     */
    public function hasEmbedding(int $productId): bool;
}
