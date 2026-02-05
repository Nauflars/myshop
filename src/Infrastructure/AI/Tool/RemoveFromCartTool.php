<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\RemoveFromCart;

/**
 * RemoveFromCartTool - AI Tool for removing products from cart
 * 
 * This tool enables the AI agent to remove products from the user's shopping cart.
 * It delegates to the RemoveFromCart use case.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
class RemoveFromCartTool
{
    public function __construct(
        private readonly RemoveFromCart $removeFromCart
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
     * @param string $userId The UUID of the user
     * @param string $productId The UUID of the product to remove
     * @return array{
     *     success: bool,
     *     data: array{
     *         totalItems: int,
     *         totalAmount: float,
     *         currency: string
     *     }|null,
     *     message: string
     * }
     */
    public function __invoke(string $userId, string $productId): array
    {
        try {
            // Validate inputs
            if (empty(trim($userId))) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'User ID is required.',
                ];
            }
            
            if (empty(trim($productId))) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Product ID is required.',
                ];
            }
            
            // Delegate to Application layer use case
            $result = $this->removeFromCart->execute(trim($userId), trim($productId));
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => $result['message'],
                ];
            }
            
            // Format response for AI agent
            return [
                'success' => true,
                'data' => [
                    'totalItems' => $result['totalItems'],
                    'totalAmount' => $result['totalAmount'],
                    'currency' => $result['currency'],
                ],
                'message' => $result['message'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to remove product from cart: ' . $e->getMessage(),
            ];
        }
    }
}
