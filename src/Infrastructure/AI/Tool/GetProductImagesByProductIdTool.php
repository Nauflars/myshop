<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetProductImagesByProductId;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * GetProductImagesByProductIdTool - AI Tool for product image retrieval
 * 
 * This tool enables the AI agent to retrieve product image URLs.
 * It delegates to the GetProductImagesByProductId use case.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
#[AsTool('GetProductImagesByProductId', 'Retrieve all images for a specific product by its ID. Returns image URLs and metadata.')]
class GetProductImagesByProductIdTool
{
    public function __construct(
        private readonly GetProductImagesByProductId $getProductImagesByProductId
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
     * @param string $productId The UUID of the product to retrieve images for
     * @return array{
     *     success: bool,
     *     data: array{
     *         productId: string,
     *         productName: string|null,
     *         images: array<int, string>,
     *         imageCount: int
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
            $result = $this->getProductImagesByProductId->execute(trim($productId));
            
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
                    'productId' => $result['productId'],
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
                'message' => 'Failed to retrieve product images. Please try again.',
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
            return "No images available for {$result['productName']}.";
        }
        
        $plural = $imageCount > 1 ? 's' : '';
        return "Found {$imageCount} image{$plural} for {$result['productName']}.";
    }
}
