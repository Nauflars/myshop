<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\AI;

use App\Application\UseCase\AI\GetProductsName;
use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GetProductsName use case
 */
class GetProductsNameTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private GetProductsName $useCase;
    
    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->useCase = new GetProductsName($this->productRepository);
    }
    
    public function testExecuteReturnsAllProductsWhenNoFilters(): void
    {
        // Arrange
        $products = [
            $this->createProduct('1', 'Laptop', 'Electronics'),
            $this->createProduct('2', 'T-Shirt', 'Clothing'),
            $this->createProduct('3', 'Book', 'Books'),
        ];
        
        $this->productRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($products);
        
        // Act
        $result = $this->useCase->execute();
        
        // Assert
        $this->assertCount(3, $result);
        $this->assertEquals('1', $result[0]['id']);
        $this->assertEquals('Laptop', $result[0]['name']);
        $this->assertEquals('Electronics', $result[0]['category']);
    }
    
    public function testExecuteUsesSearchWhenSearchTermProvided(): void
    {
        // Arrange
        $products = [
            $this->createProduct('1', 'Laptop', 'Electronics'),
        ];
        
        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->with('laptop', null)
            ->willReturn($products);
        
        $this->productRepository
            ->expects($this->never())
            ->method('findAll');
        
        // Act
        $result = $this->useCase->execute('laptop');
        
        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Laptop', $result[0]['name']);
    }
    
    public function testExecuteUsesSearchWhenCategoryProvided(): void
    {
        // Arrange
        $products = [
            $this->createProduct('2', 'T-Shirt', 'Clothing'),
        ];
        
        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->with('', 'Clothing')
            ->willReturn($products);
        
        // Act
        $result = $this->useCase->execute(null, 'Clothing');
        
        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('T-Shirt', $result[0]['name']);
        $this->assertEquals('Clothing', $result[0]['category']);
    }
    
    public function testExecuteUsesSearchWhenBothFiltersProvided(): void
    {
        // Arrange
        $products = [
            $this->createProduct('1', 'Gaming Laptop', 'Electronics'),
        ];
        
        $this->productRepository
            ->expects($this->once())
            ->method('search')
            ->with('gaming', 'Electronics')
            ->willReturn($products);
        
        // Act
        $result = $this->useCase->execute('gaming', 'Electronics');
        
        // Assert
        $this->assertCount(1, $result);
        $this->assertEquals('Gaming Laptop', $result[0]['name']);
    }
    
    public function testExecuteReturnsEmptyArrayWhenNoProducts(): void
    {
        // Arrange
        $this->productRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([]);
        
        // Act
        $result = $this->useCase->execute();
        
        // Assert
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
    
    /**
     * Helper method to create a mock Product entity
     */
    private function createProduct(string $id, string $name, string $category): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getName')->willReturn($name);
        $product->method('getCategory')->willReturn($category);
        $product->method('getDescription')->willReturn("Description for {$name}");
        $product->method('getStock')->willReturn(10);
        $product->method('isInStock')->willReturn(true);
        
        // Create real Money instance instead of mock (Money is final)
        $money = new Money(9999, 'USD'); // $99.99
        $product->method('getPrice')->willReturn($money);
        
        return $product;
    }
}
