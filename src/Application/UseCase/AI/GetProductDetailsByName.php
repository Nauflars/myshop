<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Repository\ProductRepositoryInterface;

final class GetProductDetailsByName
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * Get detailed product information by product name
     *
     * @param string $productName The name of the product
     * @return array|null Product details or null if not found
     */
    public function execute(string $productName): ?array
    {
        $products = $this->productRepository->findAll();

        // Find product by exact name match (case-insensitive)
        foreach ($products as $product) {
            if (strcasecmp($product->getName(), $productName) === 0) {
                return [
                    'name' => $product->getName(),
                    'description' => $product->getDescription(),
                    'price' => $product->getPrice(),
                    'currency' => 'USD',
                    'stock' => $product->getStock(),
                    'available' => $product->getStock() > 0,
                    'category' => $product->getCategory(),
                    'images' => [], // Will be populated if image relationship exists
                ];
            }
        }

        // Product not found
        return null;
    }
}
