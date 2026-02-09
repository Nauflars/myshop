<?php

declare(strict_types=1);

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\RecommendationResult;
use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RecommendationResult value object
 */
class RecommendationResultTest extends TestCase
{
    public function testCreateRecommendationResult(): void
    {
        $product1 = new Product('Laptop', 'Gaming laptop', new Money(150000, 'USD'), 5, 'Electronics');
        $product2 = new Product('Mouse', 'Gaming mouse', new Money(5000, 'USD'), 10, 'Electronics');
        
        $products = [$product1, $product2];
        $scores = [0.95, 0.87];
        
        $result = new RecommendationResult($products, $scores);
        
        $this->assertCount(2, $result->getProducts());
        $this->assertEquals($scores, $result->getSimilarityScores());
        $this->assertInstanceOf(\DateTimeImmutable::class, $result->getGeneratedAt());
        $this->assertFalse($result->isEmpty());
        $this->assertEquals(2, $result->count());
    }

    public function testEmptyRecommendationResult(): void
    {
        $result = new RecommendationResult([], []);
        
        $this->assertTrue($result->isEmpty());
        $this->assertEquals(0, $result->count());
        $this->assertEmpty($result->getProducts());
        $this->assertEmpty($result->getSimilarityScores());
    }

    public function testGetProductWithScore(): void
    {
        $product1 = new Product('Laptop', 'Gaming laptop', new Money(150000, 'USD'), 5, 'Electronics');
        $product2 = new Product('Mouse', 'Gaming mouse', new Money(5000, 'USD'), 10, 'Electronics');
        
        $result = new RecommendationResult([$product1, $product2], [0.95, 0.87]);
        
        $firstWithScore = $result->getProductWithScore(0);
        $this->assertEquals($product1, $firstWithScore['product']);
        $this->assertEquals(0.95, $firstWithScore['score']);
        
        $secondWithScore = $result->getProductWithScore(1);
        $this->assertEquals($product2, $secondWithScore['product']);
        $this->assertEquals(0.87, $secondWithScore['score']);
    }

    public function testFilterByMinScore(): void
    {
        $product1 = new Product('Laptop', 'Gaming laptop', new Money(150000, 'USD'), 5, 'Electronics');
        $product2 = new Product('Mouse', 'Gaming mouse', new Money(5000, 'USD'), 10, 'Electronics');
        $product3 = new Product('Keyboard', 'Mechanical keyboard', new Money(8000, 'USD'), 15, 'Electronics');
        
        $result = new RecommendationResult([$product1, $product2, $product3], [0.95, 0.87, 0.75]);
        
        $filtered = $result->filterByMinScore(0.80);
        
        $this->assertCount(2, $filtered->getProducts());
        $this->assertEquals($product1, $filtered->getProducts()[0]);
        $this->assertEquals($product2, $filtered->getProducts()[1]);
    }
}
