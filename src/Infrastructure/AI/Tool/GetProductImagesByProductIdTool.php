<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetProductImagesByProductName;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * GetProductImagesByProductIdTool - AI Tool for product image retrieval by name
 * 
 * This tool enables the AI agent to retrieve product image URLs by name.
 * Refactored to use name-based interactions instead of internal IDs.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
#[AsTool('GetProductImagesByProductId', 'Get all images of a specific product by its name. Returns image URLs and metadata. Does NOT use internal IDs.')]
class GetProductImagesByProductIdTool
{
    public function __construct(
        private readonly GetProductImagesByProductName $getProductImagesByProductName
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
     * @param string $productName The name of the product to retrieve images for
     * @return array{
     *     success: bool,
     *     data: array{
     *         productName: string,
     *         images: array<int, string>,
     *         imageCount: int
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
            $result = $this->getProductImagesByProductName->execute(trim($productName));
            
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
                    'productName' => $result['productName'],
                    'images' => $result['images'],
                    'imageCount' => count($result['images']),
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
                'message' => 'Could not retrieve product images. Please try again.',
            ];
        }
    }
    
    /**
     * Format a user-friendly message
     */
    private function formatMessage(array $result): string
    {
        $imageCount = count($result['images']);
        
        if ($imageCount === 0) {
            return "No hay imágenes disponibles para {$result['productName']}.";
        }
        
        $plural = $imageCount > 1 ? 's' : '';
        return "Se encontraron {$imageCount} imagen{$plural} para {$result['productName']}.";
    }
}
