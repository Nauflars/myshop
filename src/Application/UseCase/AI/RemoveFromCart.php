<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;

/**
 * RemoveFromCart Use Case - AI Feature
 * 
 * Removes a product from the user's shopping cart.
 * 
 * Architecture: Application layer (use case)
 * DDD Role: Application Service - orchestrates domain logic
 * 
 * @author AI Shopping Assistant Team
 */
final class RemoveFromCart
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }
    
    /**
     * Execute the use case
     *
     * @param string $userId User UUID
     * @param string $productId Product UUID to remove
     * @return array{
     *     success: bool,
     *     message: string,
     *     totalItems: int,
     *     totalAmount: float,
     *     currency: string
     * }
     */
    public function execute(string $userId, string $productId): array
    {
        // Find user
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            return [
                'success' => false,
                'message' => 'User not found.',
                'totalItems' => 0,
                'totalAmount' => 0.0,
                'currency' => 'USD',
            ];
        }
        
        // Find cart
        $cart = $this->cartRepository->findByUser($user);
        if ($cart === null || $cart->isEmpty()) {
            return [
                'success' => false,
                'message' => 'Cart is empty.',
                'totalItems' => 0,
                'totalAmount' => 0.0,
                'currency' => 'USD',
            ];
        }
        
        // Find product
        $product = $this->productRepository->findById($productId);
        if ($product === null) {
            return [
                'success' => false,
                'message' => 'Product not found.',
                'totalItems' => 0,
                'totalAmount' => 0.0,
                'currency' => 'USD',
            ];
        }
        
        // Check if product is in cart
        $cartItem = $cart->findItemByProduct($product);
        if ($cartItem === null) {
            return [
                'success' => false,
                'message' => sprintf('Product "%s" is not in the cart.', $product->getName()),
                'totalItems' => 0,
                'totalAmount' => 0.0,
                'currency' => 'USD',
            ];
        }
        
        // Remove product from cart
        $cart->removeItemByProduct($product);
        $this->cartRepository->save($cart);
        
        // Calculate new totals
        $totalItems = 0;
        $totalAmount = 0.0;
        foreach ($cart->getItems() as $item) {
            $totalItems += $item->getQuantity();
            $totalAmount += $item->getProduct()->getPrice() * $item->getQuantity();
        }
        
        return [
            'success' => true,
            'message' => sprintf('Removed "%s" from cart successfully.', $product->getName()),
            'totalItems' => $totalItems,
            'totalAmount' => $totalAmount,
            'currency' => 'USD',
        ];
    }
}
