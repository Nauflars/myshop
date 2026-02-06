<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Repository\ProductRepositoryInterface;

/**
 * GetProductsNameByMaxPrice Use Case - Retrieve products within price range
 * 
 * This use case fetches products that cost less than or equal to a specified maximum price.
 * Used by AI tools to help users find products within their budget.
 * 
 * Architecture: Application layer (business logic)
 * DDD Role: Use case / application service
 * 
 * @author AI Shopping Assistant Team
 */
class GetProductsNameByMaxPrice
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }
    
    /**
     * Execute the use case
     *
     * @param float $maxPrice Maximum price in dollars (or major currency unit)
     * @param string $currency Currency code (e.g., 'USD', 'EUR')
     * @param string|null $category Optional category filter
     * @return array<int, array{id: string, name: string, description: string, price: float, currency: string, stock: int, inStock: bool, category: string}> Array of products with enriched data
     */
    public function execute(float $maxPrice, string $currency = 'USD', ?string $category = null): array
    {
        // Convert price to cents for comparison (assuming price is in cents in DB)
        $maxPriceInCents = (int) round($maxPrice * 100);
        
        // Use search method with price filter
        $products = $this->productRepository->search(
            '',              // Empty query - search all
            $category,       // Category filter
            null,            // No min price
            $maxPriceInCents // Max price in cents
        );
        
        // Transform products to enriched array format for AI consumption
        return array_map(function ($product) {
            $price = $product->getPrice();
            
            return [
                'id' => (string) $product->getId(),
                'name' => $product->getName(),
                'description' => $product->getDescription(),
                'price' => $price->getAmountAsDecimal(),
                'currency' => $price->getCurrency(),
                'stock' => $product->getStock(),
                'inStock' => $product->isInStock(),
                'category' => $product->getCategory(),
            ];
        }, $products);
    }
}
