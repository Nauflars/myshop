<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Repository\ProductRepositoryInterface;

/**
 * GetProductsName Use Case - Retrieve product names for AI assistant
 * 
 * This use case fetches products from the catalog and returns their IDs, names, and categories.
 * Used by AI tools to provide product information to users.
 * 
 * Architecture: Application layer (business logic)
 * DDD Role: Use case / application service
 * 
 * @author AI Shopping Assistant Team
 */
class GetProductsName
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }
    
    /**
     * Execute the use case
     *
     * @param string|null $searchTerm Optional search term for filtering
     * @param string|null $category Optional category filter
     * @return array<int, array{id: string, name: string, category: string}> Array of products
     */
    public function execute(?string $searchTerm = null, ?string $category = null): array
    {
        // If search term or category provided, use search method
        if ($searchTerm !== null || $category !== null) {
            $products = $this->productRepository->search(
                $searchTerm ?? '',
                $category
            );
        } else {
            // Otherwise, return all products
            $products = $this->productRepository->findAll();
        }
        
        // Transform products to simple array format for AI consumption
        return array_map(function ($product) {
            return [
                'id' => (string) $product->getId(),
                'name' => $product->getName(),
                'category' => $product->getCategory(),
            ];
        }, $products);
    }
}
