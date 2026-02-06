<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Entity\User;
use App\Domain\Repository\OrderRepositoryInterface;

final class GetOrderStatus
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository
    ) {
    }

    /**
     * Get order status by human-friendly order number
     *
     * @param User $user The authenticated user
     * @param string $orderReference Human-friendly order reference (e.g., 'ORD-20260206-001')
     * @return array|null Order status or null if not found
     */
    public function execute(User $user, string $orderReference): ?array
    {
        // Try to find by order number
        $order = $this->orderRepository->findByOrderNumber($orderReference);

        // Verify the order belongs to the user
        if ($order === null || $order->getUser()->getId() !== $user->getId()) {
            return null;
        }

        $total = $order->getTotal();

        return [
            'orderReference' => $order->getOrderNumber(),
            'date' => $order->getCreatedAt()->format('Y-m-d H:i:s'),
            'total' => $total->getAmountAsDecimal(),
            'currency' => $total->getCurrency(),
            'status' => $this->translateStatus($order->getStatus()),
            'statusCode' => $order->getStatus(),
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
