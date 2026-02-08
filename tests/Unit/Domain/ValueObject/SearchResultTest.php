<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use App\Domain\ValueObject\SearchResult;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SearchResult value object
 * 
 * Tests result encapsulation, scoring, and metadata
 * Implements spec-010 T105
 */
class SearchResultTest extends TestCase
{
    private function createMockProduct(string $id, string $name): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getName')->willReturn($name);
        $product->method('getDescription')->willReturn("Description for $name");
        $product->method('getPrice')->willReturn(new Money(9999, 'USD'));
        $product->method('getCategory')->willReturn('Electronics');
        $product->method('getStock')->willReturn(10);
        $product->method('isInStock')->willReturn(true);

        return $product;
    }

    public function testCreateWithProducts(): void
    {
        $product1 = $this->createMockProduct('p1', 'Product 1');
        $product2 = $this->createMockProduct('p2', 'Product 2');

        $products = [$product1, $product2];
        $scores = ['p1' => 0.9, 'p2' => 0.7];

        $result = new SearchResult(
            products: $products,
            scores: $scores,
            mode: 'semantic',
            totalResults: 2,
            executionTimeMs: 145.5
        );

        $this->assertSame($products, $result->getProducts());
        $this->assertSame($scores, $result->getScores());
        $this->assertSame('semantic', $result->getMode());
        $this->assertSame(2, $result->getTotalResults());
        $this->assertSame(145.5, $result->getExecutionTimeMs());
        $this->assertFalse($result->isEmpty());
        $this->assertSame(2, $result->count());
    }

    public function testCreateEmptyResult(): void
    {
        $result = new SearchResult(
            products: [],
            scores: [],
            mode: 'semantic',
            totalResults: 0,
            executionTimeMs: 50.0
        );

        $this->assertEmpty($result->getProducts());
        $this->assertEmpty($result->getScores());
        $this->assertTrue($result->isEmpty());
        $this->assertSame(0, $result->count());
    }

    public function testGetScoreForProduct(): void
    {
        $product1 = $this->createMockProduct('p1', 'Product 1');
        $product2 = $this->createMockProduct('p2', 'Product 2');

        $products = [$product1, $product2];
        $scores = ['p1' => 0.95, 'p2' => 0.82];

        $result = new SearchResult($products, $scores, 'semantic', 2, 100.0);

        $this->assertSame(0.95, $result->getScoreForProduct($product1));
        $this->assertSame(0.82, $result->getScoreForProduct($product2));
    }

    public function testGetScoreForProductNotInResults(): void
    {
        $product1 = $this->createMockProduct('p1', 'Product 1');
        $product2 = $this->createMockProduct('p2', 'Product 2');
        $product3 = $this->createMockProduct('p3', 'Product 3');

        $products = [$product1, $product2];
        $scores = ['p1' => 0.9, 'p2' => 0.8];

        $result = new SearchResult($products, $scores, 'semantic', 2, 100.0);

        $this->assertNull($result->getScoreForProduct($product3));
    }

    public function testKeywordMode(): void
    {
        $product = $this->createMockProduct('p1', 'Product 1');
        $result = new SearchResult(
            products: [$product],
            scores: [],
            mode: 'keyword',
            totalResults: 1,
            executionTimeMs: 25.0
        );

        $this->assertSame('keyword', $result->getMode());
    }

    public function testToArrayWithProducts(): void
    {
        $product1 = $this->createMockProduct('p1', 'Gaming Laptop');
        $product2 = $this->createMockProduct('p2', 'Office Laptop');

        $products = [$product1, $product2];
        $scores = ['p1' => 0.95, 'p2' => 0.75];

        $result = new SearchResult($products, $scores, 'semantic', 2, 234.5);

        $array = $result->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('products', $array);
        $this->assertArrayHasKey('metadata', $array);

        $this->assertCount(2, $array['products']);
        
        // Check first product structure
        $this->assertSame('p1', $array['products'][0]['id']);
        $this->assertSame('Gaming Laptop', $array['products'][0]['name']);
        $this->assertSame('Description for Gaming Laptop', $array['products'][0]['description']);
        $this->assertSame(9999, $array['products'][0]['price']['amount']);
        $this->assertSame('USD', $array['products'][0]['price']['currency']);
        $this->assertSame('Electronics', $array['products'][0]['category']);
        $this->assertSame(10, $array['products'][0]['stock']);
        $this->assertTrue($array['products'][0]['in_stock']);
        $this->assertSame(0.95, $array['products'][0]['similarity_score']);

        // Check metadata
        $this->assertSame('semantic', $array['metadata']['mode']);
        $this->assertSame(2, $array['metadata']['total_results']);
        $this->assertSame(2, $array['metadata']['returned_results']);
    }

    public function testToArrayWithEmptyResult(): void
    {
        $result = new SearchResult([], [], 'semantic', 0, 45.0);

        $array = $result->toArray();

        $this->assertEmpty($array['products']);
        $this->assertSame(0, $array['metadata']['total_results']);
        $this->assertSame(0, $array['metadata']['returned_results']);
    }

    public function testToArrayProductWithoutScore(): void
    {
        $product = $this->createMockProduct('p1', 'Product 1');
        $result = new SearchResult(
            products: [$product],
            scores: [], // No scores for keyword mode
            mode: 'keyword',
            totalResults: 1,
            executionTimeMs: 30.0
        );

        $array = $result->toArray();

        $this->assertNull($array['products'][0]['similarity_score']);
    }

    public function testExecutionTimePrecision(): void
    {
        $result = new SearchResult([], [], 'semantic', 0, 123.456789);

        $this->assertSame(123.456789, $result->getExecutionTimeMs());
    }

    public function testLargeResultSet(): void
    {
        $products = [];
        $scores = [];
        
        for ($i = 1; $i <= 50; $i++) {
            $id = "p$i";
            $products[] = $this->createMockProduct($id, "Product $i");
            $scores[$id] = 0.9 - ($i * 0.01); // Decreasing scores
        }

        $result = new SearchResult($products, $scores, 'semantic', 50, 500.0);

        $this->assertSame(50, $result->count());
        $this->assertSame(50, $result->getTotalResults());
        $this->assertCount(50, $result->getProducts());
        $this->assertCount(50, $result->getScores());
    }

    public function testPartialResults(): void
    {
        $product = $this->createMockProduct('p1', 'Product 1');
        
        // Total results is 100, but only 1 returned
        $result = new SearchResult(
            products: [$product],
            scores: ['p1' => 0.8],
            mode: 'semantic',
            totalResults: 100,
            executionTimeMs: 150.0
        );

        $this->assertSame(1, $result->count());
        $this->assertSame(100, $result->getTotalResults());

        $array = $result->toArray();
        $this->assertSame(100, $array['metadata']['total_results']);
        $this->assertSame(1, $array['metadata']['returned_results']);
    }

    public function testZeroExecutionTime(): void
    {
        $result = new SearchResult([], [], 'keyword', 0, 0.0);
        $this->assertSame(0.0, $result->getExecutionTimeMs());
    }

    /**
     * Test spec-010 FR-004: Results ranked by similarity score
     */
    public function testScoresAreSortedByRelevance(): void
    {
        $product1 = $this->createMockProduct('p1', 'Most Relevant');
        $product2 = $this->createMockProduct('p2', 'Medium Relevant');
        $product3 = $this->createMockProduct('p3', 'Least Relevant');

        $products = [$product1, $product2, $product3];
        $scores = ['p1' => 0.95, 'p2' => 0.75, 'p3' => 0.65];

        $result = new SearchResult($products, $scores, 'semantic', 3, 200.0);

        $array = $result->toArray();
        
        // Verify first product has highest score
        $this->assertSame(0.95, $array['products'][0]['similarity_score']);
        $this->assertSame('Most Relevant', $array['products'][0]['name']);
    }

    /**
     * Test immutability of SearchResult value object
     */
    public function testResultIsImmutable(): void
    {
        $product = $this->createMockProduct('p1', 'Product 1');
        $result = new SearchResult([$product], ['p1' => 0.9], 'semantic', 1, 100.0);

        $reflectionClass = new \ReflectionClass($result);
        $properties = $reflectionClass->getProperties();

        foreach ($properties as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }

    /**
     * Test spec-010 FR-005: Enrichment with full product data
     */
    public function testToArrayIncludesAllProductFields(): void
    {
        $product = $this->createMockProduct('p1', 'Test Product');
        $result = new SearchResult([$product], ['p1' => 0.9], 'semantic', 1, 100.0);

        $array = $result->toArray();
        $productData = $array['products'][0];

        $requiredFields = [
            'id', 'name', 'description', 'price', 
            'category', 'stock', 'in_stock', 'similarity_score'
        ];

        foreach ($requiredFields as $field) {
            $this->assertArrayHasKey($field, $productData, "Missing field: $field");
        }
    }
}
