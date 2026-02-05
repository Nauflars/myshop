<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetPriceByProductId;

/**
 * GetPriceByProductIdTool - AI Tool for product price lookup
 * 
 * This tool enables the AI agent to retrieve a product's price and stock information.
 * It delegates to the GetPriceByProductId use case.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
class GetPriceByProductIdTool
{
    public function __construct(
        private readonly GetPriceByProductId $getPriceByProductId
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
     * @param string $productId The UUID of the product to look up
     * @return array{
     *     success: bool,
     *     data: array{
     *         id: string|null,
     *         name: string|null,
     *         price: float|null,
     *         currency: string|null,
     *         inStock: bool,
     *         stockQuantity: int|null
     *     }|null,
     *     message: string
     * }
     */
    public function __invoke(string $productId): array
    {
        try {
            // Validate input
            if (empty(trim($productId))) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Product ID is required.',
                ];
            }
            
            // Delegate to Application layer use case
            $result = $this->getPriceByProductId->execute(trim($productId));
            
            if (!$result['found']) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => "Product with ID '{$productId}' not found.",
                ];
            }
            
            // Format response for AI agent
            return [
                'success' => true,
                'data' => [
                    'id' => $result['id'],
                    'name' => $result['name'],
                    'price' => $result['price'],
                    'currency' => $result['currency'],
                    'inStock' => $result['inStock'],
                    'stockQuantity' => $result['stockQuantity'],
                ],
                'message' => $this->formatMessage($result),
            ];
            
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'data' => null,
                'message' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve product price. Please try again.',
            ];
        }
    }
    
    /**
     * Format a user-friendly message
     */
    private function formatMessage(array $result): string
    {
        $currencySymbol = $this->getCurrencySymbol($result['currency']);
        $priceFormatted = number_format($result['price'], 2);
        
        $message = "{$result['name']} costs {$currencySymbol}{$priceFormatted}";
        
        if ($result['inStock']) {
            $message .= " and is currently in stock ({$result['stockQuantity']} units available).";
        } else {
            $message .= " but is currently out of stock.";
        }
        
        return $message;
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
