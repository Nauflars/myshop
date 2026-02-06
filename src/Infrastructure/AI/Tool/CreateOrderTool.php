<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\CreateOrder;
use App\Domain\Repository\CartRepositoryInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'CreateOrderTool',
    'Crear un pedido a partir del carrito actual. REQUIERE confirmación explícita del usuario. Usa esta herramienta solo después de que el usuario confirme.'
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
                    'message' => 'Debes iniciar sesión para crear un pedido.',
                ];
            }

            $cart = $this->cartRepository->findByUser($user);

            if ($cart === null || $cart->getItems()->count() === 0) {
                return [
                    'success' => false,
                    'message' => 'Tu carrito está vacío. Agrega productos antes de crear un pedido.',
                ];
            }

            $result = $this->createOrder->execute($cart, $checkoutInfo, $userConfirmed);

            if (isset($result['requiresConfirmation']) && $result['requiresConfirmation']) {
                return [
                    'success' => false,
                    'requiresConfirmation' => true,
                    'message' => '¿Confirmas que deseas crear el pedido con los productos en tu carrito? Responde "sí" o "confirmo" para proceder.',
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
