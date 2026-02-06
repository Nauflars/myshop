<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\AddToCart;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

/**
 * AddToCartTool - AI Tool for adding products to cart
 * 
 * This tool enables the AI agent to add products to the user's shopping cart.
 * It delegates to the AddToCart use case.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
#[AsTool('AddToCart', 'Add a product to the shopping cart with specified quantity. Requires userId, productId, and quantity (default: 1).')]
final class AddToCartTool
{
    public function __construct(
        private readonly AddToCart $addToCart
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
     * @param string $userId The UUID of the user
     * @param string $productId The UUID of the product to add
     * @param int $quantity The quantity to add (default: 1)
     * @return array{
     *     success: bool,
     *     data: array{
     *         cartId: string|null,
     *         totalItems: int,
     *         totalAmount: float,
     *         currency: string
     *     }|null,
     *     message: string
     * }
     */
    public function __invoke(string $userId, string $productId, int $quantity = 1): array
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
            
            if ($quantity <= 0) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Quantity must be greater than zero.',
                ];
            }
            
            // Delegate to Application layer use case
            $result = $this->addToCart->execute(trim($userId), trim($productId), $quantity);
            
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
                    'cartId' => $result['cartId'],
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
                'message' => 'Failed to add product to cart: ' . $e->getMessage(),
            ];
        }
    }
}
