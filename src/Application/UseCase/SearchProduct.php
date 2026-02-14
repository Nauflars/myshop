<?php

namespace App\Application\UseCase;

use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;

final class SearchProduct
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @return Product[]
     */
    public function execute(
        ?string $query = null,
        ?string $category = null,
        ?int $minPrice = null,
        ?int $maxPrice = null,
    ): array {
        return $this->productRepository->search($query, $category, $minPrice, $maxPrice);
    }

    /**
     * @return Product[]
     */
    public function findByCategory(string $category): array
    {
        return $this->productRepository->findByCategory($category);
    }

    /**
     * @return Product[]
     */
    public function findAll(): array
    {
        return $this->productRepository->findAll();
    }

    public function findById(string $id): ?Product
    {
        return $this->productRepository->findById($id);
    }

    /**
     * @return Product[]
     */
    public function findLowStock(int $threshold = 10): array
    {
        return $this->productRepository->findLowStock($threshold);
    }
}
