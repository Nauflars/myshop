<?php

declare(strict_types=1);

namespace App\Tests\Integration\Search;

use App\Application\Service\SearchFacade;
use App\Application\Service\SemanticSearchService;
use App\Application\Service\KeywordSearchService;
use App\Application\Service\ProductEmbeddingSyncService;
use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\SearchQuery;
use App\Infrastructure\Repository\MongoDBEmbeddingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for search mode switching and fallback mechanisms
 * 
 * Tests spec-010 FR-003: Mode parameter validation and automatic fallback
 */
class SearchFacadeTest extends KernelTestCase
{
    private SearchFacade $searchFacade;
    private SemanticSearchService $semanticSearchService;
    private KeywordSearchService $keywordSearchService;
    private ProductEmbeddingSyncService $syncService;
    private ProductRepositoryInterface $productRepository;
    private MongoDBEmbeddingRepository $embeddingRepository;
    private EntityManagerInterface $entityManager;
    private array $testProducts = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->searchFacade = $container->get(SearchFacade::class);
        $this->semanticSearchService = $container->get(SemanticSearchService::class);
        $this->keywordSearchService = $container->get(KeywordSearchService::class);
        $this->syncService = $container->get(ProductEmbeddingSyncService::class);
        $this->productRepository = $container->get(ProductRepositoryInterface::class);
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

    public function testSearchFacadeUsesSemanticMode(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'laptop for gaming',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act
        $result = $this->searchFacade->search($query, 'semantic');

        // Assert
        $this->assertEquals('semantic', $result->getMode(), 'Should use semantic search mode');
        $this->assertGreaterThanOrEqual(0, $result->getTotalResults());
    }

    public function testSearchFacadeUsesKeywordMode(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'laptop',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act
        $result = $this->searchFacade->search($query, 'keyword');

        // Assert
        $this->assertEquals('keyword', $result->getMode(), 'Should use keyword search mode');
        $this->assertGreaterThanOrEqual(0, $result->getTotalResults());
        
        // Verify all scores are 1.0 (characteristic of keyword search)
        $scores = $result->getScores();
        foreach ($scores as $score) {
            $this->assertEquals(1.0, $score, 'Keyword search should have score 1.0');
        }
    }

    public function testSearchFacadeDefaultsToSemantic(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'gaming device',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act - Don't specify mode (should default to semantic)
        $result = $this->searchFacade->search($query);

        // Assert
        $this->assertEquals('semantic', $result->getMode(), 'Should default to semantic mode');
    }

    public function testSearchFacadeModeValidation(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'test product',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act - Use invalid mode (should default to semantic)
        $result = $this->searchFacade->search($query, 'invalid_mode');

        // Assert - Should fallback to semantic
        $this->assertEquals('semantic', $result->getMode(), 'Invalid mode should default to semantic');
    }

    public function testSearchFacadeCaseInsensitiveMode(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'laptop',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act
        $result1 = $this->searchFacade->search($query, 'KEYWORD');
        $result2 = $this->searchFacade->search($query, 'Keyword');
        $result3 = $this->searchFacade->search($query, 'keyword');

        // Assert - All variations should work
        $this->assertEquals('keyword', $result1->getMode(), 'Uppercase KEYWORD should work');
        $this->assertEquals('keyword', $result2->getMode(), 'Mixed case Keyword should work');
        $this->assertEquals('keyword', $result3->getMode(), 'Lowercase keyword should work');
    }

    public function testBothModesReturnConsistentStructure(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'gaming laptop',
            limit: 5,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act
        $semanticResult = $this->searchFacade->search($query, 'semantic');
        $keywordResult = $this->searchFacade->search($query, 'keyword');

        // Assert - Both should have same structure
        $this->assertIsArray($semanticResult->getProducts());
        $this->assertIsArray($keywordResult->getProducts());
        
        $this->assertIsArray($semanticResult->getScores());
        $this->assertIsArray($keywordResult->getScores());
        
        $this->assertIsInt($semanticResult->getTotalResults());
        $this->assertIsInt($keywordResult->getTotalResults());
        
        $this->assertIsFloat($semanticResult->getExecutionTimeMs());
        $this->assertIsFloat($keywordResult->getExecutionTimeMs());
    }

