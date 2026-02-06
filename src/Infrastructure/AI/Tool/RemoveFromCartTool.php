<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\RemoveFromCart;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * RemoveFromCartTool - AI Tool for removing products from cart
 * 
 * DISABLED: Use RemoveProductFromCartTool instead - works with product names instead of IDs.
 * 
 * This tool enables the AI agent to remove products from the user's shopping cart.
 * It delegates to the RemoveFromCart use case.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
// #[AsTool('RemoveFromCart', 'Remove a product from the shopping cart completely for the authenticated user. Requires productId.')]
class RemoveFromCartTool
{
    public function __construct(
        private readonly RemoveFromCart $removeFromCart,
        private readonly Security $security
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
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
    public function __invoke(string $productId): array
    {
        try {
            // Get current authenticated user
            $user = $this->security->getUser();
            
            if (!$user instanceof User) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'User must be authenticated.',
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
            $result = $this->removeFromCart->execute($user->getId(), trim($productId));
            
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
