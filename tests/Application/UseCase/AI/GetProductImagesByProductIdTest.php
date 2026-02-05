<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\AI;

use App\Application\UseCase\AI\GetProductImagesByProductId;
use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for GetProductImagesByProductId use case
 */
class GetProductImagesByProductIdTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private GetProductImagesByProductId $useCase;
    
    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->useCase = new GetProductImagesByProductId($this->productRepository);
    }
    
    public function testExecuteReturnsImagesWhenProductFound(): void
    {
        // Arrange
        $product = $this->createProduct('prod-123', 'Laptop', 'Electronics');
        
        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->with('prod-123')
            ->willReturn($product);
        
        // Act
        $result = $this->useCase->execute('prod-123');
        
        // Assert
        $this->assertTrue($result['found']);
        $this->assertEquals('prod-123', $result['productId']);
        $this->assertEquals('Laptop', $result['productName']);
        $this->assertIsArray($result['images']);
        $this->assertNotEmpty($result['images']);
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
        $this->assertEquals('nonexistent', $result['productId']);
        $this->assertNull($result['productName']);
        $this->assertEmpty($result['images']);
    }
    
    public function testExecuteGeneratesPlaceholderImages(): void
    {
        // Arrange
        $product = $this->createProduct('prod-456', 'Gaming Mouse', 'Electronics');
        
        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($product);
        
        // Act
        $result = $this->useCase->execute('prod-456');
        
        // Assert
        $this->assertGreaterThan(0, count($result['images']));
        foreach ($result['images'] as $imageUrl) {
            $this->assertIsString($imageUrl);
            $this->assertStringContainsString('http', $imageUrl);
        }
    }
    
    public function testExecuteThrowsExceptionForEmptyProductId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product ID cannot be empty');
        
        $this->useCase->execute('');
    }
    
    /**
     * Helper method to create a mock Product
     */
    private function createProduct(string $id, string $name, string $category): Product
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn($id);
        $product->method('getName')->willReturn($name);
        $product->method('getCategory')->willReturn($category);
        
        return $product;
    }
}
