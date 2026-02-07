<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\CreateOrder;
use App\Domain\Repository\CartRepositoryInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'CreateOrderTool',
    'Create an order from the current cart. REQUIRES explicit user confirmation. Use this tool only after the user confirms.'
)]
final class CreateOrderTool
{
    public function __construct(
        private readonly CreateOrder $createOrder,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly Security $security
    ) {
    }

    /**
     * @param array $checkoutInfo Información de checkout validada
     * @param bool $userConfirmed El usuario ha confirmado explícitamente la creación del pedido
     */
    public function __invoke(array $checkoutInfo, bool $userConfirmed = false): array
    {
        try {
            $user = $this->security->getUser();

            if ($user === null) {
                return [
                    'success' => false,
                    'message' => 'You must log in to create an order.',
                ];
            }

            $cart = $this->cartRepository->findByUser($user);

            if ($cart === null || $cart->getItems()->count() === 0) {
                return [
                    'success' => false,
                    'message' => 'Your cart is empty. Add products before creating an order.',
                ];
            }

            $result = $this->createOrder->execute($cart, $checkoutInfo, $userConfirmed);

            if (isset($result['requiresConfirmation']) && $result['requiresConfirmation']) {
                return [
                    'success' => false,
                    'requiresConfirmation' => true,
                    'message' => 'Do you confirm that you want to create the order with the products in your cart? Respond "yes" or "I confirm" to proceed.',
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'No se pudo crear el pedido. Por favor intenta de nuevo.',
            ];
        }
    }
}
