<?php

declare(strict_types=1);

namespace App\Tests\Integration\Search;

use App\Application\Service\ProductEmbeddingSyncService;
use App\Application\Service\SemanticSearchService;
use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\SearchQuery;
use App\Infrastructure\Repository\MongoDBEmbeddingRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for semantic product search
 * 
 * Tests spec-010 FR-001: Semantic search with AI embeddings
 */
class SemanticSearchTest extends KernelTestCase
{
    private SemanticSearchService $searchService;
    private ProductEmbeddingSyncService $syncService;
    private ProductRepositoryInterface $productRepository;
    private MongoDBEmbeddingRepository $embeddingRepository;
    private array $testProducts = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->searchService = $container->get(SemanticSearchService::class);
        $this->syncService = $container->get(ProductEmbeddingSyncService::class);
        $this->productRepository = $container->get(ProductRepositoryInterface::class);
        $this->embeddingRepository = $container->get(MongoDBEmbeddingRepository::class);

        // Create and sync test products
        $this->createTestProducts();
    }

    protected function tearDown(): void
    {
        // Cleanup test data
        foreach ($this->testProducts as $product) {
            $productId = $this->convertUuidToInt($product->getId());
            $this->embeddingRepository->delete($productId);
            
            // Note: In a real scenario, you might want to delete from MySQL too
            // but that requires EntityManager which may complicate the test setup
        }

        $this->testProducts = [];
        parent::tearDown();
    }

    public function testSemanticSearch(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'laptop for gaming',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act
        $result = $this->searchService->search($query);

        // Assert
        $this->assertNotNull($result, 'Search result should not be null');
        $this->assertEquals('semantic', $result->getMode(), 'Search mode should be semantic');
        $this->assertGreaterThan(0, $result->getTotalResults(), 'Should find at least one product');
        
        // Verify results are ranked by similarity
        $products = $result->getProducts();
        $scores = $result->getScores();
        
        $this->assertNotEmpty($products, 'Should return product results');
        
        // Check that first result has highest score
        if (count($products) > 1) {
            $firstProductId = $products[0]->getId();
            $secondProductId = $products[1]->getId();
            
            $firstScore = $scores[$firstProductId] ?? 0.0;
            $secondScore = $scores[$secondProductId] ?? 0.0;
            
            $this->assertGreaterThanOrEqual(
                $secondScore,
                $firstScore,
                'Results should be sorted by similarity score descending'
            );
        }
        
        // Verify all scores are within valid range
        foreach ($scores as $score) {
            $this->assertGreaterThanOrEqual(0.6, $score, 'All scores should be >= minSimilarity');
            $this->assertLessThanOrEqual(1.0, $score, 'All scores should be <= 1.0');
        }
    }

    public function testSemanticSearchWithCategory(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'device with camera',
            limit: 10,
            offset: 0,
            minSimilarity: 0.5,
            category: 'Smartphones'
        );

        // Act
        $result = $this->searchService->search($query);

        // Assert
        $this->assertNotNull($result);
        
        // Verify all results belong to the specified category
        $products = $result->getProducts();
        foreach ($products as $product) {
            $this->assertEquals(
                'Smartphones',
                $product->getCategory(),
                'All results should belong to Smartphones category'
            );
        }
    }

    public function testSemanticSearchPagination(): void
    {
        // Arrange - First page
        $query1 = new SearchQuery(
            query: 'electronic device',
            limit: 2,
            offset: 0,
            minSimilarity: 0.5
        );

        // Act - Get first page
        $result1 = $this->searchService->search($query1);
        $firstPageProducts = $result1->getProducts();

        // Arrange - Second page
        $query2 = new SearchQuery(
            query: 'electronic device',
            limit: 2,
            offset: 2,
            minSimilarity: 0.5
        );

        // Act - Get second page
        $result2 = $this->searchService->search($query2);
        $secondPageProducts = $result2->getProducts();

        // Assert
        $this->assertCount(2, $firstPageProducts, 'First page should have 2 results (limit)');
        
        // Verify no overlap between pages
        if (count($firstPageProducts) > 0 && count($secondPageProducts) > 0) {
            $firstPageIds = array_map(fn($p) => $p->getId(), $firstPageProducts);
            $secondPageIds = array_map(fn($p) => $p->getId(), $secondPageProducts);
            
            $intersection = array_intersect($firstPageIds, $secondPageIds);
            $this->assertEmpty($intersection, 'Pages should not overlap');
        }
    }

    public function testMinSimilarityThreshold(): void
    {
        // Arrange - High threshold
        $query = new SearchQuery(
            query: 'completely unrelated random text xyz',
            limit: 10,
            offset: 0,
            minSimilarity: 0.9 // Very high threshold
        );

        // Act
        $result = $this->searchService->search($query);

        // Assert - Should return empty or very few results
        $scores = $result->getScores();
        
        foreach ($scores as $score) {
            $this->assertGreaterThanOrEqual(
                0.9,
                $score,
                'All returned scores should meet the minimum threshold'
            );
        }
    }

    public function testSemanticSearchReturnsRelevantResults(): void
    {
        // Arrange - Search for gaming-related products
        $query = new SearchQuery(
            query: 'gaming laptop with powerful graphics',
            limit: 5,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act
        $result = $this->searchService->search($query);

        // Assert
        $products = $result->getProducts();
        $this->assertNotEmpty($products, 'Should find relevant products');
        
        // Verify at least one result contains relevant keywords or is categorized appropriately
        $foundRelevantProduct = false;
        foreach ($products as $product) {
            $name = strtolower($product->getName());
            $description = strtolower($product->getDescription());
            $category = strtolower($product->getCategory());
            
            if (
                str_contains($name, 'gaming') ||
                str_contains($description, 'gaming') ||
                str_contains($category, 'electronics') ||
                str_contains($description, 'graphics')
            ) {
                $foundRelevantProduct = true;
                break;
            }
        }
        
        $this->assertTrue(
            $foundRelevantProduct,
            'At least one result should be semantically relevant to gaming/graphics'
        );
    }

    public function testEmptyQueryHandling(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Query must be at least 2 characters');

        new SearchQuery(
            query: '',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );
    }

    private function createTestProducts(): void
    {
        // Gaming laptop
        $gamingLaptop = new Product(
            name: 'ROG Strix Gaming Laptop',
            description: 'High-performance gaming laptop with NVIDIA RTX 4090 graphics card, Intel Core i9 processor, 32GB DDR5 RAM. Perfect for gaming enthusiasts and content creators.',
            price: new Money(249999, 'USD'),
            stock: 5,
            category: 'Electronics'
        );
        $this->testProducts[] = $gamingLaptop;
        $this->syncService->createEmbedding($gamingLaptop);

        // Business laptop
        $businessLaptop = new Product(
            name: 'ThinkPad Business Laptop',
            description: 'Professional laptop for business use. Reliable performance, long battery life, lightweight design for travel.',
            price: new Money(129999, 'USD'),
            stock: 10,
            category: 'Electronics'
        );
        $this->testProducts[] = $businessLaptop;
        $this->syncService->createEmbedding($businessLaptop);

        // Smartphone with camera
        $cameraPhone = new Product(
            name: 'Photography Pro Smartphone',
            description: '108MP camera system with advanced AI photography features. Perfect for mobile photographers and content creators.',
            price: new Money(89999, 'USD'),
            stock: 15,
            category: 'Smartphones'
        );
        $this->testProducts[] = $cameraPhone;
        $this->syncService->createEmbedding($cameraPhone);

        // Gaming console
        $gamingConsole = new Product(
            name: 'NextGen Gaming Console',
            description: 'Latest generation gaming console with 4K support, ray tracing, and exclusive game titles.',
            price: new Money(49999, 'USD'),
            stock: 20,
            category: 'Electronics'
        );
        $this->testProducts[] = $gamingConsole;
        $this->syncService->createEmbedding($gamingConsole);

        // Wireless headphones
        $headphones = new Product(
            name: 'Premium Wireless Headphones',
            description: 'Noise-cancelling wireless headphones with premium sound quality and 30-hour battery life.',
            price: new Money(29999, 'USD'),
            stock: 30,
            category: 'Audio'
        );
        $this->testProducts[] = $headphones;
        $this->syncService->createEmbedding($headphones);

        // Give embeddings time to process (in real scenario, this would be async)
        sleep(1);
    }

    private function convertUuidToInt(string $uuid): int
    {
        return crc32($uuid);
    }
}
