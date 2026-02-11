<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\UserEmbeddingRepositoryInterface;
use App\Domain\ValueObject\UserEmbedding;
use DateTimeImmutable;
use MongoDB\Client;
use MongoDB\Collection;
use Psr\Log\LoggerInterface;

/**
 * UserEmbeddingRepository - MongoDB implementation for user embeddings
 * 
 * Implements spec-014 data model: user_embeddings collection in MongoDB
 * Stores 1536-dimensional vectors with version-based optimistic locking
 */
final class UserEmbeddingRepository implements UserEmbeddingRepositoryInterface
{
    private Collection $collection;

    public function __construct(
        private readonly Client $mongoClient,
        private readonly LoggerInterface $logger,
        private readonly string $databaseName = 'myshop',
        private readonly string $collectionName = 'user_embeddings'
    ) {
        $this->collection = $this->mongoClient
            ->selectDatabase($databaseName)
            ->selectCollection($collectionName);

        // Ensure indexes on initialization
        $this->ensureIndexes();
    }

    /**
     * Find user embedding by user ID
     * 
     * @param string $userId User UUID identifier
     * @return UserEmbedding|null Embedding or null if not found
     */
    public function findByUserId(string $userId): ?UserEmbedding
    {
        try {
            $document = $this->collection->findOne(['user_id' => $userId]);

            if ($document === null) {
                return null;
            }

            // Convert BSONDocument to array
            $documentArray = $document instanceof \MongoDB\Model\BSONDocument
                ? $document->getArrayCopy()
                : (array) $document;

            return $this->documentToEmbedding($documentArray);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to find user embedding', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to retrieve user embedding', 0, $e);
        }
    }

    /**
     * Save user embedding with optimistic locking
     * 
     * @param UserEmbedding $embedding Embedding to save
     * @return bool True if saved successfully, false if version conflict
     */
    public function save(UserEmbedding $embedding): bool
    {
        try {
            $document = $this->embeddingToDocument($embedding);

            $this->logger->info('Attempting to save user embedding to MongoDB', [
                'user_id' => $embedding->userId,
                'version' => $embedding->version,
                'vector_dimensions' => count($embedding->vector),
                'is_new' => $embedding->version === 1,
            ]);

            if ($embedding->version === 1) {
                // New embedding: insert
                $result = $this->collection->insertOne($document);
                
                $this->logger->info('MongoDB insertOne result', [
                    'user_id' => $embedding->userId,
                    'inserted_count' => $result->getInsertedCount(),
                    'inserted_id' => (string) $result->getInsertedId(),
                ]);
                
                return $result->getInsertedCount() === 1;

            } else {
                // Existing embedding: update with optimistic locking
                $result = $this->collection->updateOne(
                    [
                        'user_id' => $embedding->userId,
                        'version' => $embedding->version - 1, // Check previous version
                    ],
                    ['$set' => $document]
                );

                if ($result->getModifiedCount() === 0) {
                    $this->logger->warning('Optimistic locking conflict detected', [
                        'user_id' => $embedding->userId,
                        'version' => $embedding->version,
                    ]);
                    return false;
                }

                return true;
            }

        } catch (\Throwable $e) {
            $this->logger->error('Failed to save user embedding', [
                'user_id' => $embedding->userId,
                'version' => $embedding->version,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to save user embedding', 0, $e);
        }
    }

    /**
     * Check if embedding exists for user
     * 
     * @param string $userId User UUID identifier
     * @return bool True if embedding exists
     */
    public function exists(string $userId): bool
    {
        try {
            return $this->collection->countDocuments(['user_id' => $userId]) > 0;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to check embedding existence', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to check embedding existence', 0, $e);
        }
    }

    /**
     * Delete user embedding
     * 
     * @param string $userId User UUID identifier
     * @return bool True if deleted
     */
    public function delete(string $userId): bool
    {
        try {
            $result = $this->collection->deleteOne(['user_id' => $userId]);
            return $result->getDeletedCount() === 1;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to delete user embedding', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to delete user embedding', 0, $e);
        }
    }

    /**
     * Get current version of user embedding
     * 
     * @param string $userId User UUID identifier
     * @return int|null Version number, or null if not found
     */
    public function getVersion(string $userId): ?int
    {
        try {
            $document = $this->collection->findOne(
                ['user_id' => $userId],
                ['projection' => ['version' => 1]]
            );

            if ($document === null) {
                return null;
            }

            // Convert BSONDocument to array for array access
            $documentArray = $document instanceof \MongoDB\Model\BSONDocument
                ? $document->getArrayCopy()
                : (array) $document;

            return $documentArray['version'] ?? null;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to get embedding version', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to get embedding version', 0, $e);
        }
    }

    /**
     * Find stale embeddings that need decay application
     * 
     * @param int $maxDaysOld Find embeddings older than this many days
     * @param int $limit Maximum number to return
     * @return array<UserEmbedding> Stale embeddings
     */
    public function findStaleEmbeddings(int $maxDaysOld, int $limit = 100): array
    {
        try {
            $olderThan = new DateTimeImmutable("-{$maxDaysOld} days");
            
            $cursor = $this->collection->find(
                ['last_updated_at' => ['$lt' => $olderThan->format('c')]],
                ['limit' => $limit]
            );

            $embeddings = [];
            foreach ($cursor as $document) {
                // Convert BSONDocument to array
                $documentArray = $document instanceof \MongoDB\Model\BSONDocument
                    ? $document->getArrayCopy()
                    : (array) $document;
                $embeddings[] = $this->documentToEmbedding($documentArray);
            }

            return $embeddings;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to find stale embeddings', [
                'max_days_old' => $maxDaysOld,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to find stale embeddings', 0, $e);
        }
    }

    /**
     * Count total embeddings
     * 
     * @return int Number of embeddings
     */
    public function count(): int
    {
        try {
            return $this->collection->countDocuments([]);

        } catch (\Throwable $e) {
            $this->logger->error('Failed to count embeddings', [
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to count embeddings', 0, $e);
        }
    }

    /**
     * Convert MongoDB document to UserEmbedding value object
     * 
     * @param array<string, mixed> $document MongoDB document
     * @return UserEmbedding User embedding
     */
    private function documentToEmbedding(array $document): UserEmbedding
    {
        // Convert MongoDB BSON\UTCDateTime to PHP DateTimeImmutable
        $lastUpdated = $document['last_updated'];
        if ($lastUpdated instanceof \MongoDB\BSON\UTCDateTime) {
            // Convert MongoDB UTCDateTime to PHP DateTime, then to DateTimeImmutable
            $lastUpdatedAt = DateTimeImmutable::createFromMutable($lastUpdated->toDateTime());
        } else {
            $lastUpdatedAt = new DateTimeImmutable($lastUpdated);
        }
        
        // Convert BSONArray to PHP array
        $vector = $document['vector'];
        if ($vector instanceof \MongoDB\Model\BSONArray) {
            $vector = $vector->getArrayCopy();
        }
        
        return new UserEmbedding(
            userId: (string) $document['user_id'],
            vector: $vector,
            lastUpdatedAt: $lastUpdatedAt,
            version: (int) $document['version']
        );
    }

    /**
     * Convert UserEmbedding to MongoDB document
     * 
     * @param UserEmbedding $embedding User embedding
     * @return array<string, mixed> MongoDB document
     */
    private function embeddingToDocument(UserEmbedding $embedding): array
    {
        $now = new \MongoDB\BSON\UTCDateTime();
        
        $document = [
            'user_id' => $embedding->userId,
            'vector' => $embedding->vector,
            'dimension_count' => count($embedding->vector),
            'last_updated' => new \MongoDB\BSON\UTCDateTime($embedding->lastUpdatedAt->getTimestamp() * 1000),
            'version' => $embedding->version,
            'updated_at' => $now,
        ];
        
        // Only set created_at on insert
        if ($embedding->version === 1) {
            $document['created_at'] = $now;
        }
        
        return $document;
    }

    /**
     * Ensure MongoDB indexes exist
     */
    private function ensureIndexes(): void
    {
        try {
            // Unique index on user_id
            $this->collection->createIndex(
                ['user_id' => 1],
                ['unique' => true, 'name' => 'idx_user_id']
            );

            // Index on last_updated for stale embedding queries
            $this->collection->createIndex(
                ['last_updated' => 1],
                ['name' => 'idx_last_updated']
            );

            $this->logger->debug('MongoDB indexes ensured', [
                'collection' => $this->collectionName,
            ]);

        } catch (\Throwable $e) {
            // Log but don't fail - indexes might already exist
            $this->logger->warning('Failed to ensure indexes', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Find similar products based on user embedding vector
     * 
     * Performs cosine similarity search against product_embeddings collection
     * 
     * @param array<float> $embedding User's 1536-dimensional embedding vector
     * @param int $limit Maximum number of results
     * @return array<array{productId: string, score: float}> Products with similarity scores
     */
    public function findSimilarProducts(array $embedding, int $limit = 20): array
    {
        try {
            $productCollection = $this->mongoClient
                ->selectDatabase($this->databaseName)
                ->selectCollection('product_embeddings');

            $this->logger->info('Starting vector search for product recommendations', [
                'embeddingLength' => count($embedding),
                'limit' => $limit,
            ]);

            // Fetch all product embeddings
            $allEmbeddings = $productCollection->find(
                [],
                [
                    'projection' => [
                        'product_id' => 1,
                        'embedding' => 1,
                        '_id' => 0,
                    ],
                ]
            )->toArray();

            $this->logger->info('Product embeddings fetched from MongoDB', [
                'count' => count($allEmbeddings),
            ]);

            $results = [];

            foreach ($allEmbeddings as $doc) {
                $docArray = $doc instanceof \MongoDB\Model\BSONDocument
                    ? $doc->getArrayCopy()
                    : (array) $doc;

                if (!isset($docArray['embedding'])) {
                    continue;
                }

                // Convert BSON array to PHP array
                $productEmbedding = $docArray['embedding'];
                if ($productEmbedding instanceof \MongoDB\Model\BSONArray) {
                    $productEmbedding = $productEmbedding->getArrayCopy();
                } elseif (is_object($productEmbedding) && method_exists($productEmbedding, 'getArrayCopy')) {
                    $productEmbedding = $productEmbedding->getArrayCopy();
                } elseif (!is_array($productEmbedding)) {
                    continue;
                }

                // Calculate cosine similarity
                $similarity = $this->calculateCosineSimilarity($embedding, $productEmbedding);

                $results[] = [
                    'productId' => $docArray['product_id'],
                    'score' => $similarity,
                ];
            }

            // Sort by similarity descending
            usort($results, fn($a, $b) => $b['score'] <=> $a['score']);

            // Limit results
            $results = array_slice($results, 0, $limit);

            $this->logger->info('Vector search completed', [
                'resultCount' => count($results),
                'topScore' => isset($results[0]) ? $results[0]['score'] : 0,
            ]);

            return $results;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to perform vector search for products', [
                'error' => $e->getMessage(),
                'embeddingLength' => count($embedding),
            ]);

            return [];
        }
    }

    /**
     * Calculate cosine similarity between two vectors
     * 
     * @param array<float> $vec1 First vector
     * @param array<float> $vec2 Second vector
     * @return float Similarity score (0.0 to 1.0)
     */
    private function calculateCosineSimilarity(array $vec1, array $vec2): float
    {
        if (count($vec1) !== count($vec2)) {
            $this->logger->warning('Vector dimension mismatch', [
                'vec1_length' => count($vec1),
                'vec2_length' => count($vec2),
            ]);
            return 0.0;
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vec1); $i++) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $magnitude1 += $vec1[$i] * $vec1[$i];
            $magnitude2 += $vec2[$i] * $vec2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0 || $magnitude2 == 0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }
}
