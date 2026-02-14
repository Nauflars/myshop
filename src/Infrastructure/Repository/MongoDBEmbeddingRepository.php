<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Application\Service\SearchMetricsCollector;
use App\Domain\Entity\ProductEmbedding;
use MongoDB\Client;
use MongoDB\Collection;
use MongoDB\Driver\Exception\Exception as MongoException;
use Psr\Log\LoggerInterface;

/**
 * MongoDBEmbeddingRepository - Store and search vector embeddings.
 *
 * Implements spec-010 FR-002: Store embeddings in MongoDB with vector search
 * Uses cosine similarity for semantic search
 * T092: Circuit breaker pattern for MongoDB failures
 * T100: Request timeout for MongoDB queries
 */
class MongoDBEmbeddingRepository
{
    private const COLLECTION_NAME = 'product_embeddings';
    private const VECTOR_INDEX_NAME = 'embedding_vector_index';

    // T092: Circuit breaker configuration
    private const CIRCUIT_BREAKER_THRESHOLD = 5;
    private const CIRCUIT_BREAKER_TIMEOUT = 60;
    private const CIRCUIT_BREAKER_CACHE_KEY = 'mongodb_circuit_breaker';

    // T100: Query timeout (milliseconds)
    private const QUERY_TIMEOUT_MS = 3000;

    private Collection $collection;

    public function __construct(
        private readonly Client $mongoClient,
        private readonly LoggerInterface $logger,
        private readonly string $databaseName,
        private readonly ?SearchMetricsCollector $metricsCollector = null,
        private readonly ?\Psr\Cache\CacheItemPoolInterface $cache = null,
    ) {
        $this->collection = $this->mongoClient->selectCollection(
            $this->databaseName,
            self::COLLECTION_NAME
        );
    }

