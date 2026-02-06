<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetProductsName;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('GetProductsNameTool', 'Browse and search products by name or category. Returns a list of products with their IDs, names, and categories.')]
final class GetProductsNameTool
{
    public function __construct(
        private readonly GetProductsName $getProductsName
    ) {
    }

    public function __invoke(?string $searchTerm = null, ?string $category = null): array
    {
        try {
            $products = $this->getProductsName->execute($searchTerm, $category);

            return [
                'success' => true,
                'data' => $products,
                'count' => count($products),
                'message' => $this->formatMessage($products, $searchTerm, $category),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => [],
                'count' => 0,
                'message' => 'Failed to retrieve products. Please try again.',
            ];
        }
    }
    

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
