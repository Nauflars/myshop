<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\AI;

use App\Application\UseCase\AI\GetPriceByProductId;
use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GetPriceByProductId use case
 */
class GetPriceByProductIdTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private GetPriceByProductId $useCase;
    
    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->useCase = new GetPriceByProductId($this->productRepository);
    }
    
    public function testExecuteReturnsProductPriceWhenFound(): void
    {
        // Arrange
        $product = $this->createProductWithPrice('prod-123', 'Laptop', 999.99, 50);
        
        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->with('prod-123')
            ->willReturn($product);
        
        // Act
        $result = $this->useCase->execute('prod-123');
        
        // Assert
        $this->assertTrue($result['found']);
        $this->assertEquals('prod-123', $result['id']);
        $this->assertEquals('Laptop', $result['name']);
        $this->assertEquals(999.99, $result['price']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertTrue($result['inStock']);
        $this->assertEquals(50, $result['stockQuantity']);
    }
    
    public function testExecuteReturnsNotFoundWhenProductDoesNotExist(): void
    {
        // Arrange
        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->with('nonexistent')
            ->willReturn(null);
        
        // Act
        $result = $this->useCase->execute('nonexistent');
        
        // Assert
        $this->assertFalse($result['found']);
        $this->assertEquals('nonexistent', $result['id']);
        $this->assertNull($result['name']);
        $this->assertNull($result['price']);
        $this->assertFalse($result['inStock']);
    }
    
    public function testExecuteIndicatesOutOfStock(): void
    {
        // Arrange
        $product = $this->createProductWithPrice('prod-456', 'Out of Stock Item', 25.00, 0);
        
        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);
        
        // Act
        $result = $this->useCase->execute('prod-456');
        
        // Assert
        $this->assertTrue($result['found']);
        $this->assertFalse($result['inStock']);
        $this->assertEquals(0, $result['stockQuantity']);
    }
    
    public function testExecuteThrowsExceptionForEmptyProductId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product ID cannot be empty');
        
        $this->useCase->execute('');
    }
    
    /**
     * Helper method to create a mock Product with price
     */
    private function createProductWithPrice(string $id, string $name, float $price, int $stock): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getName')->willReturn($name);
        $product->method('getStock')->willReturn($stock);
        $product->method('isInStock')->willReturn($stock > 0);
        
        $money = $this->createMock(Money::class);
        $money->method('getAmount')->willReturn($price);
        $money->method('getCurrency')->willReturn('USD');
        
        $product->method('getPrice')->willReturn($money);
        
        return $product;
    }
}
