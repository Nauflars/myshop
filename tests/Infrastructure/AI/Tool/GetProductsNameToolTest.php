<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetProductsName;
use App\Infrastructure\AI\Tool\GetProductsNameTool;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for GetProductsNameTool
 */
class GetProductsNameToolTest extends TestCase
{
    private GetProductsName $getProductsName;
    private GetProductsNameTool $tool;
    
    protected function setUp(): void
    {
        $this->getProductsName = $this->createMock(GetProductsName::class);
        $this->tool = new GetProductsNameTool($this->getProductsName);
    }
    
    public function testInvokeReturnsSuccessWithProducts(): void
    {
        // Arrange
        $products = [
            ['id' => '1', 'name' => 'Laptop', 'category' => 'Electronics'],
            ['id' => '2', 'name' => 'T-Shirt', 'category' => 'Clothing'],
        ];
        
        $this->getProductsName
            ->expects($this->once())
            ->method('execute')
            ->with(null, null)
            ->willReturn($products);
        
        // Act
        $result = ($this->tool)();
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertCount(2, $result['data']);
        $this->assertEquals(2, $result['count']);
        $this->assertStringContainsString('Se encontraron 2 producto', $result['message']);
    }
    
    public function testInvokeWithSearchTerm(): void
    {
        // Arrange
        $products = [
            ['id' => '1', 'name' => 'Gaming Laptop', 'category' => 'Electronics'],
        ];
        
        $this->getProductsName
            ->expects($this->once())
            ->method('execute')
            ->with('gaming', null)
            ->willReturn($products);
        
        // Act
        $result = ($this->tool)('gaming');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['count']);
        $this->assertStringContainsString('gaming', $result['message']);
    }
    
    public function testInvokeWithCategory(): void
    {
        // Arrange
        $products = [
            ['id' => '2', 'name' => 'T-Shirt', 'category' => 'Clothing'],
            ['id' => '3', 'name' => 'Jeans', 'category' => 'Clothing'],
        ];
        
        $this->getProductsName
            ->expects($this->once())
            ->method('execute')
            ->with(null, 'Clothing')
            ->willReturn($products);
        
        // Act
        $result = ($this->tool)(null, 'Clothing');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['count']);
        $this->assertStringContainsString('Clothing', $result['message']);
    }
    
    public function testInvokeReturnsEmptyResultsMessage(): void
    {
        // Arrange
        $this->getProductsName
            ->expects($this->once())
            ->method('execute')
            ->with('nonexistent', null)
            ->willReturn([]);
        
        // Act
        $result = ($this->tool)('nonexistent');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['data']);
        $this->assertStringContainsString('No se encontraron productos', $result['message']);
    }
    
    public function testInvokeHandlesException(): void
    {
        // Arrange
        $this->getProductsName
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(new \Exception('Database error'));
        
        // Act
        $result = ($this->tool)();
        
        // Assert
        $this->assertFalse($result['success']);
        $this->assertEquals(0, $result['count']);
        $this->assertEmpty($result['data']);
        $this->assertStringContainsString('No se pudieron recuperar los productos', $result['message']);
    }
    
    public function testInvokeFormatsMessageCorrectlyForSingleProduct(): void
    {
        // Arrange
        $products = [
            ['id' => '1', 'name' => 'Laptop', 'category' => 'Electronics'],
        ];
        
        $this->getProductsName
            ->expects($this->once())
            ->method('execute')
            ->willReturn($products);
        
        // Act
        $result = ($this->tool)();
        
        // Assert
        $this->assertStringContainsString('Se encontraron 1 producto', $result['message']);
        $this->assertStringNotContainsString('productos', $result['message']); // Should be singular
    }
}