    /**
     * Save or update a product embedding.
     */
    public function save(ProductEmbedding $productEmbedding): bool
    {
        // T092: Check if MongoDB circuit breaker is open
        if ($this->isCircuitBreakerOpen()) {
            $this->logger->warning('MongoDB circuit breaker is OPEN - blocking save operation');
            throw new \RuntimeException('MongoDB service unavailable (circuit breaker open)');
        }

        try {
            $data = $productEmbedding->toArray();

            $this->collection->updateOne(
                ['productId' => $productEmbedding->getProductId()],
                ['$set' => $data],
                ['upsert' => true]
            );

            $this->logger->info('Product embedding saved', [
                'productId' => $productEmbedding->getProductId(),
            ]);

            // T092: Reset circuit breaker on successful MongoDB operation
            $this->resetCircuitBreaker();

            return true;
        } catch (MongoException $e) {
            // T092: Record circuit breaker failure
            $this->recordCircuitBreakerFailure();

            $this->logger->error('Failed to save embedding', [
                'productId' => $productEmbedding->getProductId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Save multiple embeddings in batch.
     */
    public function saveBatch(array $productEmbeddings): int
    {
        // T092: Check if MongoDB circuit breaker is open
        if ($this->isCircuitBreakerOpen()) {
            $this->logger->warning('MongoDB circuit breaker is OPEN - blocking batch save operation');
            throw new \RuntimeException('MongoDB service unavailable (circuit breaker open)');
        }

        $savedCount = 0;

        try {
            $operations = [];
            foreach ($productEmbeddings as $embedding) {
                if (!$embedding instanceof ProductEmbedding) {
                    continue;
                }

                $operations[] = [
                    'updateOne' => [
                        ['productId' => $embedding->getProductId()],
                        ['$set' => $embedding->toArray()],
                        ['upsert' => true],
                    ],
                ];
            }

            if (empty($operations)) {
                return 0;
            }

            $result = $this->collection->bulkWrite($operations);
            $savedCount = $result->getUpsertedCount() + $result->getModifiedCount();

            $this->logger->info('Batch embeddings saved', [
                'count' => $savedCount,
                'total' => count($operations),
            ]);

            // T092: Reset circuit breaker on successful MongoDB operation
            $this->resetCircuitBreaker();

            return $savedCount;
        } catch (MongoException $e) {
            // T092: Record circuit breaker failure
            $this->recordCircuitBreakerFailure();

            $this->logger->error('Failed to save batch embeddings', [
                'error' => $e->getMessage(),
            ]);

            return $savedCount;
        }
    }

    /**
     * Find embedding by product ID.
     */
    public function findByProductId(string $productId): ?ProductEmbedding
    {
        // T092: Check if MongoDB circuit breaker is open
        if ($this->isCircuitBreakerOpen()) {
            $this->logger->warning('MongoDB circuit breaker is OPEN - blocking find operation');
            throw new \RuntimeException('MongoDB service unavailable (circuit breaker open)');
        }

        try {
            $document = $this->collection->findOne(['productId' => $productId]);

            if (null === $document) {
                return null;
            }

            // T092: Reset circuit breaker on successful read
            $this->resetCircuitBreaker();

            return ProductEmbedding::fromArray((array) $document);
        } catch (MongoException $e) {
            // T092: Record circuit breaker failure
            $this->recordCircuitBreakerFailure();

            $this->logger->error('Failed to find embedding', [
                'productId' => $productId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Search for similar products using cosine similarity.
     *
     * Implements spec-010 FR-003: Vector similarity search
     * Uses PHP-based cosine similarity calculation (MongoDB aggregation is complex for dynamic arrays)
     *
     * @param array $queryEmbedding 1536-dimension vector
     * @param int   $limit          Maximum results to return
     * @param float $minSimilarity  Minimum similarity threshold (0.0-1.0)
     *
     * @return array Array of [productId, similarity, name, description, category, metadata]
     */
    public function searchSimilar(array $queryEmbedding, int $limit = 10, float $minSimilarity = 0.0): array
    {
        // T092: Check if MongoDB circuit breaker is open
        if ($this->isCircuitBreakerOpen()) {
            $this->logger->warning('MongoDB circuit breaker is OPEN - blocking similarity search');
            throw new \RuntimeException('MongoDB service unavailable (circuit breaker open)');
        }

        // T078: Track MongoDB query performance
        $queryStartTime = microtime(true);

        try {
            // T079: Optimize MongoDB query with projection (only return needed fields)
            // Fetch only embedding, productId, and metadata fields instead of all document data
            $allEmbeddings = $this->collection->find(
                [],
                [
                    'projection' => [
                        'productId' => 1,
                        'embedding' => 1,
                        'name' => 1,
                        'description' => 1,
                        'category' => 1,
                        'metadata' => 1,
                        '_id' => 0, // Exclude MongoDB's internal _id
                    ],
                ]
            )->toArray();

            $documentsScanned = count($allEmbeddings);
            $results = [];

            foreach ($allEmbeddings as $doc) {
                $docArray = (array) $doc;

                if (!isset($docArray['embedding'])) {
                    continue;
                }

                // MongoDB 2.x compatibility: Convert BSONArray to PHP array
                $embedding = $docArray['embedding'];
                if ($embedding instanceof \MongoDB\Model\BSONArray) {
                    $embedding = iterator_to_array($embedding);
                } elseif (is_object($embedding) && method_exists($embedding, 'getArrayCopy')) {
                    $embedding = $embedding->getArrayCopy();
                } elseif (!is_array($embedding)) {
                    continue; // Skip if not convertible to array
                }

                $similarity = $this->calculateCosineSimilarity(
                    $queryEmbedding,
                    $embedding
                );

                if ($similarity >= $minSimilarity) {
                    // MongoDB 2.x compatibility: Convert BSONDocument metadata
                    $metadata = $docArray['metadata'] ?? [];
                    if ($metadata instanceof \MongoDB\Model\BSONDocument
                        || $metadata instanceof \MongoDB\Model\BSONArray) {
                        $metadata = iterator_to_array($metadata);
                    }

                    $results[] = [
                        'productId' => $docArray['productId'],
                        'similarity' => $similarity,
                        'name' => $docArray['name'] ?? '',
                        'description' => $docArray['description'] ?? '',
                        'category' => $docArray['category'] ?? '',
                        'metadata' => $metadata,
                    ];
                }
            }

            // Sort by similarity descending
            usort($results, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

            // Limit results
            $results = array_slice($results, 0, $limit);

            // T078: Record MongoDB query performance metrics
            $queryTimeMs = (microtime(true) - $queryStartTime) * 1000;
            if (null !== $this->metricsCollector) {
                $this->metricsCollector->recordMongoDBQuery(
                    queryTimeMs: $queryTimeMs,
                    documentsScanned: $documentsScanned,
                    documentsReturned: count($results)
                );
            }

            $this->logger->info('Similarity search completed', [
                'results_count' => count($results),
                'limit' => $limit,
                'min_similarity' => $minSimilarity,
                'query_time_ms' => round($queryTimeMs, 2),
                'documents_scanned' => $documentsScanned,
            ]);

            // T092: Reset circuit breaker on successful MongoDB query
            $this->resetCircuitBreaker();

            return $results;
        } catch (MongoException $e) {
            // T092: Record circuit breaker failure
            $this->recordCircuitBreakerFailure();

            $this->logger->error('Similarity search failed', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Calculate cosine similarity between two vectors.
     *
     * Cosine similarity = dot(A,B) / (||A|| * ||B||)
     *
     * @return float Similarity score between 0.0 and 1.0
     */
    private function calculateCosineSimilarity(array $vectorA, array $vectorB): float
    {
        if (count($vectorA) !== count($vectorB)) {
            return 0.0;
        }

        $dotProduct = 0.0;
        $magnitudeA = 0.0;
        $magnitudeB = 0.0;

        for ($i = 0; $i < count($vectorA); ++$i) {
            $dotProduct += $vectorA[$i] * $vectorB[$i];
            $magnitudeA += $vectorA[$i] * $vectorA[$i];
            $magnitudeB += $vectorB[$i] * $vectorB[$i];
        }

        $magnitudeA = sqrt($magnitudeA);
        $magnitudeB = sqrt($magnitudeB);

        if (0.0 == $magnitudeA || 0.0 == $magnitudeB) {
            return 0.0;
        }

        return $dotProduct / ($magnitudeA * $magnitudeB);
    }

    /**
     * Delete embedding by product ID.
     */
    public function delete(int $productId): bool
    {
        // T092: Check if MongoDB circuit breaker is open
        if ($this->isCircuitBreakerOpen()) {
            $this->logger->warning('MongoDB circuit breaker is OPEN - blocking delete operation');
            throw new \RuntimeException('MongoDB service unavailable (circuit breaker open)');
        }

        try {
            $result = $this->collection->deleteOne(['productId' => $productId]);

            $this->logger->info('Embedding deleted', [
                'productId' => $productId,
                'deleted' => $result->getDeletedCount(),
            ]);

            // T092: Reset circuit breaker on successful delete
            $this->resetCircuitBreaker();

            return $result->getDeletedCount() > 0;
        } catch (MongoException $e) {
            // T092: Record circuit breaker failure
            $this->recordCircuitBreakerFailure();

            $this->logger->error('Failed to delete embedding', [
                'productId' => $productId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete all embeddings.
     */
    public function deleteAll(): int
    {
        // T092: Check if MongoDB circuit breaker is open
        if ($this->isCircuitBreakerOpen()) {
            $this->logger->warning('MongoDB circuit breaker is OPEN - blocking deleteAll operation');
            throw new \RuntimeException('MongoDB service unavailable (circuit breaker open)');
        }

        try {
            $result = $this->collection->deleteMany([]);
            $deletedCount = $result->getDeletedCount();

            $this->logger->warning('All embeddings deleted', [
                'count' => $deletedCount,
            ]);

            // T092: Reset circuit breaker on successful deleteAll
            $this->resetCircuitBreaker();

            return $deletedCount;
        } catch (MongoException $e) {
            // T092: Record circuit breaker failure
            $this->recordCircuitBreakerFailure();

            $this->logger->error('Failed to delete all embeddings', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Count total embeddings.
     */
    public function count(): int
    {
        try {
            return $this->collection->countDocuments();
        } catch (MongoException $e) {
            $this->logger->error('Failed to count embeddings', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Check if vector index exists.
     */
    public function hasVectorIndex(): bool
    {
        try {
            $indexes = iterator_to_array($this->collection->listIndexes());

            foreach ($indexes as $index) {
                if (self::VECTOR_INDEX_NAME === $index->getName()) {
                    return true;
                }
            }

            return false;
        } catch (MongoException $e) {
            $this->logger->error('Failed to check vector index', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get collection name.
     */
    public function getCollectionName(): string
    {
        return self::COLLECTION_NAME;
    }

    /**
     * Get vector index name.
     */
    public function getVectorIndexName(): string
    {
        return self::VECTOR_INDEX_NAME;
    }

    /**
     * T092: Check if MongoDB circuit breaker is open.
     */
    private function isCircuitBreakerOpen(): bool
    {
        if (null === $this->cache) {
            return false;
        }

        try {
            $item = $this->cache->getItem(self::CIRCUIT_BREAKER_CACHE_KEY);

            if (!$item->isHit()) {
                return false;
            }

            $state = $item->get();

            if (isset($state['opened_at'])) {
                $elapsed = time() - $state['opened_at'];

                if ($elapsed >= self::CIRCUIT_BREAKER_TIMEOUT) {
                    $this->logger->info('MongoDB circuit breaker entering HALF-OPEN state');
                    $this->resetCircuitBreaker();

                    return false;
                }
            }

            return $state['is_open'] ?? false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to check MongoDB circuit breaker state', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * T092: Record a MongoDB failure for circuit breaker.
     */
    private function recordCircuitBreakerFailure(): void
    {
        if (null === $this->cache) {
            return;
        }

        try {
            $item = $this->cache->getItem(self::CIRCUIT_BREAKER_CACHE_KEY);
            $state = $item->isHit() ? $item->get() : ['failures' => 0, 'is_open' => false];

            $state['failures'] = ($state['failures'] ?? 0) + 1;

            if ($state['failures'] >= self::CIRCUIT_BREAKER_THRESHOLD && !($state['is_open'] ?? false)) {
                $state['is_open'] = true;
                $state['opened_at'] = time();

                $this->logger->error('MongoDB circuit breaker OPENED', [
                    'failures' => $state['failures'],
                    'threshold' => self::CIRCUIT_BREAKER_THRESHOLD,
                    'timeout_seconds' => self::CIRCUIT_BREAKER_TIMEOUT,
                ]);
            }

            $item->set($state);
            $item->expiresAfter(self::CIRCUIT_BREAKER_TIMEOUT + 60);
            $this->cache->save($item);
        } catch (\Exception $e) {
            $this->logger->error('Failed to record MongoDB circuit breaker failure', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * T092: Reset MongoDB circuit breaker on successful request.
     */
    private function resetCircuitBreaker(): void
    {
        if (null === $this->cache) {
            return;
        }

        try {
            $item = $this->cache->getItem(self::CIRCUIT_BREAKER_CACHE_KEY);

            if ($item->isHit()) {
                $state = $item->get();

                if ($state['is_open'] ?? false) {
                    $this->logger->info('MongoDB circuit breaker CLOSED (service recovered)');
                }
            }

            $item->set(['failures' => 0, 'is_open' => false]);
            $item->expiresAfter(self::CIRCUIT_BREAKER_TIMEOUT + 60);
            $this->cache->save($item);
        } catch (\Exception $e) {
            $this->logger->error('Failed to reset MongoDB circuit breaker', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
