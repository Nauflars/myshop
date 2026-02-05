<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\Entity\Cart;

/**
 * AddToCart Use Case - AI Feature
 * 
 * Adds a product to the user's shopping cart with specified quantity.
 * Creates a new cart if one doesn't exist.
 * 
 * Architecture: Application layer (use case)
 * DDD Role: Application Service - orchestrates domain logic
 * 
 * @author AI Shopping Assistant Team
 */
final class AddToCart
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
     * @param string $productId Product UUID
     * @param int $quantity Quantity to add (default: 1)
     * @return array{
     *     success: bool,
     *     cartId: string|null,
     *     message: string,
     *     totalItems: int,
     *     totalAmount: float,
     *     currency: string
     * }
     */
    public function execute(string $userId, string $productId, int $quantity = 1): array
    {
        if ($quantity <= 0) {
            return [
                'success' => false,
                'cartId' => null,
                'message' => 'Quantity must be greater than zero.',
                'totalItems' => 0,
                'totalAmount' => 0.0,
                'currency' => 'USD',
            ];
        }
        
        // Find user
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            return [
                'success' => false,
                'cartId' => null,
                'message' => 'User not found.',
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
                'cartId' => null,
                'message' => 'Product not found.',
                'totalItems' => 0,
                'totalAmount' => 0.0,
                'currency' => 'USD',
            ];
        }
        
        // Check stock
        if (!$product->isInStock()) {
            return [
                'success' => false,
                'cartId' => null,
                'message' => sprintf('Product "%s" is out of stock.', $product->getName()),
                'totalItems' => 0,
                'totalAmount' => 0.0,
                'currency' => 'USD',
            ];
        }
        
        if ($product->getStock() < $quantity) {
            return [
                'success' => false,
                'cartId' => null,
                'message' => sprintf(
                    'Insufficient stock for "%s". Available: %d, Requested: %d',
                    $product->getName(),
                    $product->getStock(),
                    $quantity
                ),
                'totalItems' => 0,
                'totalAmount' => 0.0,
                'currency' => 'USD',
            ];
        }
        
        // Find or create cart
        $cart = $this->cartRepository->findByUser($user);
        if ($cart === null) {
            $cart = new Cart($user);
        }
        
        // Add product to cart
        $cart->addProduct($product, $quantity);
        $this->cartRepository->save($cart);
        
        // Calculate totals
        $totalItems = 0;
        $totalAmount = 0.0;
        foreach ($cart->getItems() as $item) {
            $totalItems += $item->getQuantity();
            $totalAmount += $item->getProduct()->getPrice() * $item->getQuantity();
        }
        
        return [
            'success' => true,
            'cartId' => $cart->getId(),
            'message' => sprintf(
                'Added %d x "%s" to cart successfully.',
                $quantity,
                $product->getName()
            ),
            'totalItems' => $totalItems,
            'totalAmount' => $totalAmount,
            'currency' => 'USD',
        ];
    }
}
