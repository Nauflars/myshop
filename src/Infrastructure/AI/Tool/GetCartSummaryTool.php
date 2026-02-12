<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetCartSummary;
use App\Domain\Repository\CartRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'GetCartSummaryTool',
    'Get complete cart summary with product names, quantities, prices, and total. Use this tool when the user wants to view their cart.'
)]
final class GetCartSummaryTool
{
    public function __construct(
        private readonly GetCartSummary $getCartSummary,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Security $security,
        private readonly LoggerInterface $aiToolsLogger
    ) {
    }

    public function __invoke(): array
    {
        $this->aiToolsLogger->info('🛒 GetCartSummaryTool called');
        
        try {
            $user = $this->security->getUser();

            if ($user === null) {
                $this->aiToolsLogger->warning('⚠️ GetCartSummaryTool: User not authenticated');
                return [
                    'success' => false,
                    'message' => 'You must log in to view your cart.',
                    'cart' => null,
                ];
            }

            $cart = $this->cartRepository->findByUser($user);

            if ($cart === null) {
                $cart = new \App\Domain\Entity\Cart($user);
                $this->cartRepository->save($cart);
            }

            $summary = $this->getCartSummary->execute($cart);

            if ($summary['isEmpty']) {
                $this->aiToolsLogger->info('🛒 Cart is empty');
                return [
                    'success' => true,
                    'cart' => $summary,
                    'message' => 'Your cart is empty. Would you like to add some products?',
                ];
            }

            $this->aiToolsLogger->info('✅ Cart summary retrieved', [
                'item_count' => $summary['itemCount'],
                'total' => $summary['total']
            ]);
            
            return [
                'success' => true,
                'cart' => $summary,
                'message' => sprintf(
                    'Tu carrito contiene %d producto(s) por un total de $%.2f.',
                    $summary['itemCount'],
                    $summary['total']
                ),
            ];
        } catch (\Exception $e) {
            $this->aiToolsLogger->error('❌ GetCartSummaryTool failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'cart' => null,
                'message' => 'Could not retrieve cart summary. Please try again.',
            ];
        }
    }
}
