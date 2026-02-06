<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Entity\Cart;
use App\Domain\Entity\Order;
use Doctrine\ORM\EntityManagerInterface;

final class CreateOrder
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Create an order from cart with explicit user confirmation
     *
     * @param Cart $cart The user's cart
     * @param array $checkoutInfo Validated checkout information
     * @param bool $userConfirmed User has explicitly confirmed order creation
     * @return array Order result with human-friendly reference
     */
    public function execute(Cart $cart, array $checkoutInfo, bool $userConfirmed): array
    {
        if (!$userConfirmed) {
            return [
                'success' => false,
                'requiresConfirmation' => true,
                'message' => 'Se requiere confirmación explícita del usuario antes de crear el pedido.',
            ];
        }

        if ($cart->isEmpty()) {
            return [
                'success' => false,
                'message' => 'El carrito está vacío. Agrega productos antes de crear un pedido.',
            ];
        }

        // Create order from cart using static factory method
        $order = Order::createFromCart($cart);

        // Persist order
        $this->entityManager->persist($order);
        
        // Clear cart after order creation
        $cart->clear();

        $this->entityManager->flush();

        $orderNumber = $order->getOrderNumber();
        $total = $order->getTotal();

        return [
            'success' => true,
            'orderReference' => $orderNumber,
            'total' => $total->getAmountAsDecimal(),
            'currency' => $total->getCurrency(),
            'status' => $order->getStatus(),
            'message' => "¡Pedido {$orderNumber} creado exitosamente! Total: \${$total->getAmountAsDecimal()}",
        ];
    }
}
