<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetProductsName;

/**
 * GetProductsNameTool - AI Tool for product discovery
 * 
 * This tool enables the AI agent to retrieve product names and categories
 * from the catalog. It delegates to the GetProductsName use case.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
class GetProductsNameTool
{
    public function __construct(
        private readonly GetProductsName $getProductsName
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
     * @param string|null $searchTerm Optional search term for filtering products by name or description
     * @param string|null $category Optional category filter (e.g., "Electronics", "Clothing", "Books")
     * @return array{
     *     success: bool,
     *     data: array<int, array{id: string, name: string, category: string}>,
     *     count: int,
     *     message: string
     * }
     */
    public function __invoke(?string $searchTerm = null, ?string $category = null): array
    {
        try {
            // Delegate to Application layer use case
            $products = $this->getProductsName->execute($searchTerm, $category);
            
            // Format response for AI agent
            return [
                'success' => true,
                'data' => $products,
                'count' => count($products),
                'message' => $this->formatMessage($products, $searchTerm, $category),
            ];
            
        } catch (\Exception $e) {
            // Handle errors gracefully
            return [
                'success' => false,
                'data' => [],
                'count' => 0,
                'message' => 'Failed to retrieve products. Please try again.',
            ];
        }
    }
    
    /**
     * Format a user-friendly message based on results
     */
    private function formatMessage(array $products, ?string $searchTerm, ?string $category): string
    {
        $count = count($products);
        
        if ($count === 0) {
            if ($searchTerm && $category) {
                return "No products found matching '{$searchTerm}' in category '{$category}'.";
            } elseif ($searchTerm) {
                return "No products found matching '{$searchTerm}'.";
            } elseif ($category) {
                return "No products found in category '{$category}'.";
            }
            return 'No products found in the catalog.';
        }
        
        $suffix = $searchTerm ? " matching '{$searchTerm}'" : '';
        $suffix .= $category ? " in category '{$category}'" : '';
        
        return "Found {$count} product" . ($count > 1 ? 's' : '') . $suffix . '.';
    }
}
