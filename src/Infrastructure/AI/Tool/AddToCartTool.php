<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\AddToCartByName;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * AddToCartTool - AI Tool for adding products to cart by name
 * 
 * This tool enables the AI agent to add products to the user's shopping cart by product name.
 * Refactored to use name-based interactions and Security context instead of IDs.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
#[AsTool('AddToCart', 'Add a product to the shopping cart with the specified quantity. Requires product name and quantity (default: 1). Does NOT use internal IDs.')]
final class AddToCartTool
{
    public function __construct(
        private readonly AddToCartByName $addToCartByName,
        private readonly Security $security
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
     * @param string $productName The name of the product to add
     * @param int $quantity The quantity to add (default: 1)
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
    public function __invoke(string $productName, int $quantity = 1): array
    {
        try {
            // Get authenticated user from Security context
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'User not authenticated.',
                ];
            }
            
            // Validate inputs
            if (empty(trim($productName))) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Product name is required.',
                ];
            }
            
            if ($quantity <= 0) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'La cantidad debe ser mayor que cero.',
                ];
            }
            
            // Delegate to Application layer use case
            $result = $this->addToCartByName->execute($user, trim($productName), $quantity);
            
            if (!$result['success']) {
                return [
                    'success' => false,
                    'data' => null,
                    'message' => $result['message'],
                ];
            }
            
            // Format response for AI agent (without internal cart ID)
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
                'message' => 'No se pudo agregar el producto al carrito: ' . $e->getMessage(),
            ];
        }
    }
}
