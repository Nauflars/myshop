<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Product;
use App\Domain\Entity\ProductEmbedding;
use App\Domain\Repository\EmbeddingServiceInterface;
use App\Infrastructure\Repository\MongoDBEmbeddingRepository;
use Psr\Log\LoggerInterface;

/**
 * ProductEmbeddingSyncService - Sync product data to MongoDB embeddings
 * 
 * Implements spec-010 FR-004: Auto-sync product embeddings on CRUD operations
 * Generates embeddings from product name + description + category
 * T084: Optimize description text before embedding (remove HTML, truncate)
 */
class ProductEmbeddingSyncService
{
    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 1000;
    
    // T084: OpenAI token limits and text optimization
    private const MAX_DESCRIPTION_LENGTH = 8000; // Safe limit before 8191 token max
    private const MAX_TOKENS_PER_REQUEST = 8191;
    
    // T102: Maximum raw description length before validation failure
    // If description is longer, must be manually shortened before embedding generation
    private const MAX_RAW_DESCRIPTION_LENGTH = 32000; // ~8000 tokens (conservative: 4 chars/token)

    public function __construct(
        private readonly EmbeddingServiceInterface $embeddingService,
        private readonly MongoDBEmbeddingRepository $embeddingRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Generate embedding text from product
     * T084: Optimize description text before embedding
     * T102: Validate description length before processing
     */
    public function generateEmbeddingText(Product $product): string
    {
        $rawDescription = $product->getDescription();
        
        // T102: Validate raw description length
        if (strlen($rawDescription) > self::MAX_RAW_DESCRIPTION_LENGTH) {
            throw new \InvalidArgumentException(sprintf(
                'Product description is too long: %d characters (max: %d). ' .
                'Please reduce the description text before generating embeddings.',
                strlen($rawDescription),
                self::MAX_RAW_DESCRIPTION_LENGTH
            ));
        }
        
        // Clean and optimize description text
        $description = $this->optimizeText($rawDescription);
        
        return sprintf(
            "%s. %s. Category: %s",
            $product->getName(),
            $description,
            $product->getCategory()
        );
    }
    
    /**
     * T084: Optimize text for embedding generation
     * - Remove HTML tags
     * - Truncate to token limit
     * - Remove excessive whitespace
     * - Preserve important product information
     */
    private function optimizeText(string $text): string
    {
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove multiple spaces, tabs, newlines
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim whitespace
        $text = trim($text);
        
        // Truncate if too long (conservative estimate: 1 token ≈ 4 chars)
        if (strlen($text) > self::MAX_DESCRIPTION_LENGTH) {
            $text = substr($text, 0, self::MAX_DESCRIPTION_LENGTH);
            
            // Try to break at a sentence or word boundary
            $lastPeriod = strrpos($text, '.');
            $lastSpace = strrpos($text, ' ');
            
            if ($lastPeriod !== false && $lastPeriod > self::MAX_DESCRIPTION_LENGTH * 0.9) {
                $text = substr($text, 0, $lastPeriod + 1);
            } elseif ($lastSpace !== false && $lastSpace > self::MAX_DESCRIPTION_LENGTH * 0.9) {
                $text = substr($text, 0, $lastSpace);
            }
            
            $text .= '...';
        }
        
        return $text;
    }

    /**
     * Generate embedding vector for product
     */
    public function generateEmbedding(Product $product): array
    {
        $text = $this->generateEmbeddingText($product);

        try {
            return $this->embeddingService->generateEmbedding($text);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate embedding', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create product embedding in MongoDB
     */
    public function createEmbedding(Product $product): bool
    {
        try {
            $this->logger->info('Creating product embedding', [
                'product_id' => $product->getId(),
                'product_name' => $product->getName(),
            ]);

            $embedding = $this->generateEmbedding($product);

            $productEmbedding = new ProductEmbedding(
                productId: $product->getId(), // Use UUID directly
                embedding: $embedding,
                name: $product->getName(),
                description: $product->getDescription(),
                category: $product->getCategory(),
                metadata: [
                    'price_cents' => $product->getPrice()->getAmountInCents(),
                    'currency' => $product->getPrice()->getCurrency(),
                    'stock' => $product->getStock(),
                    'created_at' => $product->getCreatedAt()->format('c'),
                    'updated_at' => $product->getUpdatedAt()->format('c'),
                ]
            );

            $result = $this->embeddingRepository->save($productEmbedding);

            if ($result) {
                $this->logger->info('Product embedding created successfully', [
                    'product_id' => $product->getId(),
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to create product embedding', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage(),
            ]);

            // Don't throw - allow MySQL operation to succeed even if MongoDB fails
            return false;
        }
    }

    /**
     * Update product embedding in MongoDB
     */
    public function updateEmbedding(Product $product): bool
    {
        try {
            $this->logger->info('Updating product embedding', [
                'product_id' => $product->getId(),
                'product_name' => $product->getName(),
            ]);

            // Check if embedding exists
            $existingEmbedding = $this->embeddingRepository->findByProductId(
                $product->getId()
            );

            if ($existingEmbedding === null) {
                // Create if doesn't exist
                $this->logger->warning('Embedding not found for update, creating new', [
                    'product_id' => $product->getId(),
                ]);
                return $this->createEmbedding($product);
            }

            // Generate new embedding
            $embedding = $this->generateEmbedding($product);

            $productEmbedding = new ProductEmbedding(
                productId: $product->getId(), // Use UUID directly
                embedding: $embedding,
                name: $product->getName(),
                description: $product->getDescription(),
                category: $product->getCategory(),
                metadata: [
                    'price_cents' => $product->getPrice()->getAmountInCents(),
                    'currency' => $product->getPrice()->getCurrency(),
                    'stock' => $product->getStock(),
                    'created_at' => $product->getCreatedAt()->format('c'),
                    'updated_at' => $product->getUpdatedAt()->format('c'),
                ]
            );

            $result = $this->embeddingRepository->save($productEmbedding);

            if ($result) {
                $this->logger->info('Product embedding updated successfully', [
                    'product_id' => $product->getId(),
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to update product embedding', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Delete product embedding from MongoDB
     */
    public function deleteEmbedding(Product $product): bool
    {
        try {
            $this->logger->info('Deleting product embedding', [
                'product_id' => $product->getId(),
            ]);

            $result = $this->embeddingRepository->delete(
                $this->convertUuidToInt($product->getId())
            );

            if ($result) {
                $this->logger->info('Product embedding deleted successfully', [
                    'product_id' => $product->getId(),
                ]);
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete product embedding', [
                'product_id' => $product->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Sync with retry logic for transient failures
     */
    public function syncWithRetry(Product $product, string $operation = 'create'): bool
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                return match ($operation) {
                    'create' => $this->createEmbedding($product),
                    'update' => $this->updateEmbedding($product),
                    'delete' => $this->deleteEmbedding($product),
                    default => throw new \InvalidArgumentException("Invalid operation: {$operation}"),
                };

            } catch (\Exception $e) {
                $attempt++;
                $lastException = $e;

                if ($attempt < self::MAX_RETRIES) {
                    $delay = self::RETRY_DELAY_MS * $attempt;
                    usleep($delay * 1000);

                    $this->logger->warning('Retrying sync operation', [
                        'operation' => $operation,
                        'attempt' => $attempt,
                        'product_id' => $product->getId(),
                    ]);
                }
            }
        }

        $this->logger->error('Sync failed after retries', [
            'operation' => $operation,
            'attempts' => $attempt,
            'product_id' => $product->getId(),
            'error' => $lastException?->getMessage(),
        ]);

        return false;
    }

    /**
     * Convert UUID string to integer for MongoDB
     * Uses hash of UUID to generate consistent integer ID
     */
    private function convertUuidToInt(string $uuid): int
    {
        return abs(crc32($uuid));
    }
}
