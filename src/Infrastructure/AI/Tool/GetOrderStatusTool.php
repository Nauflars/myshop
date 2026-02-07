<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetOrderStatus;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'GetOrderStatusTool',
    'Check order status using its readable reference (e.g., ORD-20260206-001). Use this tool when the user asks about an order\'s status.'
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
                    'message' => 'You must log in to check the status of your orders.',
                ];
            }

            $order = $this->getOrderStatus->execute($user, $orderReference);

            if ($order === null) {
                return [
                    'success' => false,
                    'order' => null,
                    'message' => "Order '{$orderReference}' not found. Please verify the reference and try again.",
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
