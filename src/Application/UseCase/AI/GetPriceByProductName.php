<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Repository\ProductRepositoryInterface;

/**
 * GetPriceByProductName Use Case - Retrieve product price by name
 * 
 * This use case fetches a product's price, currency, and stock status by its name.
 * Performs case-insensitive search and ensures exact match.
 * Used by AI tools to provide pricing details without exposing internal IDs.
 * 
 * Architecture: Application layer (business logic)
 * DDD Role: Use case / application service
 * 
 * @author AI Shopping Assistant Team
 */
class GetPriceByProductName
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }
    
    /**
     * Execute the use case
     *
     * @param string $productName Product name
     * @return array{
     *     found: bool,
     *     name: string|null,
     *     description: string|null,
     *     price: float|null,
     *     currency: string|null,
     *     inStock: bool,
     *     stockQuantity: int|null
     * } Product price information
     * @throws \InvalidArgumentException If productName is empty
     */
    public function execute(string $productName): array
    {
        if (empty(trim($productName))) {
            throw new \InvalidArgumentException('Product name cannot be empty');
        }
        
        // Search for product by name
        $products = $this->productRepository->search(trim($productName), null, null, null);
        
        // Find exact case-insensitive match
        $product = null;
        foreach ($products as $p) {
            if (strcasecmp($p->getName(), trim($productName)) === 0) {
                $product = $p;
                break;
            }
        }
        
        if (!$product) {
            return [
                'found' => false,
                'name' => $productName,
                'description' => null,
                'price' => null,
                'currency' => null,
                'inStock' => false,
                'stockQuantity' => null,
            ];
        }
        
        $price = $product->getPrice();
        
        return [
            'found' => true,
            'name' => $product->getName(),
            'description' => $product->getDescription(),
            'price' => $price->getAmountAsDecimal(),
            'currency' => $price->getCurrency(),
            'inStock' => $product->isInStock(),
            'stockQuantity' => $product->getStock(),
        ];
    }
}
