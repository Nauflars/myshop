<?php

namespace App\Tests\Application\UseCase;

use App\Application\UseCase\SearchProduct;
use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

class SearchProductTest extends TestCase
{
    public function testExecuteWithAllParameters(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        
        $expectedProducts = [
            new Product('Product 1', 'Description 1', new Money(1000, 'USD'), 10, 'Electronics'),
            new Product('Product 2', 'Description 2', new Money(2000, 'USD'), 5, 'Electronics'),
        ];

        $productRepository->expects($this->once())
            ->method('search')
            ->with('laptop', 'Electronics', 500, 3000)
            ->willReturn($expectedProducts);

        $useCase = new SearchProduct($productRepository);
        $result = $useCase->execute('laptop', 'Electronics', 500, 3000);

        $this->assertCount(2, $result);
        $this->assertEquals($expectedProducts, $result);
    }

    public function testExecuteWithQueryOnly(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        
        $productRepository->expects($this->once())
            ->method('search')
            ->with('phone', null, null, null)
            ->willReturn([]);

        $useCase = new SearchProduct($productRepository);
        $result = $useCase->execute('phone');

        $this->assertIsArray($result);
    }

    public function testFindByCategory(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        
        $expectedProducts = [
            new Product('Book 1', 'A great book', new Money(1500, 'USD'), 20, 'Books'),
        ];

        $productRepository->expects($this->once())
            ->method('findByCategory')
            ->with('Books')
            ->willReturn($expectedProducts);

        $useCase = new SearchProduct($productRepository);
        $result = $useCase->findByCategory('Books');

        $this->assertCount(1, $result);
        $this->assertEquals('Book 1', $result[0]->getName());
    }

    public function testFindAll(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        
        $expectedProducts = [
            new Product('Product 1', 'Description 1', new Money(1000, 'USD'), 10, 'Electronics'),
            new Product('Product 2', 'Description 2', new Money(2000, 'USD'), 5, 'Books'),
            new Product('Product 3', 'Description 3', new Money(3000, 'USD'), 15, 'Clothing'),
        ];

        $productRepository->expects($this->once())
            ->method('findAll')
            ->willReturn($expectedProducts);

        $useCase = new SearchProduct($productRepository);
        $result = $useCase->findAll();

        $this->assertCount(3, $result);
    }

    public function testFindById(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        
        $product = new Product('Test Product', 'A test product', new Money(1000, 'USD'), 10, 'Electronics');

        $productRepository->expects($this->once())
            ->method('findById')
            ->with('test-id-123')
            ->willReturn($product);

        $useCase = new SearchProduct($productRepository);
        $result = $useCase->findById('test-id-123');

        $this->assertInstanceOf(Product::class, $result);
        $this->assertEquals('Test Product', $result->getName());
    }

    public function testFindByIdReturnsNull(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        
        $productRepository->expects($this->once())
            ->method('findById')
            ->with('non-existent-id')
            ->willReturn(null);

        $useCase = new SearchProduct($productRepository);
        $result = $useCase->findById('non-existent-id');

        $this->assertNull($result);
    }

    public function testFindLowStock(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        
        $lowStockProducts = [
            new Product('Low Stock 1', 'Description 1', new Money(1000, 'USD'), 3, 'Electronics'),
            new Product('Low Stock 2', 'Description 2', new Money(2000, 'USD'), 5, 'Books'),
        ];

        $productRepository->expects($this->once())
            ->method('findLowStock')
            ->with(10)
            ->willReturn($lowStockProducts);

        $useCase = new SearchProduct($productRepository);
        $result = $useCase->findLowStock(10);

        $this->assertCount(2, $result);
    }

    public function testFindLowStockWithDefaultThreshold(): void
    {
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        
        $productRepository->expects($this->once())
            ->method('findLowStock')
            ->with(10) // default threshold
            ->willReturn([]);

        $useCase = new SearchProduct($productRepository);
        $useCase->findLowStock();
    }
}
