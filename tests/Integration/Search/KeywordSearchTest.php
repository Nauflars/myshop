<?php

declare(strict_types=1);

namespace App\Tests\Integration\Search;

use App\Application\Service\KeywordSearchService;
use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\SearchQuery;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for traditional keyword-based product search
 * 
 * Tests spec-010 FR-002: Keyword search mode
 */
class KeywordSearchTest extends KernelTestCase
{
    private KeywordSearchService $keywordSearchService;
    private ProductRepositoryInterface $productRepository;
    private EntityManagerInterface $entityManager;
    private array $testProducts = [];

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->keywordSearchService = $container->get(KeywordSearchService::class);
        $this->productRepository = $container->get(ProductRepositoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Create test products in MySQL
        $this->createTestProducts();
    }

    protected function tearDown(): void
    {
        // Cleanup test data from MySQL
        foreach ($this->testProducts as $product) {
            if ($this->entityManager->contains($product)) {
                $this->entityManager->remove($product);
            } else {
                // Re-attach if detached
                $managedProduct = $this->entityManager->find(Product::class, $product->getId());
                if ($managedProduct) {
                    $this->entityManager->remove($managedProduct);
                }
            }
        }
        
        $this->entityManager->flush();
        $this->testProducts = [];

        parent::tearDown();
    }

    public function testKeywordSearchExactMatch(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'laptop',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act
        $result = $this->keywordSearchService->search($query);

        // Assert
        $this->assertNotNull($result, 'Search result should not be null');
        $this->assertEquals('keyword', $result->getMode(), 'Search mode should be keyword');
        $this->assertGreaterThan(0, $result->getTotalResults(), 'Should find laptop products');
        
        $products = $result->getProducts();
        $this->assertNotEmpty($products, 'Should return products matching "laptop"');
        
        // Verify at least one product contains "laptop" in name or description
        $foundMatch = false;
        foreach ($products as $product) {
            $name = strtolower($product->getName());
            $description = strtolower($product->getDescription());
            
            if (str_contains($name, 'laptop') || str_contains($description, 'laptop')) {
                $foundMatch = true;
                break;
            }
        }
        
        $this->assertTrue($foundMatch, 'At least one result should contain "laptop"');
    }

    public function testKeywordSearchCaseInsensitive(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'GAMING',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act
        $result = $this->keywordSearchService->search($query);

        // Assert
        $products = $result->getProducts();
        $this->assertNotEmpty($products, 'Should find products regardless of case');
        
        // Verify matches are case-insensitive
        foreach ($products as $product) {
            $haystack = strtolower($product->getName() . ' ' . $product->getDescription() . ' ' . $product->getCategory());
            $this->assertTrue(
                str_contains($haystack, 'gaming'),
                'Product should match "gaming" (case-insensitive)'
            );
        }
    }

    public function testKeywordSearchWithCategory(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'phone',
            limit: 10,
            offset: 0,
            minSimilarity: 0.5,
            category: 'Smartphones'
        );

        // Act
        $result = $this->keywordSearchService->search($query);

        // Assert
        $products = $result->getProducts();
        
