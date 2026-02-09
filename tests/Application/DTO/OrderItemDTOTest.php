<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\OrderItemDTO;
use App\Domain\Entity\OrderItem;
use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OrderItemDTO
 */
class OrderItemDTOTest extends TestCase
{
    public function testFromEntity(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('prod-123');
        $product->method('getName')->willReturn('Laptop');
        
        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProduct')->willReturn($product);
        $orderItem->method('getProductName')->willReturn('Laptop');
        $orderItem->method('getQuantity')->willReturn(2);
        $orderItem->method('getPrice')->willReturn(new Money(99999, 'USD')); // $999.99
        $orderItem->method('getSubtotal')->willReturn(new Money(199998, 'USD')); // $1999.98
        
        $dto = OrderItemDTO::fromEntity($orderItem);
        
        $this->assertEquals('prod-123', $dto->productId);
        $this->assertEquals('Laptop', $dto->productName);
        $this->assertEquals(2, $dto->quantity);
        $this->assertEquals(999.99, $dto->price);
        $this->assertEquals(1999.98, $dto->subtotal);
    }
    
    public function testFromEntityWithDifferentPrice(): void
    {
        $product = $this->createMock(Product::class);
        $product->method('getId')->willReturn('prod-456');
        $product->method('getName')->willReturn('Mouse');
        
        $orderItem = $this->createMock(OrderItem::class);
        $orderItem->method('getProduct')->willReturn($product);
        $orderItem->method('getProductName')->willReturn('Mouse');
        $orderItem->method('getQuantity')->willReturn(1);
        $orderItem->method('getPrice')->willReturn(new Money(2500, 'USD')); // $25.00
        $orderItem->method('getSubtotal')->willReturn(new Money(2500, 'USD')); // $25.00
        
        $dto = OrderItemDTO::fromEntity($orderItem);
        
        $this->assertEquals('prod-456', $dto->productId);
        $this->assertEquals(25.00, $dto->price);
        $this->assertEquals(1, $dto->quantity);
    }
}
