<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetProductsNameByMaxPrice;
use App\Infrastructure\AI\Tool\GetProductsNameByMaxPriceTool;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for GetProductsNameByMaxPriceTool
 */
class GetProductsNameByMaxPriceToolTest extends TestCase
{
    private GetProductsNameByMaxPrice $getProductsNameByMaxPrice;
    private GetProductsNameByMaxPriceTool $tool;
    
    protected function setUp(): void
    {
        $this->getProductsNameByMaxPrice = $this->createMock(GetProductsNameByMaxPrice::class);
        $this->tool = new GetProductsNameByMaxPriceTool($this->getProductsNameByMaxPrice);
    }
    
    public function testInvokeReturnsSuccessWithProducts(): void
    {
        // Arrange
        $products = [
            ['id' => '1', 'name' => 'Laptop', 'price' => 45.00, 'currency' => 'USD', 'category' => 'Electronics'],
            ['id' => '2', 'name' => 'Mouse', 'price' => 15.00, 'currency' => 'USD', 'category' => 'Electronics'],
        ];
        
        $this->getProductsNameByMaxPrice
            ->expects($this->once())
            ->method('execute')
            ->with(50.00, 'USD', null)
            ->willReturn($products);
        
        // Act
        $result = ($this->tool)(50.00);
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(2, $result['count']);
        $this->assertStringContainsString('Se encontraron 2 producto', $result['message']);
        $this->assertStringContainsString('$50.00', $result['message']);
    }
    
    public function testInvokeWithCategory(): void
    {
        // Arrange
        $products = [
            ['id' => '1', 'name' => 'T-Shirt', 'price' => 19.99, 'currency' => 'USD', 'category' => 'Clothing'],
        ];
        
        $this->getProductsNameByMaxPrice
            ->expects($this->once())
            ->method('execute')
            ->with(30.00, 'USD', 'Clothing')
            ->willReturn($products);
        
        // Act
        $result = ($this->tool)(30.00, 'USD', 'Clothing');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['count']);
        $this->assertStringContainsString('Clothing', $result['message']);
    }
    
    public function testInvokeRejectsNegativePrice(): void
    {
        // Act
        $result = ($this->tool)(-10.00);
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['count']);
        $this->assertStringContainsString('must be greater than zero', $result['message']);
    }
    
    public function testInvokeRejectsZeroPrice(): void
    {
        // Act
        $result = ($this->tool)(0.00);
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('must be greater than zero', $result['message']);
    }
    
    public function testInvokeReturnsEmptyResultsMessage(): void
    {
        // Arrange
        $this->getProductsNameByMaxPrice
            ->expects($this->once())
            ->method('execute')
            ->willReturn([]);
        
        // Act
        $result = ($this->tool)(5.00);
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['count']);
        $this->assertStringContainsString('No se encontraron productos', $result['message']);
    }
    
    public function testInvokeHandlesException(): void
    {
        // Arrange
        $this->getProductsNameByMaxPrice
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Database error'));
        
        // Act
        $result = ($this->tool)(50.00);
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['count']);
        $this->assertStringContainsString('No se pudieron recuperar los productos', $result['message']);
    }
    
    public function testInvokeFormatsCurrencySymbolCorrectly(): void
    {
        // Arrange
        $products = [
            ['id' => '1', 'name' => 'Item', 'price' => 10.00, 'currency' => 'EUR', 'category' => 'Test'],
        ];
        
        $this->getProductsNameByMaxPrice
            ->expects($this->once())
            ->method('execute')
            ->with(20.00, 'EUR', null)
            ->willReturn($products);
        
        // Act
        $result = ($this->tool)(20.00, 'EUR');
        
        // Assert
        $this->assertStringContainsString('€20.00', $result['message']);
    }
    
    public function testInvokeFormatsMessageCorrectlyForSingleProduct(): void
    {
        // Arrange
        $products = [
            ['id' => '1', 'name' => 'Book', 'price' => 12.99, 'currency' => 'USD', 'category' => 'Books'],
        ];
        
        $this->getProductsNameByMaxPrice
            ->expects($this->once())
            ->method('execute')
            ->willReturn($products);
        
        // Act
        $result = ($this->tool)(15.00);
        
        // Assert
        $this->assertStringContainsString('Se encontraron 1 producto', $result['message']);
        $this->assertStringNotContainsString('productos', $result['message']); // Should be singular
    }
}
