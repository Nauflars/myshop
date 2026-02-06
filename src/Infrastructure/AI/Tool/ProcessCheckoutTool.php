<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\ProcessCheckout;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * ProcessCheckoutTool - AI Tool for processing orders
 * 
 * This tool enables the AI agent to complete checkout and create orders.
 * It delegates to the ProcessCheckout use case.
 * 
 * Architecture: Infrastructure layer (AI adapter)
 * DDD Role: Technical adapter - NO business logic
 * 
 * @author AI Shopping Assistant Team
 */
#[AsTool('ProcessCheckout', 'Complete the order, update stock, and clear cart for the authenticated user. Returns order number and confirmation.')]
class ProcessCheckoutTool
{
    public function __construct(
        private readonly ProcessCheckout $processCheckout,
        private readonly Security $security
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
     * @return array{
     *     success: bool,
     *     data: array{
     *         orderId: string|null,
     *         orderNumber: string|null,
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
                    'message' => 'User must be authenticated to checkout.',
                ];
            }
            
            // Delegate to Application layer use case
            $result = $this->processCheckout->execute($user->getId());
            
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
                    'orderId' => $result['orderId'],
                    'orderNumber' => $result['orderNumber'],
                    'totalAmount' => $result['totalAmount'],
                    'currency' => $result['currency'],
                    'itemCount' => $result['itemCount'],
                ],
                'message' => $result['message'],
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => null,
                'message' => 'Failed to process checkout: ' . $e->getMessage(),
            ];
        }
    }
}
