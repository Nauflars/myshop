<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetOrderStatus;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'GetOrderStatusTool',
    'Consultar el estado de un pedido usando su referencia legible (ej: ORD-20260206-001). Usa esta herramienta cuando el usuario pregunte por el estado de un pedido.'
)]
final class GetOrderStatusTool
{
    public function __construct(
        private readonly GetOrderStatus $getOrderStatus,
        private readonly Security $security
    ) {
    }

    /**
     * @param string $orderReference Referencia del pedido (ej: ORD-20260206-001)
     */
    public function __invoke(string $orderReference): array
    {
        try {
            $user = $this->security->getUser();

            if ($user === null) {
                return [
                    'success' => false,
                    'order' => null,
                    'message' => 'Debes iniciar sesión para consultar el estado de tus pedidos.',
                ];
            }

            $order = $this->getOrderStatus->execute($user, $orderReference);

            if ($order === null) {
                return [
                    'success' => false,
                    'order' => null,
                    'message' => "No se encontró el pedido '{$orderReference}'. Verifica la referencia y intenta de nuevo.",
                ];
            }

            return [
                'success' => true,
                'order' => $order,
                'message' => sprintf(
                    "El pedido %s está en estado: %s.",
                    $order['orderReference'],
                    $order['status']
                ),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'order' => null,
                'message' => 'No se pudo consultar el estado del pedido. Por favor intenta de nuevo.',
            ];
        }
    }
}
