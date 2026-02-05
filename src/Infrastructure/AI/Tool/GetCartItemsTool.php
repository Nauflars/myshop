<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetCartItems;

/**
 * GetCartItemsTool - AI Tool for viewing shopping cart
 * 
 * This tool enables the AI agent to retrieve the user's current shopping cart contents.
 * It delegates to the GetCartItems use case.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
class GetCartItemsTool
{
    public function __construct(
        private readonly GetCartItems $getCartItems
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
     * @param string $userId The UUID of the user whose cart to retrieve
     * @return array{
     *     success: bool,
     *     data: array{
     *         cartId: string|null,
     *         items: array<int, array{
     *             productId: string,
     *             productName: string,
             *             quantity: int,
     *             unitPrice: float,
     *             totalPrice: float,
     *             currency: string
     *         }>,
     *         totalAmount: float,
     *         currency: string,
     *         itemCount: int
     *     }|null,
     *     message: string
     * }
     */
    public function __invoke(string $userId): array
    {
        try {
            // Validate input
            if (empty(trim($userId))) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'User ID is required.',
                ];
            }
            
            // Delegate to Application layer use case
            $result = $this->getCartItems->execute(trim($userId));
            
            if (!$result['found']) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'User not found.',
                ];
            }
            
            if ($result['itemCount'] === 0) {
                return [
                    'success' => true,
                    'data' => [
                        'cartId' => $result['cartId'],
                        'items' => [],
                        'totalAmount' => 0.0,
                        'currency' => 'USD',
                        'itemCount' => 0,
                    ],
                    'message' => 'Your cart is empty.',
                ];
            }
            
            // Format response for AI agent
            return [
                'success' => true,
                'data' => [
                    'cartId' => $result['cartId'],
                    'items' => $result['items'],
                    'totalAmount' => $result['totalAmount'],
                    'currency' => $result['currency'],
                    'itemCount' => $result['itemCount'],
                ],
                'message' => sprintf(
                    'Cart contains %d item(s) with total of $%.2f.',
                    $result['itemCount'],
                    $result['totalAmount']
                ),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to retrieve cart: ' . $e->getMessage(),
            ];
        }
    }
}
