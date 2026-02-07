<?php

declare(strict_types=1);

namespace App\Tests\Integration\AI;

use App\Application\Service\ProductEmbeddingSyncService;
use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use App\Infrastructure\AI\Tool\SemanticProductSearchTool;
use App\Infrastructure\Repository\MongoDBEmbeddingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for SemanticProductSearchTool
 * 
 * Tests spec-010 T072-T073: VA integration with semantic search
 */
class SemanticProductSearchToolTest extends KernelTestCase
{
    private SemanticProductSearchTool $searchTool;
    private ProductEmbeddingSyncService $syncService;
    private MongoDBEmbeddingRepository $embeddingRepository;
    private EntityManagerInterface $entityManager;
    private array $testProducts = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->searchTool = $container->get(SemanticProductSearchTool::class);
        $this->syncService = $container->get(ProductEmbeddingSyncService::class);
        $this->embeddingRepository = $container->get(MongoDBEmbeddingRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Create test products
        $this->createTestProducts();
    }

    protected function tearDown(): void
    {
        // Cleanup test data
        foreach ($this->testProducts as $product) {
            // Remove from MongoDB
            $productId = $this->convertUuidToInt($product->getId());
            $this->embeddingRepository->delete($productId);
            
            // Remove from MySQL
            $managedProduct = $this->entityManager->find(Product::class, $product->getId());
            if ($managedProduct) {
                $this->entityManager->remove($managedProduct);
            }
        }
        
        $this->entityManager->flush();
        $this->testProducts = [];

        parent::tearDown();
    }

    public function testToolReturnsSuccessWithSemanticMode(): void
    {
        // Act - Natural language query
        $result = ($this->searchTool)(
            query: 'laptop for gaming',
            mode: 'semantic',
            limit: 5
        );

        // Assert
        $this->assertTrue($result['success'], 'Search should succeed');
        $this->assertIsArray($result['products'], 'Should return products array');
        $this->assertArrayHasKey('count', $result, 'Should have count');
        $this->assertArrayHasKey('search_mode', $result, 'Should have search mode');
        $this->assertEquals('semantic', $result['search_mode'], 'Should use semantic mode');
    }

    public function testToolReturnsSuccessWithKeywordMode(): void
    {
        // Act - Keyword search
        $result = ($this->searchTool)(
            query: 'laptop',
            mode: 'keyword',
            limit: 5
        );

        // Assert
        $this->assertTrue($result['success'], 'Search should succeed');
        $this->assertIsArray($result['products'], 'Should return products array');
        $this->assertEquals('keyword', $result['search_mode'], 'Should use keyword mode');
        
        // Keyword search should return results with score 1.0
        if (count($result['products']) > 0) {
            foreach ($result['products'] as $product) {
                $this->assertEquals(1.0, $product['similarity_score'], 'Keyword search should have score 1.0');
            }
        }
    }

    public function testToolDefaultsToSemanticMode(): void
    {
        // Act - No mode specified (should default to semantic)
        $result = ($this->searchTool)(
            query: 'gaming device',
            limit: 5
        );

        // Assert
        $this->assertTrue($result['success'], 'Search should succeed');
        $this->assertEquals('semantic', $result['search_mode'], 'Should default to semantic mode');
    }

    public function testToolReturnsProductsWithCorrectStructure(): void
    {
        // Act
        $result = ($this->searchTool)(
            query: 'laptop',
            mode: 'keyword',
            limit: 5
        );

        // Assert
        $this->assertTrue($result['success']);
        
        if ($result['count'] > 0) {
            $product = $result['products'][0];
            
            // Verify product structure
            $this->assertArrayHasKey('id', $product);
            $this->assertArrayHasKey('name', $product);
            $this->assertArrayHasKey('description', $product);
            $this->assertArrayHasKey('price', $product);
            $this->assertArrayHasKey('currency', $product);
            $this->assertArrayHasKey('stock', $product);
            $this->assertArrayHasKey('category', $product);
            $this->assertArrayHasKey('similarity_score', $product);
            $this->assertArrayHasKey('available', $product);
            
            // Verify data types
            $this->assertIsString($product['id']);
            $this->assertIsString($product['name']);
            $this->assertIsFloat($product['price']);
            $this->assertIsInt($product['stock']);
            $this->assertIsFloat($product['similarity_score']);
            $this->assertIsBool($product['available']);
        }
    }

