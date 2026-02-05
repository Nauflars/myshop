<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetPriceByProductId;
use App\Infrastructure\AI\Tool\GetPriceByProductIdTool;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for GetPriceByProductIdTool
 */
class GetPriceByProductIdToolTest extends TestCase
{
    private GetPriceByProductId $getPriceByProductId;
    private GetPriceByProductIdTool $tool;
    
    protected function setUp(): void
    {
        $this->getPriceByProductId = $this->createMock(GetPriceByProductId::class);
        $this->tool = new GetPriceByProductIdTool($this->getPriceByProductId);
    }
    
    public function testInvokeReturnsSuccessWithPrice(): void
    {
        // Arrange
        $productData = [
            'found' => true,
            'id' => 'prod-123',
            'name' => 'Laptop',
            'price' => 999.99,
            'currency' => 'USD',
            'inStock' => true,
            'stockQuantity' => 50,
        ];
        
        $this->getPriceByProductId
            ->expects($this->once())
            ->method('execute')
            ->with('prod-123')
            ->willReturn($productData);
        
        // Act
        $result = ($this->tool)('prod-123');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']);
        $this->assertEquals('Laptop', $result['data']['name']);
        $this->assertEquals(999.99, $result['data']['price']);
        $this->assertTrue($result['data']['inStock']);
        $this->assertStringContainsString('$999.99', $result['message']);
    }
    
    public function testInvokeReturnsFailureWhenProductNotFound(): void
    {
        // Arrange
        $productData = [
            'found' => false,
            'id' => 'nonexistent',
            'name' => null,
            'price' => null,
            'currency' => null,
            'inStock' => false,
            'stockQuantity' => null,
        ];
        
        $this->getPriceByProductId
            ->expects($this->once())
            ->method('execute')
            ->with('nonexistent')
            ->willReturn($productData);
        
        // Act
        $result = ($this->tool)('nonexistent');
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertStringContainsString('not found', $result['message']);
    }
    
    public function testInvokeHandlesOutOfStock(): void
    {
        // Arrange
        $productData = [
            'found' => true,
            'id' => 'prod-456',
            'name' => 'Out of Stock Item',
            'price' => 25.00,
            'currency' => 'USD',
            'inStock' => false,
            'stockQuantity' => 0,
        ];
        
        $this->getPriceByProductId
            ->expects($this->once())
            ->method('execute')
            ->willReturn($productData);
        
        // Act
        $result = ($this->tool)('prod-456');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertFalse($result['data']['inStock']);
        $this->assertStringContainsString('out of stock', $result['message']);
    }
    
    public function testInvokeRejectsEmptyProductId(): void
    {
        // Act
        $result = ($this->tool)('');
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertStringContainsString('required', $result['message']);
    }
    
    public function testInvokeHandlesException(): void
    {
        // Arrange
        $this->getPriceByProductId
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Database error'));
        
        // Act
        $result = ($this->tool)('prod-123');
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertNull($result['data']);
        $this->assertStringContainsString('Failed to retrieve', $result['message']);
    }
    
    public function testInvokeFormatsCurrencyCorrectly(): void
    {
        // Arrange
        $productData = [
            'found' => true,
            'id' => 'prod-789',
            'name' => 'Item',
            'price' => 50.00,
            'currency' => 'EUR',
            'inStock' => true,
            'stockQuantity' => 10,
        ];
        
        $this->getPriceByProductId
            ->expects($this->once())
            ->method('execute')
            ->willReturn($productData);
        
        // Act
        $result = ($this->tool)('prod-789');
        
        // Assert
        $this->assertStringContainsString('€50.00', $result['message']);
    }
}
