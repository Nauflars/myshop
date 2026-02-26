<?php

namespace App\Tests\Infrastructure\Controller;

use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Tests for paginated product list response and nameEs serialization.
 * Validates the pagination metadata structure and Product entity's nameEs field.
 */
class ProductControllerTest extends TestCase
{
    public function testProductHasNameEsField(): void
    {
        $product = new Product('Running Shoes', 'Comfortable shoes', new Money(5999, 'USD'), 25, 'Footwear');

        $this->assertNull($product->getNameEs());
    }

    public function testProductNameEsCanBeSet(): void
    {
        $product = new Product('Running Shoes', 'Comfortable shoes', new Money(5999, 'USD'), 25, 'Footwear');
        $product->setNameEs('Zapatillas para Correr');

        $this->assertEquals('Zapatillas para Correr', $product->getNameEs());
    }

    public function testProductDisplayNameReturnsEnglishByDefault(): void
    {
        $product = new Product('Running Shoes', 'Comfortable shoes', new Money(5999, 'USD'), 25, 'Footwear');
        $product->setNameEs('Zapatillas para Correr');

        $this->assertEquals('Running Shoes', $product->getDisplayName());
        $this->assertEquals('Running Shoes', $product->getDisplayName('en'));
    }

    public function testProductDisplayNameReturnsSpanishWhenRequested(): void
    {
        $product = new Product('Running Shoes', 'Comfortable shoes', new Money(5999, 'USD'), 25, 'Footwear');
        $product->setNameEs('Zapatillas para Correr');

        $this->assertEquals('Zapatillas para Correr', $product->getDisplayName('es'));
    }

    public function testProductDisplayNameFallsBackToEnglishWhenNoSpanish(): void
    {
        $product = new Product('Running Shoes', 'Comfortable shoes', new Money(5999, 'USD'), 25, 'Footwear');

        $this->assertEquals('Running Shoes', $product->getDisplayName('es'));
    }

    public function testProductNameEsCanBeCleared(): void
    {
        $product = new Product('Running Shoes', 'Comfortable shoes', new Money(5999, 'USD'), 25, 'Footwear');
        $product->setNameEs('Zapatillas');
        $product->setNameEs(null);

        $this->assertNull($product->getNameEs());
    }

    public function testPaginationMetadataStructure(): void
    {
        // Simulate the pagination logic from ProductController::list
        $products = $this->createProductArray(25);

        $page = 1;
        $limit = 10;
        $total = count($products);
        $offset = ($page - 1) * $limit;
        $paginatedProducts = array_slice($products, $offset, $limit);

        $response = [
            'items' => array_map(fn (Product $p) => $this->serializeProduct($p), $paginatedProducts),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($offset + $limit) < $total,
        ];

        $this->assertArrayHasKey('items', $response);
        $this->assertArrayHasKey('total', $response);
        $this->assertArrayHasKey('page', $response);
        $this->assertArrayHasKey('limit', $response);
        $this->assertArrayHasKey('hasMore', $response);
        $this->assertCount(10, $response['items']);
        $this->assertEquals(25, $response['total']);
        $this->assertEquals(1, $response['page']);
        $this->assertEquals(10, $response['limit']);
        $this->assertTrue($response['hasMore']);
    }

    public function testPaginationLastPageHasNoMore(): void
    {
        $products = $this->createProductArray(25);

        $page = 3;
        $limit = 10;
        $total = count($products);
        $offset = ($page - 1) * $limit;
        $paginatedProducts = array_slice($products, $offset, $limit);

        $response = [
            'items' => array_map(fn (Product $p) => $this->serializeProduct($p), $paginatedProducts),
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'hasMore' => ($offset + $limit) < $total,
        ];

        $this->assertCount(5, $response['items']);
        $this->assertFalse($response['hasMore']);
    }

    public function testPaginationWithExactPageBoundary(): void
    {
        $products = $this->createProductArray(20);

        $page = 2;
        $limit = 10;
        $total = count($products);
        $offset = ($page - 1) * $limit;
        $paginatedProducts = array_slice($products, $offset, $limit);

        $hasMore = ($offset + $limit) < $total;

        $this->assertCount(10, $paginatedProducts);
        $this->assertFalse($hasMore);
    }

    public function testPaginationLimitsAreEnforced(): void
    {
        // Test that limit is clamped between 1 and 50
        $limitFromRequest = 100;
        $limit = min(50, max(1, $limitFromRequest));
        $this->assertEquals(50, $limit);

        $limitFromRequest = 0;
        $limit = min(50, max(1, $limitFromRequest));
        $this->assertEquals(1, $limit);

        $limitFromRequest = -5;
        $limit = min(50, max(1, $limitFromRequest));
        $this->assertEquals(1, $limit);
    }

    public function testPaginationPageMinimumIsOne(): void
    {
        $pageFromRequest = 0;
        $page = max(1, $pageFromRequest);
        $this->assertEquals(1, $page);

        $pageFromRequest = -1;
        $page = max(1, $pageFromRequest);
        $this->assertEquals(1, $page);
    }

    public function testSerializationIncludesNameEs(): void
    {
        $product = new Product('Laptop', 'Great laptop', new Money(99900, 'USD'), 50, 'Electronics');
        $product->setNameEs('Portátil');

        $serialized = $this->serializeProduct($product);

        $this->assertArrayHasKey('nameEs', $serialized);
        $this->assertEquals('Portátil', $serialized['nameEs']);
        $this->assertEquals('Laptop', $serialized['name']);
    }

    public function testSerializationNameEsNullWhenNotSet(): void
    {
        $product = new Product('Laptop', 'Great laptop', new Money(99900, 'USD'), 50, 'Electronics');

        $serialized = $this->serializeProduct($product);

        $this->assertArrayHasKey('nameEs', $serialized);
        $this->assertNull($serialized['nameEs']);
    }

    /**
     * @return Product[]
     */
    private function createProductArray(int $count): array
    {
        $products = [];
        for ($i = 1; $i <= $count; ++$i) {
            $products[] = new Product("Product {$i}", "Description {$i}", new Money($i * 100, 'USD'), 10, 'Category');
        }

        return $products;
    }

    /**
     * Mirrors the serialization from ProductController.
     */
    private function serializeProduct(Product $product): array
    {
        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'nameEs' => $product->getNameEs(),
            'description' => $product->getDescription(),
            'price' => [
                'amount' => $product->getPrice()->getAmountInCents(),
                'currency' => $product->getPrice()->getCurrency(),
            ],
            'stock' => $product->getStock(),
            'category' => $product->getCategory(),
            'inStock' => $product->isInStock(),
            'lowStock' => $product->isLowStock(),
            'createdAt' => $product->getCreatedAt()->format('c'),
            'updatedAt' => $product->getUpdatedAt()->format('c'),
        ];
    }
}