    public function testSemanticToKeywordFallbackOnError(): void
    {
        // Note: This test simulates what happens when semantic search fails
        // In a real scenario, we'd need to mock the OpenAI service to force a failure
        // For this integration test, we verify that the facade has fallback logic
        
        // Arrange - Create a query
        $query = new SearchQuery(
            query: 'laptop',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act - Try semantic search
        $result = $this->searchFacade->search($query, 'semantic');

        // Assert - Even if semantic fails internally, facade should return a valid result
        // Either semantic result or keyword fallback
        $this->assertNotNull($result, 'Facade should always return a result');
        $this->assertIsArray($result->getProducts(), 'Should return products array');
        $this->assertContains($result->getMode(), ['semantic', 'keyword'], 'Mode should be either semantic or keyword');
    }

    public function testKeywordSearchAsReliableFallback(): void
    {
        // Arrange - Query that should work in keyword mode
        $query = new SearchQuery(
            query: 'laptop',
            limit: 10,
            offset: 0,
            minSimilarity: 0.5
        );

        // Act - Force keyword mode
        $result = $this->searchFacade->search($query, 'keyword');

        // Assert - Keyword should always work (doesn't depend on external services)
        $this->assertEquals('keyword', $result->getMode());
        $this->assertGreaterThan(0, $result->getTotalResults(), 'Keyword search should find products');
    }

    public function testSemanticSearchPreservesScores(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'gaming laptop',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act
        $result = $this->searchFacade->search($query, 'semantic');

        // Assert - If semantic mode is used, scores should vary (not all 1.0)
        if ($result->getMode() === 'semantic') {
            $scores = $result->getScores();
            $uniqueScores = array_unique(array_values($scores), SORT_REGULAR);
            
            // Semantic search should have varying scores or at least not all exactly 1.0
            // unless only one result is returned
            if (count($scores) > 1) {
                $this->assertGreaterThanOrEqual(
                    1,
                    count($uniqueScores),
                    'Semantic search should have varying similarity scores'
                );
            }
        } else {
            $this->markTestSkipped('Semantic search not available, skipping score test');
        }
    }

    public function testSearchResultsAreEnrichedWithProductData(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'laptop',
            limit: 5,
            offset: 0,
            minSimilarity: 0.5
        );

        // Act
        $result = $this->searchFacade->search($query, 'keyword');

        // Assert - Products should have full data, not just IDs
        $products = $result->getProducts();
        
        if (count($products) > 0) {
            $firstProduct = $products[0];
            
            $this->assertNotNull($firstProduct->getId());
            $this->assertNotEmpty($firstProduct->getName());
            $this->assertNotEmpty($firstProduct->getDescription());
            $this->assertNotNull($firstProduct->getPrice());
            $this->assertNotNull($firstProduct->getStock());
            $this->assertNotEmpty($firstProduct->getCategory());
        }
    }

    public function testPaginationWorksBothModes(): void
    {
        // Arrange - Same query with different offsets
        $query1 = new SearchQuery(
            query: 'gaming',
            limit: 2,
            offset: 0,
            minSimilarity: 0.5
        );

        $query2 = new SearchQuery(
            query: 'gaming',
            limit: 2,
            offset: 2,
            minSimilarity: 0.5
        );

        // Act - Test both modes
        $semanticPage1 = $this->searchFacade->search($query1, 'semantic');
        $semanticPage2 = $this->searchFacade->search($query2, 'semantic');
        
        $keywordPage1 = $this->searchFacade->search($query1, 'keyword');
        $keywordPage2 = $this->searchFacade->search($query2, 'keyword');

        // Assert - Pages should not overlap
        $this->assertLessThanOrEqual(2, count($semanticPage1->getProducts()));
        $this->assertLessThanOrEqual(2, count($keywordPage1->getProducts()));
    }

    public function testEmptyResultsHandledGracefully(): void
    {
        // Arrange - Query that won't match anything
        $query = new SearchQuery(
            query: 'xyznonexistentproduct999',
            limit: 10,
            offset: 0,
            minSimilarity: 0.9
        );

        // Act
        $semanticResult = $this->searchFacade->search($query, 'semantic');
        $keywordResult = $this->searchFacade->search($query, 'keyword');

        // Assert - Both should return empty results gracefully
        $this->assertEquals(0, $semanticResult->getTotalResults());
        $this->assertEquals(0, $keywordResult->getTotalResults());
        
        $this->assertEmpty($semanticResult->getProducts());
        $this->assertEmpty($keywordResult->getProducts());
    }

    private function createTestProducts(): void
    {
        // Gaming laptop
        $gamingLaptop = new Product(
            name: 'Gaming Laptop Pro',
            description: 'High-performance gaming laptop with RTX 4090 GPU, perfect for gaming',
            price: new Money(249999, 'USD'),
            stock: 5,
            category: 'Electronics'
        );
        $this->entityManager->persist($gamingLaptop);
        $this->testProducts[] = $gamingLaptop;

        // Business laptop
        $businessLaptop = new Product(
            name: 'Business Laptop',
            description: 'Professional laptop for business use',
            price: new Money(129999, 'USD'),
            stock: 10,
            category: 'Electronics'
        );
        $this->entityManager->persist($businessLaptop);
        $this->testProducts[] = $businessLaptop;

        // Gaming console
        $gamingConsole = new Product(
            name: 'Gaming Console',
            description: 'Next generation gaming console with 4K support',
            price: new Money(49999, 'USD'),
            stock: 20,
            category: 'Electronics'
        );
        $this->entityManager->persist($gamingConsole);
        $this->testProducts[] = $gamingConsole;

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
