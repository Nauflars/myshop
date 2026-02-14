<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\AddToCartByName;
use App\Domain\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * AddToCartTool - AI Tool for adding products to cart by name.
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
        private readonly Security $security,
        private readonly LoggerInterface $aiToolsLogger,
    ) {
    }

    /**
     * Execute the tool with parameters from AI agent.
     *
     * @param string $productName The name of the product to add
     * @param int    $quantity    The quantity to add (default: 1)
     *
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
        $this->aiToolsLogger->info('🛍 AddToCartTool called', [
            'product_name' => $productName,
            'quantity' => $quantity,
        ]);

        try {
            // Get authenticated user from Security context
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                $this->aiToolsLogger->warning('AddToCartTool: User not authenticated');

                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'User not authenticated.',
                ];
            }

            // Validate inputs
            if (empty(trim($productName))) {
                $this->aiToolsLogger->warning('AddToCartTool: Product name is empty');

                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Product name is required.',
                ];
            }

            if ($quantity <= 0) {
                $this->aiToolsLogger->warning('AddToCartTool: Invalid quantity', ['quantity' => $quantity]);

                return [
                    'success' => false,
                    'data' => null,
                    'message' => 'Quantity must be greater than zero.',
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
            $this->aiToolsLogger->info('AddToCartTool: Successfully added to cart', [
                'product_name' => $productName,
                'quantity' => $quantity,
                'total_items' => $result['totalItems'],
                'total_amount' => $result['totalAmount'],
            ]);

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
            $this->aiToolsLogger->error('AddToCartTool: Exception occurred', [
                'error' => $e->getMessage(),
                'product_name' => $productName,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'data' => null,
                'message' => 'No se pudo agregar el producto al carrito: '.$e->getMessage(),
            ];
        }
    }
}
