<?php

namespace App\Application\UseCase;

use App\Domain\Entity\Cart;
use App\Domain\Entity\Order;
use App\Domain\Entity\User;
use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;

final class Checkout
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function execute(User $user): Order
    {
        // Get user's cart
        $cart = $this->cartRepository->findByUser($user);
        if ($cart === null || $cart->isEmpty()) {
            throw new \InvalidArgumentException('Cart is empty');
        }

        // Validate stock availability for all items
        foreach ($cart->getItems() as $cartItem) {
            $product = $cartItem->getProduct();
            if ($product->getStock() < $cartItem->getQuantity()) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Insufficient stock for product "%s". Available: %d, Requested: %d',
                        $product->getName(),
                        $product->getStock(),
                        $cartItem->getQuantity()
                    )
                );
            }
        }

        // Create order from cart
        $order = Order::createFromCart($cart);

        // Decrement stock for each product
        foreach ($order->getItems() as $orderItem) {
            $product = $orderItem->getProduct();
            $product->decrementStock($orderItem->getQuantity());
            $this->productRepository->save($product);
        }

        // Save order
        $this->orderRepository->save($order);

        // Clear cart
        $cart->clear();
        $this->cartRepository->save($cart);

        return $order;
    }
}
