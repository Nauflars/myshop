<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetCartItems;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GetCartItemsTool - AI Tool for viewing shopping cart
 * 
 * DISABLED: Use GetCartSummaryTool instead for better Spanish descriptions.
 * 
 * This tool enables the AI agent to retrieve the user's current shopping cart contents.
 * It delegates to the GetCartItems use case.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
// #[AsTool('GetCartItems', 'View current cart contents with product names, quantities, prices, and total amount for the authenticated user.')]
class GetCartItemsTool
{
    public function __construct(
        private readonly GetCartItems $getCartItems,
        private readonly Security $security
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
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
    public function __invoke(): array
    {
        try {
            // Get current authenticated user
            $user = $this->security->getUser();
            
            if (!$user instanceof User) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'User must be authenticated to view cart.',
                ];
            }
            
            // Delegate to Application layer use case
            $result = $this->getCartItems->execute($user->getId());
            
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
