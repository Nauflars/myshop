<?php

namespace App\Application\DTO;

final readonly class ProductDTO
{
    public function __construct(
        public string $id,
        public string $name,
        public string $description,
        public string $price,
        public int $priceInCents,
        public string $currency,
        public int $stock,
        public string $category,
        public bool $inStock,
        public bool $lowStock,
        public string $createdAt,
        public string $updatedAt
    ) {
    }
}
