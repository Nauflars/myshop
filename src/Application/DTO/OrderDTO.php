<?php

namespace App\Application\DTO;

final readonly class OrderDTO
{
    /**
     * @param array<OrderItemDTO> $items
     */
    public function __construct(
        public string $id,
        public string $orderNumber,
        public string $userId,
        public array $items,
        public string $total,
        public int $totalInCents,
        public string $currency,
        public string $status,
        public string $createdAt,
        public string $updatedAt
    ) {
    }
}
