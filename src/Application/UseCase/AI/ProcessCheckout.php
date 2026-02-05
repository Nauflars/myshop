<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Entity\Order;
use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;

/**
 * ProcessCheckout Use Case - AI Feature
 * 
 * Processes checkout: validates cart, creates order, updates stock, and clears cart.
 * 
 * Architecture: Application layer (use case)
 * DDD Role: Application Service - orchestrates domain logic
 * 
 * @author AI Shopping Assistant Team
 */
final class ProcessCheckout
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }
    
    /**
     * Execute the use case
     *
     * @param string $userId User UUID
     * @return array{
     *     success: bool,
     *     orderId: string|null,
     *     orderNumber: string|null,
     *     totalAmount: float,
     *     currency: string,
     *     itemCount: int,
     *     message: string
     * }
     */
    public function execute(string $userId): array
    {
        // Find user
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            return [
                'success' => false,
                'orderId' => null,
                'orderNumber' => null,
                'totalAmount' => 0.0,
                'currency' => 'USD',
                'itemCount' => 0,
                'message' => 'User not found.',
            ];
        }
        
        // Find cart
        $cart = $this->cartRepository->findByUser($user);
        if ($cart === null || $cart->isEmpty()) {
            return [
                'success' => false,
                'orderId' => null,
                'orderNumber' => null,
                'totalAmount' => 0.0,
                'currency' => 'USD',
                'itemCount' => 0,
                'message' => 'Cart is empty. Cannot proceed with checkout.',
            ];
        }
        
        // Validate stock for all items
        foreach ($cart->getItems() as $cartItem) {
            $product = $cartItem->getProduct();
            if ($product->getStock() < $cartItem->getQuantity()) {
                return [
                    'success' => false,
                    'orderId' => null,
                    'orderNumber' => null,
                    'totalAmount' => 0.0,
                    'currency' => 'USD',
                    'itemCount' => 0,
                    'message' => sprintf(
                        'Insufficient stock for "%s". Available: %d, Required: %d',
                        $product->getName(),
                        $product->getStock(),
                        $cartItem->getQuantity()
                    ),
                ];
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
        $this->cartRepository->delete($cart);
        
        // Calculate order details
        $totalAmount = 0.0;
        $itemCount = 0;
        foreach ($order->getItems() as $item) {
            $totalAmount += $item->getPrice() * $item->getQuantity();
            $itemCount += $item->getQuantity();
        }
        
        return [
            'success' => true,
            'orderId' => $order->getId(),
            'orderNumber' => $order->getOrderNumber(),
            'totalAmount' => $totalAmount,
            'currency' => 'USD',
            'itemCount' => $itemCount,
            'message' => sprintf(
                'Order #%s placed successfully! Total: $%.2f for %d item(s).',
                $order->getOrderNumber(),
                $totalAmount,
                $itemCount
            ),
        ];
    }
}
