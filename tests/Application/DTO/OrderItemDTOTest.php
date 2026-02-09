<?php

declare(strict_types=1);

namespace App\Tests\Application\DTO;

use App\Application\DTO\OrderItemDTO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for OrderItemDTO
 */
class OrderItemDTOTest extends TestCase
{
    public function testCreateDTO(): void
    {
        $dto = new OrderItemDTO(
            productId: 'prod-123',
            productName: 'Laptop',
            quantity: 2,
            price: '$999.99',
            priceInCents: 99999,
            subtotal: '$1,999.98',
            subtotalInCents: 199998
        );
        
        $this->assertEquals('prod-123', $dto->productId);
        $this->assertEquals('Laptop', $dto->productName);
        $this->assertEquals(2, $dto->quantity);
        $this->assertEquals('$999.99', $dto->price);
        $this->assertEquals(99999, $dto->priceInCents);
        $this->assertEquals('$1,999.98', $dto->subtotal);
        $this->assertEquals(199998, $dto->subtotalInCents);
    }
}
