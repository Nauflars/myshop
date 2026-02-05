<?php

namespace App\Application\DTO;

final readonly class CartDTO
{
    /**
     * @param array<CartItemDTO> $items
     */
    public function __construct(
        public string $id,
        public string $userId,
        public array $items,
        public string $total,
        public int $totalInCents,
        public string $currency,
        public int $itemCount,
        public int $totalQuantity,
        public string $updatedAt
    ) {
    }
}
