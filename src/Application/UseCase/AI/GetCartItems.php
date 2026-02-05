<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;

/**
 * GetCartItems Use Case - AI Feature
 * 
 * Returns the current shopping cart contents for a user,
 * including products, quantities, prices, and total amount.
 * 
 * Architecture: Application layer (use case)
 * DDD Role: Application Service - orchestrates domain logic
 * 
 * @author AI Shopping Assistant Team
 */
final class GetCartItems
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }
    
    /**
     * Execute the use case
     *
     * @param string $userId User UUID
     * @return array{
     *     found: bool,
     *     cartId: string|null,
     *     items: array<int, array{
     *         productId: string,
     *         productName: string,
     *         quantity: int,
     *         unitPrice: float,
     *         totalPrice: float,
     *         currency: string
     *     }>,
     *     totalAmount: float,
     *     currency: string,
     *     itemCount: int
     * }
     */
    public function execute(string $userId): array
    {
        // Find user
        $user = $this->userRepository->findById($userId);
        if ($user === null) {
            return [
                'found' => false,
                'cartId' => null,
                'items' => [],
                'totalAmount' => 0.0,
                'currency' => 'USD',
                'itemCount' => 0,
            ];
        }
        
        // Find cart
        $cart = $this->cartRepository->findByUser($user);
        if ($cart === null || $cart->isEmpty()) {
            return [
                'found' => true,
                'cartId' => null,
                'items' => [],
                'totalAmount' => 0.0,
                'currency' => 'USD',
                'itemCount' => 0,
            ];
        }
        
        // Build response with cart items
        $items = [];
        $totalAmount = 0.0;
        
        foreach ($cart->getItems() as $cartItem) {
            $product = $cartItem->getProduct();
            $itemTotal = $product->getPrice() * $cartItem->getQuantity();
            $totalAmount += $itemTotal;
            
            $items[] = [
                'productId' => $product->getId(),
                'productName' => $product->getName(),
                'quantity' => $cartItem->getQuantity(),
                'unitPrice' => $product->getPrice(),
                'totalPrice' => $itemTotal,
                'currency' => 'USD',
            ];
        }
        
        return [
            'found' => true,
            'cartId' => $cart->getId(),
            'items' => $items,
            'totalAmount' => $totalAmount,
            'currency' => 'USD',
            'itemCount' => count($items),
        ];
    }
}
