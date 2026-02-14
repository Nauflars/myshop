<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Repository\ProductRepositoryInterface;

final class ListProducts
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * List all products with optional filters.
     *
     * @param string|null $category      Filter by category
     * @param bool|null   $availableOnly Show only available products
     *
     * @return array Array of products with name, description, price, availability
     */
    public function execute(?string $category = null, ?bool $availableOnly = true): array
    {
        $products = $this->productRepository->findAll();

        $result = [];
        foreach ($products as $product) {
            // Apply category filter
            if (null !== $category && $product->getCategory() !== $category) {
                continue;
            }

            // Apply availability filter
            if ($availableOnly && $product->getStock() <= 0) {
                continue;
            }

            $result[] = [
                'name' => $product->getDisplayName('es'), // Use Spanish name if available
                'nameEn' => $product->getName(), // Keep original English name for reference
                'description' => $product->getDescription(),
                'price' => $product->getPrice(),
                'currency' => 'USD',
                'stock' => $product->getStock(),
                'available' => $product->getStock() > 0,
                'category' => $product->getCategory(),
            ];
        }

        return $result;
    }
}
