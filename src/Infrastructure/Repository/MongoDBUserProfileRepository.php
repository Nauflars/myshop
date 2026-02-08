<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\UserProfile;
use App\Domain\Repository\UserProfileRepositoryInterface;
use MongoDB\Client;
use MongoDB\Database;
use Psr\Log\LoggerInterface;

/**
 * MongoDB implementation for UserProfile repository
 */
class MongoDBUserProfileRepository implements UserProfileRepositoryInterface
{
    private Database $database;
    private LoggerInterface $logger;
    private string $collectionName = 'user_profiles';

    public function __construct(
        Client $mongoClient,
        string $databaseName,
        LoggerInterface $logger
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
                return null;
            }

            return UserProfile::fromArray((array) $document);
        } catch (\Exception $e) {
            $this->logger->error('Failed to find user profile', [
                'userId' => $userId,
                'error' => $e->getMessage(),
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

            $pipeline = [
                [
                    '$vectorSearch' => [
                        'index' => 'vector_index',
                        'path' => 'embedding',
                        'queryVector' => $embedding,
                        'numCandidates' => $limit * 10,
                        'limit' => $limit,
                    ],
                ],
                [
                    '$project' => [
                        'productId' => '$product_id',
                        'score' => ['$meta' => 'vectorSearchScore'],
                    ],
                ],
            ];

            $results = $collection->aggregate($pipeline);
            $products = [];

            foreach ($results as $result) {
                $products[] = [
                    'productId' => $result['productId'],
                    'score' => $result['score'],
                ];
            }

            $this->logger->info('Vector search completed', [
                'resultCount' => count($products),
                'limit' => $limit,
            ]);

            return $products;
        } catch (\Exception $e) {
            $this->logger->error('Failed to perform vector search', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
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
