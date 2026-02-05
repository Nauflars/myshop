<?php

namespace App\Domain\Repository;

use App\Domain\Entity\Product;

interface ProductRepositoryInterface
{
    public function save(Product $product): void;

    public function findById(string $id): ?Product;

    public function findAll(): array;

    public function delete(Product $product): void;

    public function findByCategory(string $category): array;

    public function search(string $query, ?string $category = null, ?int $minPrice = null, ?int $maxPrice = null): array;

    public function findLowStock(int $threshold = 10): array;

    public function countAll(): int;

    public function findTopProducts(int $limit = 10): array;
}
