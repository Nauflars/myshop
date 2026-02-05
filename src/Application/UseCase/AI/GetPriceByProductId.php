<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Repository\ProductRepositoryInterface;

/**
 * GetPriceByProductId Use Case - Retrieve product price and stock information
 * 
 * This use case fetches a specific product's price, currency, and stock status.
 * Used by AI tools to provide pricing details to users.
 * 
 * Architecture: Application layer (business logic)
 * DDD Role: Use case / application service
 * 
 * @author AI Shopping Assistant Team
 */
class GetPriceByProductId
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }
    
    /**
     * Execute the use case
     *
     * @param string $productId Product UUID
     * @return array{
     *     found: bool,
     *     id: string|null,
     *     name: string|null,
     *     price: float|null,
     *     currency: string|null,
     *     inStock: bool,
     *     stockQuantity: int|null
     * } Product price information
     * @throws \InvalidArgumentException If productId is empty
     */
    public function execute(string $productId): array
    {
        if (empty($productId)) {
            throw new \InvalidArgumentException('Product ID cannot be empty');
        }
        
        // Find product by ID
        $product = $this->productRepository->findById($productId);
        
        if (!$product) {
            return [
                'found' => false,
                'id' => $productId,
                'name' => null,
                'price' => null,
                'currency' => null,
                'inStock' => false,
                'stockQuantity' => null,
            ];
        }
        
        $price = $product->getPrice();
        
        return [
            'found' => true,
            'id' => (string) $product->getId(),
            'name' => $product->getName(),
            'price' => $price->getAmount(), // Returns as float (e.g., 19.99)
            'currency' => $price->getCurrency(),
            'inStock' => $product->isInStock(),
            'stockQuantity' => $product->getStock(),
        ];
    }
}
