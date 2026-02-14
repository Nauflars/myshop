<?php

declare(strict_types=1);

namespace App\Application\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Service for caching query embeddings in Redis to reduce OpenAI API costs.
 *
 * Implements spec-010 User Story 5: Query Embedding Cache
 * Cache key format: search:embedding:{md5(query)}
 * TTL: Configurable via EMBEDDING_CACHE_TTL environment variable
 */
class EmbeddingCacheService
{
    private const CACHE_KEY_PREFIX = 'search:embedding:';

    private int $cacheTtl;
    private int $cacheHits = 0;
    private int $cacheMisses = 0;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        string $embeddingCacheTtl = '3600', // Default 1 hour
    ) {
        $this->cacheTtl = (int) $embeddingCacheTtl;
    }

    /**
     * Get cached embedding for a query.
     *
     * @param string $query The search query
     *
     * @return array|null The cached embedding array (1536 dimensions) or null if not cached
     */
    public function get(string $query): ?array
    {
        try {
            $cacheKey = $this->generateCacheKey($query);
            $cachedValue = $this->cache->get($cacheKey, function (ItemInterface $item) {
                // Cache miss - return null to indicate no cached value
                $item->expiresAfter($this->cacheTtl);

                return null;
            });

            if (null !== $cachedValue) {
                ++$this->cacheHits;

                // Deserialize from JSON
                $embedding = $this->deserialize($cachedValue);

                if (null !== $embedding) {
                    $this->logger->info('Embedding cache HIT', [
                        'query' => $query,
                        'cache_key' => $cacheKey,
                        'embedding_dimensions' => count($embedding),
                        'total_hits' => $this->cacheHits,
                    ]);

                    return $embedding;
                }
            }

            // Cache miss
            ++$this->cacheMisses;
            $this->logger->info('Embedding cache MISS', [
                'query' => $query,
                'cache_key' => $cacheKey,
                'total_misses' => $this->cacheMisses,
            ]);

            return null;
        } catch (\Throwable $e) {
            // Redis connection error or other cache failure
            $this->logger->warning('Cache retrieval failed, bypassing cache', [
                'query' => $query,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return null;
        }
    }

    /**
     * Store embedding in cache.
     *
     * @param string $query     The search query
     * @param array  $embedding The embedding array (1536 dimensions)
     *
     * @return bool True if successfully cached, false on error
     */
    public function set(string $query, array $embedding): bool
    {
        try {
            // Validate embedding dimensions
            if (1536 !== count($embedding)) {
                $this->logger->error('Invalid embedding dimensions for cache', [
                    'query' => $query,
                    'expected_dimensions' => 1536,
                    'actual_dimensions' => count($embedding),
                ]);

                return false;
            }

            $cacheKey = $this->generateCacheKey($query);

            // Serialize to JSON
            $serialized = $this->serialize($embedding);

            if (null === $serialized) {
                $this->logger->error('Failed to serialize embedding for cache', [
                    'query' => $query,
                    'cache_key' => $cacheKey,
                ]);

                return false;
            }

            // Store in cache with TTL
            $this->cache->get($cacheKey, function (ItemInterface $item) use ($serialized) {
                $item->expiresAfter($this->cacheTtl);

                return $serialized;
            });

            $this->logger->info('Embedding cached successfully', [
                'query' => $query,
                'cache_key' => $cacheKey,
                'ttl_seconds' => $this->cacheTtl,
                'embedding_dimensions' => count($embedding),
            ]);

            return true;
        } catch (\Throwable $e) {
            // Redis connection error or other cache failure
            // Don't throw exception - just log and continue
            $this->logger->warning('Cache write failed, continuing without cache', [
                'query' => $query,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * Delete cached embedding for a query.
     *
     * @param string $query The search query
     *
     * @return bool True if successfully deleted, false on error
     */
    public function delete(string $query): bool
    {
        try {
            $cacheKey = $this->generateCacheKey($query);
            $this->cache->delete($cacheKey);

            $this->logger->info('Embedding cache entry deleted', [
                'query' => $query,
                'cache_key' => $cacheKey,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->warning('Cache deletion failed', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Clear all cached embeddings.
     *
     * @return bool True if successfully cleared, false on error
     */
    public function clear(): bool
    {
        try {
            // Note: Symfony Cache doesn't have a clear-by-prefix method
            // This will clear the entire cache pool
            $this->cache->clear();

            $this->logger->info('Embedding cache cleared', [
                'total_hits_before_clear' => $this->cacheHits,
                'total_misses_before_clear' => $this->cacheMisses,
            ]);

            // Reset metrics
            $this->cacheHits = 0;
            $this->cacheMisses = 0;

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to clear embedding cache', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get cache hit/miss statistics.
     *
     * @return array{hits: int, misses: int, hit_rate: float}
     */
    public function getStats(): array
    {
        $total = $this->cacheHits + $this->cacheMisses;
        $hitRate = $total > 0 ? ($this->cacheHits / $total) * 100 : 0.0;

        return [
            'hits' => $this->cacheHits,
            'misses' => $this->cacheMisses,
            'hit_rate' => round($hitRate, 2),
        ];
    }

    /**
     * Generate cache key from query.
     *
     * Format: search:embedding:{md5(query)}
     *
     * @param string $query The search query
     *
     * @return string The cache key
     */
    private function generateCacheKey(string $query): string
    {
        // Normalize query (lowercase, trim) before hashing
        $normalizedQuery = strtolower(trim($query));
        $hash = md5($normalizedQuery);

        return self::CACHE_KEY_PREFIX.$hash;
    }

    /**
     * Serialize embedding array to JSON string.
     *
     * @param array $embedding The embedding array
     *
     * @return string|null JSON string or null on error
     */
    private function serialize(array $embedding): ?string
    {
        try {
            $json = json_encode($embedding, JSON_THROW_ON_ERROR);

            return $json;
        } catch (\JsonException $e) {
            $this->logger->error('Failed to serialize embedding', [
                'error' => $e->getMessage(),
                'dimensions' => count($embedding),
            ]);

            return null;
        }
    }

    /**
     * Deserialize JSON string to embedding array.
     *
     * @param string $json The JSON string
     *
     * @return array|null Embedding array or null on error
     */
    private function deserialize(string $json): ?array
    {
        try {
            $embedding = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

            // Validate it's an array
            if (!is_array($embedding)) {
                $this->logger->error('Deserialized value is not an array', [
                    'type' => gettype($embedding),
                ]);

                return null;
            }

            // Validate dimensions
            if (1536 !== count($embedding)) {
                $this->logger->error('Invalid embedding dimensions in cache', [
                    'expected' => 1536,
                    'actual' => count($embedding),
                ]);

                return null;
            }

            return $embedding;
        } catch (\JsonException $e) {
            $this->logger->error('Failed to deserialize embedding', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
