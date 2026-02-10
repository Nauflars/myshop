<?php

namespace App\Application\UseCase;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\Entity\Cart;
use App\Domain\Entity\Order;
use App\Domain\Entity\User;
use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\EventType;
use App\Infrastructure\Queue\RabbitMQPublisher;

final class Checkout
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly RabbitMQPublisher $rabbitMQPublisher
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
        
        // spec-014: Publish purchase events to queue for user embedding updates
        try {
            $occurredAt = new \DateTimeImmutable();
            foreach ($order->getItems() as $orderItem) {
                $message = UpdateUserEmbeddingMessage::fromDomainEvent(
                    userId: $user->getId(),
                    eventType: EventType::PRODUCT_PURCHASE,
                    searchPhrase: null,
                    productId: (int) $orderItem->getProduct()->getId(),
                    occurredAt: $occurredAt
                );
                $this->rabbitMQPublisher->publish($message);
            }
        } catch (\Exception $e) {
            // Log but don't fail checkout - event publishing is non-critical
            error_log('Failed to publish purchase events: ' . $e->getMessage());
        }

        return $order;
    }
}
