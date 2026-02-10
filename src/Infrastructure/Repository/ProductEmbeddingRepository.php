<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\ProductEmbeddingRepositoryInterface;
use MongoDB\Client;
use Psr\Log\LoggerInterface;

/**
 * ProductEmbeddingRepository - MongoDB implementation for product embeddings
 * 
 * Implements spec-014 US2: Fetch product embeddings from MongoDB product_embeddings collection
 */
final class ProductEmbeddingRepository implements ProductEmbeddingRepositoryInterface
{
    private const COLLECTION_NAME = 'product_embeddings';
    private const EMBEDDING_DIMENSIONS = 1536;

    private \MongoDB\Collection $collection;

    public function __construct(
        private readonly Client $mongoClient,
        private readonly LoggerInterface $logger
    ) {
        $databaseName = $_ENV['MONGODB_DATABASE'] ?? 'myshop';
        $this->collection = $this->mongoClient->selectCollection($databaseName, self::COLLECTION_NAME);
    }

    /**
     * {@inheritDoc}
     */
    public function findEmbeddingByProductId(int $productId): ?array
    {
        try {
            $document = $this->collection->findOne(['product_id' => $productId]);

            if ($document === null) {
                $this->logger->warning('Product embedding not found', [
                    'product_id' => $productId,
                ]);
                return null;
            }

            // Extract embedding vector from BSON
            $embedding = $document['embedding'] ?? null;

            if ($embedding === null) {
                $this->logger->error('Product document missing embedding field', [
                    'product_id' => $productId,
                ]);
                return null;
            }

            // Convert BSON array to PHP array<float>
            $vector = $this->convertToFloatArray($embedding);

            // Validate dimensions
            if (count($vector) !== self::EMBEDDING_DIMENSIONS) {
                $this->logger->error('Invalid product embedding dimensions', [
                    'product_id' => $productId,
                    'expected' => self::EMBEDDING_DIMENSIONS,
                    'actual' => count($vector),
                ]);
                return null;
            }

            return $vector;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve product embedding', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function findEmbeddingsByProductIds(array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        try {
            $cursor = $this->collection->find([
                'product_id' => ['$in' => $productIds]
            ]);

            $embeddings = [];

            foreach ($cursor as $document) {
                $productId = $document['product_id'] ?? null;
                $embedding = $document['embedding'] ?? null;

                if ($productId !== null && $embedding !== null) {
                    $vector = $this->convertToFloatArray($embedding);
                    
                    if (count($vector) === self::EMBEDDING_DIMENSIONS) {
                        $embeddings[(int)$productId] = $vector;
                    }
                }
            }

            $this->logger->info('Retrieved batch product embeddings', [
                'requested' => count($productIds),
                'found' => count($embeddings),
            ]);

            return $embeddings;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to retrieve product embeddings batch', [
                'product_ids' => $productIds,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * {@inheritDoc}
     */
    public function hasEmbedding(int $productId): bool
    {
        try {
            $count = $this->collection->countDocuments(['product_id' => $productId]);
            return $count > 0;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to check product embedding existence', [
                'product_id' => $productId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Convert BSON array to PHP float array
     * 
     * @param mixed $bsonArray BSON array from MongoDB
     * @return array<float> PHP float array
     */
    private function convertToFloatArray(mixed $bsonArray): array
    {
        if (is_array($bsonArray)) {
            return array_map(fn($val) => (float)$val, array_values($bsonArray));
        }

        if ($bsonArray instanceof \MongoDB\Model\BSONArray) {
            return array_map(fn($val) => (float)$val, $bsonArray->getArrayCopy());
        }

        return [];
    }
}