    public function testToolRespectsLimitParameter(): void
    {
        // Act - Request only 2 results
        $result = ($this->searchTool)(
            query: 'gaming',
            mode: 'keyword',
            limit: 2
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertLessThanOrEqual(2, $result['count'], 'Should respect limit parameter');
    }

    public function testToolFiltersByCategory(): void
    {
        // Act - Search with category filter
        $result = ($this->searchTool)(
            query: 'product',
            mode: 'keyword',
            limit: 10,
            category: 'Electronics'
        );

        // Assert
        $this->assertTrue($result['success']);
        
        // Verify all results belong to specified category
        foreach ($result['products'] as $product) {
            $this->assertEquals('Electronics', $product['category'], 'All results should be in Electronics category');
        }
    }

    public function testToolHandlesEmptyResultsGracefully(): void
    {
        // Act - Search for non-existent product
        $result = ($this->searchTool)(
            query: 'xyznonexistentproduct999',
            mode: 'keyword',
            limit: 5
        );

        // Assert
        $this->assertTrue($result['success'], 'Should still return success for empty results');
        $this->assertEquals(0, $result['count'], 'Count should be 0');
        $this->assertEmpty($result['products'], 'Products array should be empty');
        $this->assertArrayHasKey('message', $result, 'Should have friendly message');
        $this->assertArrayHasKey('suggestions', $result, 'Should provide suggestions');
        $this->assertIsArray($result['suggestions'], 'Suggestions should be an array');
    }

    public function testToolClampsLimitParameterToValidRange(): void
    {
        // Act - Try to request 100 products (should be clamped to 20 max)
        $result = ($this->searchTool)(
            query: 'laptop',
            mode: 'keyword',
            limit: 100
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertLessThanOrEqual(20, $result['count'], 'Limit should be clamped to max 20');
    }

    public function testToolClampsMinSimilarityToValidRange(): void
    {
        // Act - Try invalid similarity values
        $result1 = ($this->searchTool)(
            query: 'laptop',
            mode: 'semantic',
            limit: 5,
            minSimilarity: -0.5 // Invalid, should be clamped to 0.0
        );

        $result2 = ($this->searchTool)(
            query: 'laptop',
            mode: 'semantic',
            limit: 5,
            minSimilarity: 1.5 // Invalid, should be clamped to 1.0
        );

        // Assert - Both should succeed with clamped values
        $this->assertTrue($result1['success'], 'Should handle invalid min similarity gracefully');
        $this->assertTrue($result2['success'], 'Should handle invalid min similarity gracefully');
    }

    public function testToolIncludesExecutionTimeMetric(): void
    {
        // Act
        $result = ($this->searchTool)(
            query: 'laptop',
            mode: 'keyword',
            limit: 5
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('execution_time_ms', $result, 'Should include execution time');
        $this->assertIsFloat($result['execution_time_ms'], 'Execution time should be float');
        $this->assertGreaterThan(0, $result['execution_time_ms'], 'Execution time should be positive');
    }

    public function testToolHandlesInvalidQueryGracefully(): void
    {
        // Act - Empty query (should be caught by SearchQuery validation)
        $result = ($this->searchTool)(
            query: 'x', // Too short (min 2 chars)
            mode: 'keyword',
            limit: 5
        );

        // Assert
        $this->assertFalse($result['success'], 'Should fail for invalid query');
        $this->assertArrayHasKey('error', $result, 'Should include error message');
        $this->assertEquals(0, $result['count'], 'Count should be 0 on error');
    }

    public function testToolFormatsMessageAppropriately(): void
    {
        // Act
        $result = ($this->searchTool)(
            query: 'laptop',
            mode: 'semantic',
            limit: 5
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('message', $result, 'Should include message for VA');
        $this->assertIsString($result['message'], 'Message should be string');
        $this->assertStringContainsString('laptop', $result['message'], 'Message should mention query');
    }

    public function testToolWithUserIdParameter(): void
    {
        // Act - Include user ID for context enrichment
        $testUserId = 'test-customer-' . time();
        
        $result = ($this->searchTool)(
            query: 'laptop',
            mode: 'semantic',
            limit: 5,
            userId: $testUserId
        );

        // Assert
        $this->assertTrue($result['success'], 'Search with user ID should succeed');
        // Context tracking happens internally, just verify no errors
    }

    public function testToolIndicatesWhenMoreResultsAvailable(): void
    {
        // Arrange - Create many products
        for ($i = 0; $i < 10; $i++) {
            $product = new Product(
                name: "Test Product {$i}",
                description: 'Test description for gaming laptop',
                price: new Money(99999, 'USD'),
                stock: 5,
                category: 'Electronics'
            );
            $this->entityManager->persist($product);
            $this->testProducts[] = $product;
        }
        $this->entityManager->flush();

        // Act - Request only 3 results
        $result = ($this->searchTool)(
            query: 'gaming',
            mode: 'keyword',
            limit: 3
        );

        // Assert
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('has_more', $result, 'Should indicate if more results available');
        $this->assertIsBool($result['has_more'], 'has_more should be boolean');
    }

    private function createTestProducts(): void
    {
        // Gaming laptop
        $gamingLaptop = new Product(
            name: 'Gaming Laptop Pro',
            description: 'High-performance gaming laptop with RTX 4090 GPU, perfect for gaming enthusiasts',
            price: new Money(249999, 'USD'),
            stock: 5,
            category: 'Electronics'
        );
        $this->entityManager->persist($gamingLaptop);
        $this->testProducts[] = $gamingLaptop;

        // Business laptop
        $businessLaptop = new Product(
            name: 'Business Laptop',
            description: 'Professional laptop for business and productivity work',
            price: new Money(129999, 'USD'),
            stock: 10,
            category: 'Electronics'
        );
        $this->entityManager->persist($businessLaptop);
        $this->testProducts[] = $businessLaptop;

        // Streaming gear
        $streamingMic = new Product(
            name: 'Streaming Microphone',
            description: 'Professional microphone for streaming and content creation',
            price: new Money(19999, 'USD'),
            stock: 15,
            category: 'Audio'
        );
        $this->entityManager->persist($streamingMic);
        $this->testProducts[] = $streamingMic;

        $this->entityManager->flush();

        // Sync embeddings for semantic search
        foreach ($this->testProducts as $product) {
            $this->syncService->createEmbedding($product);
        }

        // Give time for embeddings to process
        sleep(1);
    }

    private function convertUuidToInt(string $uuid): int
    {
        return crc32($uuid);
    }
}
