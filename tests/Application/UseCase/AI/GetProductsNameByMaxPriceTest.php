<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\AI;

use App\Application\UseCase\AI\GetProductsNameByMaxPrice;
use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GetProductsNameByMaxPrice use case
 */
class GetProductsNameByMaxPriceTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private GetProductsNameByMaxPrice $useCase;
    
    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->useCase = new GetProductsNameByMaxPrice($this->productRepository);
    }
    
    public function testExecuteFiltersProductsByMaxPrice(): void
    {
        // Arrange
        $products = [
            $this->createProduct('1', 'Budget Laptop', 'Electronics', 40.00),
            $this->createProduct('2', 'Cheap Headphones', 'Electronics', 25.00),
        ];
        
        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->with('', null, null, 5000) // $50 = 5000 cents
            ->willReturn($products);
        
        // Act
        $result = $this->useCase->execute(50.00);
        
        // Assert
        $this->assertCount(2, $result);
        $this->assertEquals('Budget Laptop', $result[0]['name']);
        $this->assertEquals(40.00, $result[0]['price']);
    }
    
    public function testExecuteWithCategory(): void
    {
        // Arrange
        $products = [
            $this->createProduct('1', 'T-Shirt', 'Clothing', 19.99),
        ];
        
        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->with('', 'Clothing', null, 2000) // $20 = 2000 cents
            ->willReturn($products);
        
        // Act
        $result = $this->useCase->execute(20.00, 'USD', 'Clothing');
        
        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('T-Shirt', $result[0]['name']);
        $this->assertEquals('Clothing', $result[0]['category']);
    }
    
    public function testExecuteReturnsPriceInformation(): void
    {
        // Arrange
        $products = [
            $this->createProduct('1', 'Book', 'Books', 15.99),
        ];
        
        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn($products);
        
        // Act
        $result = $this->useCase->execute(20.00);
        
        // Assert
        $this->assertArrayHasKey('price', $result[0]);
        $this->assertArrayHasKey('currency', $result[0]);
        $this->assertEquals(15.99, $result[0]['price']);
        $this->assertEquals('USD', $result[0]['currency']);
    }
    
    public function testExecuteReturnsEmptyArrayWhenNoMatches(): void
    {
        // Arrange
        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->willReturn([]);
        
        // Act
        $result = $this->useCase->execute(10.00);
        
        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    public function testExecuteConvertsDecimalPriceToCents(): void
    {
        // Arrange
        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->with('', null, null, 9999) // $99.99 = 9999 cents
            ->willReturn([]);
        
        // Act
        $this->useCase->execute(99.99);
        
        // Assert - expectation verified by mock
    }
    
    /**
     * Helper method to create a mock Product entity with price
     */
    private function createProduct(string $id, string $name, string $category, float $priceAmount): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getName')->willReturn($name);
        $product->method('getCategory')->willReturn($category);
        
        // Create real Money instance instead of mock (Money is final)
        $money = new Money((int)($priceAmount * 100), 'USD'); // Convert to cents
        
        $product->method('getPrice')->willReturn($money);
        
        return $product;
    }
}
