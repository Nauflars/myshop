<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\UserProfile;
use App\Domain\Repository\UserProfileRepositoryInterface;
use MongoDB\Client;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

/**
 * MongoDB implementation for UserProfile repository.
 */
class MongoDBUserProfileRepository implements UserProfileRepositoryInterface
{
    private Database $database;
    private LoggerInterface $logger;
    private string $collectionName = 'user_profiles';

    public function __construct(
        Client $mongoClient,
        string $databaseName,
        LoggerInterface $logger,
    ) {
        $this->database = $mongoClient->selectDatabase($databaseName);
        $this->logger = $logger;
    }

    public function findByUserId(string $userId): ?UserProfile
    {
        try {
            $collection = $this->database->selectCollection($this->collectionName);
            $document = $collection->findOne(['userId' => $userId]);

            if (!$document) {
                $this->logger->info('DEBUG: Profile document not found in MongoDB', [
                    'userId' => $userId,
                    'collection' => $this->collectionName,
                ]);

                return null;
            }

            $this->logger->info('DEBUG: Profile document found, converting to entity', [
                'userId' => $userId,
                'documentKeys' => array_keys((array) $document),
            ]);

            return UserProfile::fromArray((array) $document);
        } catch (\Exception $e) {
            $this->logger->error('Failed to find user profile', [
                'userId' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return null;
        }
    }

    public function save(UserProfile $profile): void
    {
        try {
            $collection = $this->database->selectCollection($this->collectionName);
            $data = $profile->toArray();

            $collection->updateOne(
                ['userId' => $profile->getUserId()],
                ['$set' => $data],
                ['upsert' => true]
            );

            $this->logger->info('User profile saved successfully', [
                'userId' => $profile->getUserId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save user profile', [
                'userId' => $profile->getUserId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function delete(string $userId): void
    {
        try {
            $collection = $this->database->selectCollection($this->collectionName);
            $result = $collection->deleteOne(['userId' => $userId]);

            $this->logger->info('User profile deleted', [
                'userId' => $userId,
                'deletedCount' => $result->getDeletedCount(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete user profile', [
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    public function findSimilarProducts(array $embedding, int $limit = 20): array
    {
        try {
            $collection = $this->database->selectCollection('product_embeddings');

            $this->logger->info('Starting vector search using PHP cosine similarity', [
                'embeddingLength' => count($embedding),
                'limit' => $limit,
                'collection' => 'product_embeddings',
            ]);

            // Fetch all product embeddings
            $allEmbeddings = $collection->find(
                [],
                [
                    'projection' => [
                        'product_id' => 1,
                        'embedding' => 1,
                        '_id' => 0,
                    ],
                ]
            )->toArray();

            $this->logger->info('DEBUG: Embeddings fetched from MongoDB', [
                'count' => count($allEmbeddings),
                'first_has_embedding' => isset($allEmbeddings[0]['embedding']) ? 'yes' : 'no',
                'first_embedding_type' => isset($allEmbeddings[0]['embedding']) ? get_class($allEmbeddings[0]['embedding']) : 'N/A',
            ]);

            $results = [];
            $iteration = 0;

            foreach ($allEmbeddings as $doc) {
                $docArray = (array) $doc;

                if (!isset($docArray['embedding'])) {
                    continue;
                }

                // Convert BSON array to PHP array
                $productEmbedding = $docArray['embedding'];
                if ($productEmbedding instanceof \MongoDB\Model\BSONArray) {
                    $productEmbedding = iterator_to_array($productEmbedding);
                } elseif (is_object($productEmbedding) && method_exists($productEmbedding, 'getArrayCopy')) {
                    $productEmbedding = $productEmbedding->getArrayCopy();
                } elseif (!is_array($productEmbedding)) {
                    continue;
                }

                // Calculate cosine similarity
                $similarity = $this->calculateCosineSimilarity($embedding, $productEmbedding);

                // Log first 3 calculations for debugging
                if ($iteration < 3) {
                    $this->logger->info('DEBUG: Similarity calculation', [
                        'iteration' => $iteration,
                        'productId' => $docArray['product_id'],
                        'productEmbeddingLength' => count($productEmbedding),
                        'queryEmbeddingLength' => count($embedding),
                        'similarity' => $similarity,
                    ]);
                }

                $results[] = [
                    'productId' => $docArray['product_id'],
                    'score' => $similarity,
                ];

                ++$iteration;
            }

            // Sort by similarity descending
            usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

            $this->logger->info('DEBUG: Results sorted', [
                'totalResults' => count($results),
                'topScore' => isset($results[0]) ? $results[0]['score'] : 'N/A',
                'top3Scores' => array_slice(array_map(fn ($r) => $r['score'], $results), 0, 3),
            ]);

            // Limit results
            $results = array_slice($results, 0, $limit);

            $this->logger->info('Vector search completed', [
                'resultCount' => count($results),
                'limit' => $limit,
            ]);

            return $results;
        } catch (\Exception $e) {
            $this->logger->error('Failed to perform vector search', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'embeddingLength' => count($embedding),
            ]);

            return [];
        }
    }

    /**
     * Calculate cosine similarity between two vectors.
     */
    private function calculateCosineSimilarity(array $vec1, array $vec2): float
    {
        if (count($vec1) !== count($vec2)) {
            throw new \InvalidArgumentException('Vectors must have the same dimensions');
        }

        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;

        for ($i = 0; $i < count($vec1); ++$i) {
            $dotProduct += $vec1[$i] * $vec2[$i];
            $magnitude1 += $vec1[$i] * $vec1[$i];
            $magnitude2 += $vec2[$i] * $vec2[$i];
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if (0 == $magnitude1 || 0 == $magnitude2) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    public function findStaleProfiles(int $daysOld = 30): array
    {
        try {
            $collection = $this->database->selectCollection($this->collectionName);
            $threshold = new \DateTime("-{$daysOld} days");

            $cursor = $collection->find([
                'updatedAt' => ['$lt' => $threshold->format('c')],
            ]);

            $profiles = [];
            foreach ($cursor as $document) {
                $profiles[] = UserProfile::fromArray((array) $document);
            }

            return $profiles;
        } catch (\Exception $e) {
            $this->logger->error('Failed to find stale profiles', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    public function countProfiles(): int
    {
        try {
            $collection = $this->database->selectCollection($this->collectionName);

            return $collection->countDocuments();
        } catch (\Exception $e) {
            $this->logger->error('Failed to count profiles', [
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    public function exists(string $userId): bool
    {
        try {
            $collection = $this->database->selectCollection($this->collectionName);
            $count = $collection->countDocuments(['userId' => $userId], ['limit' => 1]);

            return $count > 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to check profile existence', [
                'userId' => $userId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
