<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\ValueObject\UserEmbedding;

/**
 * UserEmbeddingRepositoryInterface - Repository contract for user embeddings persistence
 * 
 * Implements spec-014 architecture: Domain repository interface
 * Infrastructure layer provides MongoDB implementation
 */
interface UserEmbeddingRepositoryInterface
{
    /**
     * Find user embedding by user ID
     * 
     * @param string $userId User UUID identifier
     * @return UserEmbedding|null User embedding or null if not found
     */
    public function findByUserId(string $userId): ?UserEmbedding;

    /**
     * Save or update user embedding with optimistic locking
     * 
     * @param UserEmbedding $embedding User embedding to persist
     * @return bool True if save successful, false if version conflict
     * @throws \RuntimeException On database errors
     */
    public function save(UserEmbedding $embedding): bool;

    /**
     * Check if user embedding exists
     * 
     * @param string $userId User UUID identifier
     * @return bool True if embedding exists
     */
    public function exists(string $userId): bool;

    /**
     * Delete user embedding
     * 
     * @param string $userId User UUID identifier
     * @return bool True if deleted, false if not found
     */
    public function delete(string $userId): bool;

    /**
     * Get current version number for user embedding
     * 
     * @param string $userId User UUID identifier
     * @return int|null Version number or null if not found
     */
    public function getVersion(string $userId): ?int;

    /**
     * Find stale embeddings (not updated recently)
     * 
     * @param int $maxDaysOld Maximum age in days
     * @param int $limit Maximum results to return
     * @return array<int, UserEmbedding> Array of [userId => UserEmbedding]
     */
    public function findStaleEmbeddings(int $maxDaysOld, int $limit = 100): array;

    /**
     * Count total user embeddings
     * 
     * @return int Total count
     */
    public function count(): int;

    /**
     * Find similar products based on user embedding vector
     * 
     * Performs cosine similarity search against product_embeddings collection
     * 
     * @param array<float> $embedding User's 1536-dimensional embedding vector
     * @param int $limit Maximum number of results to return
     * @return array<array{productId: string, score: float}> Array of products with similarity scores
     */
    public function findSimilarProducts(array $embedding, int $limit = 20): array;
}