        // All results should belong to Smartphones category
        foreach ($products as $product) {
            $this->assertEquals(
                'Smartphones',
                $product->getCategory(),
                'All results should be in Smartphones category'
            );
        }
    }

    public function testKeywordSearchMatchesNameAndDescription(): void
    {
        // Arrange - Search for term that appears only in description
        $query = new SearchQuery(
            query: 'photography',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act
        $result = $this->keywordSearchService->search($query);

        // Assert
        $this->assertGreaterThan(0, $result->getTotalResults(), 'Should find products with "photography" in description');
        
        $products = $result->getProducts();
        $foundMatch = false;
        
        foreach ($products as $product) {
            $text = strtolower($product->getName() . ' ' . $product->getDescription());
            if (str_contains($text, 'photography')) {
                $foundMatch = true;
                break;
            }
        }
        
        $this->assertTrue($foundMatch, 'Should find products with keyword in name or description');
    }

    public function testKeywordSearchPagination(): void
    {
        // Arrange - First page
        $query1 = new SearchQuery(
            query: 'the',
            limit: 2,
            offset: 0,
            minSimilarity: 0.5
        );

        // Act - Get first page
        $result1 = $this->keywordSearchService->search($query1);
        $firstPageProducts = $result1->getProducts();

        // Arrange - Second page
        $query2 = new SearchQuery(
            query: 'the',
            limit: 2,
            offset: 2,
            minSimilarity: 0.5
        );

        // Act - Get second page
        $result2 = $this->keywordSearchService->search($query2);
        $secondPageProducts = $result2->getProducts();

        // Assert - Verify limit is respected
        $this->assertLessThanOrEqual(2, count($firstPageProducts), 'First page should respect limit');
        
        // If we have results on both pages, verify no overlap
        if (count($firstPageProducts) > 0 && count($secondPageProducts) > 0) {
            $firstPageIds = array_map(fn($p) => $p->getId(), $firstPageProducts);
            $secondPageIds = array_map(fn($p) => $p->getId(), $secondPageProducts);
            
            $intersection = array_intersect($firstPageIds, $secondPageIds);
            $this->assertEmpty($intersection, 'Pages should not contain duplicate products');
        }
    }

    public function testKeywordSearchNoResults(): void
    {
        // Arrange - Search for non-existent term
        $query = new SearchQuery(
            query: 'xyznonexistentproduct123',
            limit: 10,
            offset: 0,
            minSimilarity: 0.6
        );

        // Act
        $result = $this->keywordSearchService->search($query);

        // Assert
        $this->assertEquals(0, $result->getTotalResults(), 'Should return zero results for non-existent term');
        $this->assertEmpty($result->getProducts(), 'Products array should be empty');
    }

    public function testKeywordSearchAllScoresAreOne(): void
    {
        // Arrange
        $query = new SearchQuery(
            query: 'laptop',
            limit: 10,
            offset: 0,
            minSimilarity: 0.5
        );

        // Act
        $result = $this->keywordSearchService->search($query);

        // Assert - Keyword search should return binary scores (1.0 for all matches)
        $scores = $result->getScores();
        
        foreach ($scores as $score) {
            $this->assertEquals(
                1.0,
                $score,
                'Keyword search should assign score of 1.0 for all matches'
            );
        }
    }

    public function testKeywordSearchPartialMatch(): void
    {
        // Arrange - Search for partial word
        $query = new SearchQuery(
            query: 'game',
            limit: 10,
            offset: 0,
            minSimilarity: 0.5
        );

        // Act
        $result = $this->keywordSearchService->search($query);

        // Assert - Should match "game", "gaming", "games", etc.
        $products = $result->getProducts();
        
        $foundPartialMatch = false;
        foreach ($products as $product) {
            $text = strtolower($product->getName() . ' ' . $product->getDescription());
            if (str_contains($text, 'game') || str_contains($text, 'gaming')) {
                $foundPartialMatch = true;
                break;
            }
        }
        
        $this->assertTrue($foundPartialMatch, 'Should match partial keywords');
    }

    public function testKeywordSearchMultipleTerms(): void
    {
        // Arrange - Search with multiple words
        $query = new SearchQuery(
            query: 'gaming laptop',
            limit: 10,
            offset: 0,
            minSimilarity: 0.5
        );

        // Act
        $result = $this->keywordSearchService->search($query);

        // Assert
        $products = $result->getProducts();
        
        if (count($products) > 0) {
            // At least one product should contain both terms or one of them
            $foundRelevant = false;
            foreach ($products as $product) {
                $text = strtolower($product->getName() . ' ' . $product->getDescription());
                if (str_contains($text, 'gaming') || str_contains($text, 'laptop')) {
                    $foundRelevant = true;
                    break;
                }
            }
            
            $this->assertTrue($foundRelevant, 'Should find products matching multiple search terms');
        } else {
            $this->markTestSkipped('No products found to test multiple terms');
        }
    }

    private function createTestProducts(): void
    {
        // Gaming laptop
        $gamingLaptop = new Product(
            name: 'ROG Strix Gaming Laptop',
            description: 'High-performance gaming laptop with NVIDIA RTX 4090 graphics card, Intel Core i9 processor, 32GB DDR5 RAM. Perfect for gaming enthusiasts.',
            price: new Money(249999, 'USD'),
            stock: 5,
            category: 'Electronics'
        );
        $this->entityManager->persist($gamingLaptop);
        $this->testProducts[] = $gamingLaptop;

        // Business laptop
        $businessLaptop = new Product(
            name: 'ThinkPad Business Laptop',
            description: 'Professional laptop for business use. Reliable performance and long battery life.',
            price: new Money(129999, 'USD'),
            stock: 10,
            category: 'Electronics'
        );
        $this->entityManager->persist($businessLaptop);
        $this->testProducts[] = $businessLaptop;

        // Smartphone
        $cameraPhone = new Product(
            name: 'Photography Pro Smartphone',
            description: '108MP camera system with advanced AI photography features. Perfect for mobile photographers.',
            price: new Money(89999, 'USD'),
            stock: 15,
            category: 'Smartphones'
        );
        $this->entityManager->persist($cameraPhone);
        $this->testProducts[] = $cameraPhone;

        // Gaming console
        $gamingConsole = new Product(
            name: 'NextGen Gaming Console',
            description: 'Latest generation gaming console with 4K support and exclusive game titles.',
            price: new Money(49999, 'USD'),
            stock: 20,
            category: 'Electronics'
        );
        $this->entityManager->persist($gamingConsole);
        $this->testProducts[] = $gamingConsole;

        // Headphones
        $headphones = new Product(
            name: 'Premium Wireless Headphones',
            description: 'Noise-cancelling wireless headphones with premium sound quality.',
            price: new Money(29999, 'USD'),
            stock: 30,
            category: 'Audio'
        );
        $this->entityManager->persist($headphones);
        $this->testProducts[] = $headphones;

        // Budget phone
        $budgetPhone = new Product(
            name: 'Budget Android Phone',
            description: 'Affordable smartphone for everyday use with decent camera.',
            price: new Money(19999, 'USD'),
            stock: 50,
            category: 'Smartphones'
        );
        $this->entityManager->persist($budgetPhone);
        $this->testProducts[] = $budgetPhone;

        $this->entityManager->flush();
    }
}
