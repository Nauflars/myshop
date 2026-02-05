<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\ProcessCheckout;

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
class ProcessCheckoutTool
{
    public function __construct(
        private readonly ProcessCheckout $processCheckout
    ) {
    }
    
    /**
     * Execute the tool with parameters from AI agent
     *
     * @param string $userId The UUID of the user placing the order
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
            $result = $this->processCheckout->execute(trim($userId));
            
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
