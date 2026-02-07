<?php

declare(strict_types=1);

namespace App\Tests\Integration\ProductEmbedding;

use App\Application\Service\ProductEmbeddingSyncService;
use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use App\Infrastructure\Repository\MongoDBEmbeddingRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for product embedding sync lifecycle
 * 
 * Tests spec-010 FR-004: Auto-sync on product CRUD operations
 */
class ProductEmbeddingSyncTest extends KernelTestCase
{
    private ProductEmbeddingSyncService $syncService;
    private MongoDBEmbeddingRepository $embeddingRepository;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->syncService = $container->get(ProductEmbeddingSyncService::class);
        $this->embeddingRepository = $container->get(MongoDBEmbeddingRepository::class);
    }

    public function testCreateEmbedding(): void
    {
        // Arrange
        $product = new Product(
            name: 'Test Gaming Laptop',
            description: 'High-performance laptop with RTX 4090 GPU and 32GB RAM',
            price: new Money(199999, 'USD'),
            stock: 10,
            category: 'Electronics'
        );

        // Act
        $result = $this->syncService->createEmbedding($product);

        // Assert
        $this->assertTrue($result, 'Embedding creation should succeed');

        // Verify in MongoDB
        $productId = $this->convertUuidToInt($product->getId());
        $storedEmbedding = $this->embeddingRepository->findByProductId($productId);

        $this->assertNotNull($storedEmbedding, 'Embedding should be stored in MongoDB');
        $this->assertEquals($product->getName(), $storedEmbedding->getName());
        $this->assertEquals($product->getDescription(), $storedEmbedding->getDescription());
        $this->assertEquals($product->getCategory(), $storedEmbedding->getCategory());
        $this->assertCount(1536, $storedEmbedding->getEmbedding(), 'Embedding should have 1536 dimensions');

        // Cleanup
        $this->embeddingRepository->delete($productId);
    }

    public function testUpdateEmbedding(): void
    {
        // Arrange - Create initial embedding
        $product = new Product(
            name: 'Test Product',
            description: 'Original description',
            price: new Money(9999, 'USD'),
            stock: 5,
            category: 'Test'
        );

        $this->syncService->createEmbedding($product);
        $productId = $this->convertUuidToInt($product->getId());

        // Act - Update product and sync
        $product->setDescription('Updated description with new features');
        $result = $this->syncService->updateEmbedding($product);

        // Assert
        $this->assertTrue($result, 'Embedding update should succeed');

        $updatedEmbedding = $this->embeddingRepository->findByProductId($productId);
        $this->assertNotNull($updatedEmbedding);
        $this->assertEquals('Updated description with new features', $updatedEmbedding->getDescription());

        // Cleanup
        $this->embeddingRepository->delete($productId);
    }

    public function testDeleteEmbedding(): void
    {
        // Arrange - Create embedding
        $product = new Product(
            name: 'Test Product to Delete',
            description: 'This will be deleted',
            price: new Money(5000, 'USD'),
            stock: 1,
            category: 'Test'
        );

        $this->syncService->createEmbedding($product);
        $productId = $this->convertUuidToInt($product->getId());

        // Verify it exists
        $this->assertNotNull($this->embeddingRepository->findByProductId($productId));

        // Act - Delete
        $result = $this->syncService->deleteEmbedding($product);

        // Assert
        $this->assertTrue($result, 'Embedding deletion should succeed');
        $this->assertNull(
            $this->embeddingRepository->findByProductId($productId),
            'Embedding should be removed from MongoDB'
        );
    }

    public function testGenerateEmbeddingText(): void
    {
        // Arrange
        $product = new Product(
            name: 'Wireless Mouse',
            description: 'Ergonomic wireless mouse with 6 buttons',
            price: new Money(2999, 'USD'),
            stock: 50,
            category: 'Accessories'
        );

        // Act
        $text = $this->syncService->generateEmbeddingText($product);

        // Assert
        $this->assertStringContainsString('Wireless Mouse', $text);
        $this->assertStringContainsString('Ergonomic wireless mouse', $text);
        $this->assertStringContainsString('Accessories', $text);
    }

    public function testSyncWithRetrySuccess(): void
    {
        // Arrange
        $product = new Product(
            name: 'Retry Test Product',
            description: 'Testing retry logic',
            price: new Money(1000, 'USD'),
            stock: 1,
            category: 'Test'
        );

        // Act
        $result = $this->syncService->syncWithRetry($product, 'create');

        // Assert
        $this->assertTrue($result, 'Sync with retry should succeed');

        // Cleanup
        $productId = $this->convertUuidToInt($product->getId());
        $this->embeddingRepository->delete($productId);
    }

    public function testMultipleProductsSync(): void
    {
        // Arrange
        $products = [];
        for ($i = 1; $i <= 5; $i++) {
            $products[] = new Product(
                name: "Test Product {$i}",
                description: "Description for product {$i}",
                price: new Money(1000 * $i, 'USD'),
                stock: $i * 10,
                category: 'Test'
            );
        }

        // Act - Create embeddings
        $results = [];
        foreach ($products as $product) {
            $results[] = $this->syncService->createEmbedding($product);
        }

        // Assert
        foreach ($results as $result) {
            $this->assertTrue($result, 'Each product sync should succeed');
        }

        // Verify all stored
        foreach ($products as $product) {
            $productId = $this->convertUuidToInt($product->getId());
            $embedding = $this->embeddingRepository->findByProductId($productId);
            $this->assertNotNull($embedding, "Product {$product->getName()} should be stored");
        }

        // Cleanup
        foreach ($products as $product) {
            $productId = $this->convertUuidToInt($product->getId());
            $this->embeddingRepository->delete($productId);
        }
    }

    private function convertUuidToInt(string $uuid): int
    {
        return abs(crc32($uuid));
    }
}
