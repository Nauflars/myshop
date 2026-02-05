<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetProductImagesByProductId;
use App\Infrastructure\AI\Tool\GetProductImagesByProductIdTool;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for GetProductImagesByProductIdTool
 */
class GetProductImagesByProductIdToolTest extends TestCase
{
    private GetProductImagesByProductId $getProductImagesByProductId;
    private GetProductImagesByProductIdTool $tool;
    
    protected function setUp(): void
    {
        $this->getProductImagesByProductId = $this->createMock(GetProductImagesByProductId::class);
        $this->tool = new GetProductImagesByProductIdTool($this->getProductImagesByProductId);
    }
    
    public function testInvokeReturnsSuccessWithImages(): void
    {
        // Arrange
        $productData = [
            'found' => true,
            'productId' => 'prod-123',
            'productName' => 'Laptop',
            'images' => [
                'https://example.com/image1.jpg',
                'https://example.com/image2.jpg',
            ],
        ];
        
        $this->getProductImagesByProductId
            ->expects($this->once())
            ->method('execute')
            ->with('prod-123')
            ->willReturn($productData);
        
        // Act
        $result = ($this->tool)('prod-123');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertNotNull($result['data']);
        $this->assertEquals('Laptop', $result['data']['productName']);
        $this->assertCount(2, $result['data']['images']);
        $this->assertEquals(2, $result['data']['imageCount']);
        $this->assertStringContainsString('Found 2 images', $result['message']);
    }
    
    public function testInvokeReturnsFailureWhenProductNotFound(): void
    {
        // Arrange
        $productData = [
            'found' => false,
            'productId' => 'nonexistent',
            'productName' => null,
            'images' => [],
        ];
        
        $this->getProductImagesByProductId
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
    
    public function testInvokeHandlesNoImages(): void
    {
        // Arrange
        $productData = [
            'found' => true,
            'productId' => 'prod-456',
            'productName' => 'No Images Product',
            'images' => [],
        ];
        
        $this->getProductImagesByProductId
            ->expects($this->once())
            ->method('execute')
            ->willReturn($productData);
        
        // Act
        $result = ($this->tool)('prod-456');
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals(0, $result['data']['imageCount']);
        $this->assertStringContainsString('No images available', $result['message']);
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
        $this->getProductImagesByProductId
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
    
    public function testInvokeFormatsSingularImageMessage(): void
    {
        // Arrange
        $productData = [
            'found' => true,
            'productId' => 'prod-789',
            'productName' => 'Single Image Product',
            'images' => [
                'https://example.com/image1.jpg',
            ],
        ];
        
        $this->getProductImagesByProductId
            ->expects($this->once())
            ->method('execute')
            ->willReturn($productData);
        
        // Act
        $result = ($this->tool)('prod-789');
        
        // Assert
        $this->assertStringContainsString('Found 1 image', $result['message']);
        $this->assertStringNotContainsString('images', $result['message']); // Should be singular
    }
}
