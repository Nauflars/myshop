<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Entity\User;
use App\Domain\Repository\OrderRepositoryInterface;

final class ListPreviousOrders
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    /**
     * List previous orders for authenticated user with human-friendly references.
     *
     * @param User $user  The authenticated user
     * @param int  $limit Maximum number of orders to return
     *
     * @return array List of orders with human-friendly data
     */
    public function execute(User $user, int $limit = 10): array
    {
        $orders = $this->orderRepository->findByUser($user);

        // Apply limit
        $orders = array_slice($orders, 0, $limit);

        $result = [];
        foreach ($orders as $order) {
            $total = $order->getTotal();
            $result[] = [
                'orderReference' => $order->getOrderNumber(),
                'date' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
                'total' => $total->getAmountAsDecimal(),
                'currency' => $total->getCurrency(),
                'status' => $this->translateStatus($order->getStatus()),
                'statusCode' => $order->getStatus(),
            ];
        }

        return [
            'orders' => $result,
            'count' => count($result),
        ];
    }

    private function translateStatus(string $status): string
    {
        return match ($status) {
            'PENDING' => 'Pendiente',
            'CONFIRMED' => 'Confirmado',
            'SHIPPED' => 'Enviado',
            'DELIVERED' => 'Entregado',
            'CANCELLED' => 'Cancelado',
            default => 'Desconocido',
        };
    }
}
