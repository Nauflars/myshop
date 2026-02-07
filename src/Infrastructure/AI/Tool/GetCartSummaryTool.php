<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetCartSummary;
use App\Domain\Repository\CartRepositoryInterface;
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
        private readonly Security $security
    ) {
    }

    public function __invoke(): array
    {
        try {
            $user = $this->security->getUser();

            if ($user === null) {
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
                return [
                    'success' => true,
                    'cart' => $summary,
                    'message' => 'Your cart is empty. Would you like to add some products?',
                ];
            }

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
            return [
                'success' => false,
                'cart' => null,
                'message' => 'Could not retrieve cart summary. Please try again.',
            ];
        }
    }
}
