<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetPriceByProductName;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * GetPriceByProductIdTool - AI Tool for product price lookup by name
 * 
 * This tool enables the AI agent to retrieve a product's price and stock information by name.
 * Refactored to use name-based interactions instead of internal IDs.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
#[AsTool('GetPriceByProductId', 'Obtener precio detallado, moneda y estado de stock para un producto específico por su nombre. NO usa IDs internos.')]
class GetPriceByProductIdTool
{
    public function __construct(
        private readonly GetPriceByProductName $getPriceByProductName
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
     * @param string $productName The name of the product to look up
     * @return array{
     *     success: bool,
     *     data: array{
     *         name: string|null,
     *         description: string|null,
     *         price: float|null,
     *         currency: string|null,
     *         inStock: bool,
     *         stockQuantity: int|null
     *     }|null,
     *     message: string
     * }
     */
    public function __invoke(string $productName): array
    {
        try {
            // Validate input
            if (empty(trim($productName))) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'El nombre del producto es requerido.',
                ];
            }
            
            // Delegate to Application layer use case
            $result = $this->getPriceByProductName->execute(trim($productName));
            
            if (!$result['found']) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => "Producto con nombre '{$productName}' no encontrado.",
                ];
            }
            
            // Format response for AI agent (without internal ID)
            return [
                'success' => true,
                'data' => [
                    'name' => $result['name'],
                    'description' => $result['description'],
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
                'message' => 'No se pudo recuperar el precio del producto. Por favor, intente nuevamente.',
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
        
        $message = "{$result['name']} cuesta {$currencySymbol}{$priceFormatted}";
        
        if ($result['inStock']) {
            $message .= " y actualmente está en stock ({$result['stockQuantity']} unidades disponibles).";
        } else {
            $message .= " pero actualmente está fuera de stock.";
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
