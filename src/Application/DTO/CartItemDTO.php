<?php

namespace App\Application\DTO;

final readonly class CartItemDTO
{
    public function __construct(
        public string $productId,
        public string $productName,
        public int $quantity,
        public string $price,
        public int $priceInCents,
        public string $subtotal,
        public int $subtotalInCents
    ) {
    }
}
