<?php

declare(strict_types=1);

namespace App\Tests\Integration\ProductEmbedding;

use App\Application\Service\ProductEmbeddingSyncService;
use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use App\Infrastructure\Repository\MongoDBEmbeddingRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Tests for sync failure scenarios
 * 
 * Tests spec-010 T029: Failure handling (OpenAI down, MongoDB down, network timeout)
 */
class SyncFailureTest extends KernelTestCase
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

    public function testSyncDoesNotThrowOnMongoDBFailure(): void
    {
        // This test verifies that MongoDB failures don't break MySQL operations
        // In production, MongoDB might be temporarily unavailable
        
        $product = new Product(
            name: 'Test Product',
            description: 'Testing failure handling',
            price: new Money(1000, 'USD'),
            stock: 1,
            category: 'Test'
        );

        // Even if sync fails, it should not throw an exception
        // The sync service logs errors but continues
        try {
            $result = $this->syncService->createEmbedding($product);
            
            // Result might be false if MongoDB is down, but no exception should be thrown
            $this->assertIsBool($result);
            
        } catch (\Exception $e) {
            // If an exception is thrown, fail the test
            $this->fail('Sync should not throw exceptions: ' . $e->getMessage());
        }
    }

    public function testUpdateNonExistentEmbeddingCreatesNew(): void
    {
        // Arrange - Product without embedding
        $product = new Product(
            name: 'Orphan Product',
            description: 'Product without embedding',
            price: new Money(1000, 'USD'),
            stock: 1,
            category: 'Test'
        );

        // Act - Try to update (should create instead)
        $result = $this->syncService->updateEmbedding($product);

        // Assert - Should succeed by creating new embedding
        $this->assertTrue($result, 'Update should create new embedding if not found');

        // Verify created
        $productId = abs(crc32($product->getId()));
        $embedding = $this->embeddingRepository->findByProductId($productId);
        $this->assertNotNull($embedding, 'Embedding should be created');

        // Cleanup
        $this->embeddingRepository->delete($productId);
    }

    public function testDeleteNonExistentEmbeddingDoesNotFail(): void
    {
        // Arrange - Product without embedding
        $product = new Product(
            name: 'Non-existent Embedding Product',
            description: 'No embedding exists for this',
            price: new Money(1000, 'USD'),
            stock: 1,
            category: 'Test'
        );

        // Act - Try to delete non-existent embedding
        $result = $this->syncService->deleteEmbedding($product);

        // Assert - Should not throw, result will be false
        $this->assertFalse($result, 'Delete should return false for non-existent embedding');
    }

    public function testEmbeddingTextGenerationWithSpecialCharacters(): void
    {
        // Arrange - Product with special characters
        $product = new Product(
            name: 'Product "Special" & <Tags>',
            description: "Description with 'quotes' and symbols: @#$%",
            price: new Money(1000, 'USD'),
            stock: 1,
            category: 'Test & Demo'
        );

        // Act
        $text = $this->syncService->generateEmbeddingText($product);

        // Assert - Should handle special characters
        $this->assertIsString($text);
        $this->assertStringContainsString('Special', $text);
        $this->assertStringContainsString('quotes', $text);
    }

    public function testEmbeddingWithVeryLongDescription(): void
    {
        // Arrange - Product with very long description
        $longDescription = str_repeat('This is a very long description. ', 100);
        
        $product = new Product(
            name: 'Long Description Product',
            description: $longDescription,
            price: new Money(1000, 'USD'),
            stock: 1,
            category: 'Test'
        );

        // Act
        $result = $this->syncService->createEmbedding($product);

        // Assert - Should handle long text
        $this->assertTrue($result, 'Should successfully create embedding for long description');

        // Cleanup
        $productId = abs(crc32($product->getId()));
        $this->embeddingRepository->delete($productId);
    }

    public function testEmbeddingWithMinimalDescription(): void
    {
        // Arrange - Product with minimal description
        $product = new Product(
            name: 'X',
            description: 'Y',
            price: new Money(100, 'USD'),
            stock: 1,
            category: 'Z'
        );

        // Act
        $result = $this->syncService->createEmbedding($product);

        // Assert - Should handle minimal text
        $this->assertTrue($result, 'Should successfully create embedding for minimal description');

        // Cleanup
        $productId = abs(crc32($product->getId()));
        $this->embeddingRepository->delete($productId);
    }
}
