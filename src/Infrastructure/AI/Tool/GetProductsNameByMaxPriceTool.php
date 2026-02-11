<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetProductsNameByMaxPrice;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;


#[AsTool('GetProductsNameByMaxPrice', 'Search products within a budget. Returns products with prices less than or equal to the specified maximum price. Does NOT return internal IDs.')]
final class GetProductsNameByMaxPriceTool
{
    public function __construct(
        private readonly GetProductsNameByMaxPrice $getProductsNameByMaxPrice
    ) {
    }

    public function __invoke(float $maxPrice, string $currency = 'USD', ?string $category = null): array
    {
        try {
            // Validate inputs
            if ($maxPrice <= 0) {
                return [
                    'success' => false,
                    'data' => [],
                    'count' => 0,
                    'message' => 'Maximum price must be greater than zero.',
                ];
            }
            
            // Delegate to Application layer use case
            $products = $this->getProductsNameByMaxPrice->execute($maxPrice, $currency, $category);
            
            // Remove IDs from response to prevent exposure
            $productsWithoutIds = array_map(function($product) {
                unset($product['id']);
                return $product;
            }, $products);
            
            // Format response for AI agent
            return [
                'success' => true,
                'data' => $productsWithoutIds,
                'count' => count($productsWithoutIds),
                'message' => $this->formatMessage($productsWithoutIds, $maxPrice, $currency, $category),
            ];
            
        } catch (\Exception $e) {
            // Handle errors gracefully
            return [
                'success' => false,
                'data' => [],
                'count' => 0,
                'message' => 'Could not retrieve products by price. Please try again.',
            ];
        }
    }
    
    /**
     * Format a user-friendly message based on results
     */
    private function formatMessage(array $products, float $maxPrice, string $currency, ?string $category): string
    {
        $count = count($products);
        $priceFormatted = number_format($maxPrice, 2);
        $currencySymbol = $this->getCurrencySymbol($currency);
        
        if ($count === 0) {
            $message = "No products found below {$currencySymbol}{$priceFormatted}";
            if ($category) {
                $message .= " in category '{$category}'";
            }
            return $message . '.';
        }
        
        $message = "Found {$count} product" . ($count > 1 ? 's' : '') . " below {$currencySymbol}{$priceFormatted}";
        if ($category) {
            $message .= " in category '{$category}'";
        }
        
        return $message . '.';
    }
    
    /**
     * Get currency symbol for display
     */
    private function getCurrencySymbol(string $currency): string
    {
        return match (strtoupper($currency)) {
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY' => '¥',
            default => $currency . ' ',
        };
    }
}
