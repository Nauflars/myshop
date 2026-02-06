<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Entity\Cart;
use App\Domain\Entity\User;
use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;

/**
 * AddToCartByName Use Case - Add product to cart by name
 * 
 * Adds a product to the user's shopping cart using the product name.
 * Performs case-insensitive search and ensures exact match.
 * Creates a new cart if one doesn't exist.
 * 
 * Architecture: Application layer (use case)
 * DDD Role: Application Service - orchestrates domain logic
 * 
 * @author AI Shopping Assistant Team
 */
final class AddToCartByName
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }
    
    /**
     * Execute the use case
     *
     * @param User $user Authenticated user
     * @param string $productName Product name
     * @param int $quantity Quantity to add (default: 1)
     * @return array{
     *     success: bool,
     *     message: string,
     *     totalItems: int,
     *     totalAmount: float,
     *     currency: string
     * }
     */
    public function execute(User $user, string $productName, int $quantity = 1): array
    {
        if ($quantity <= 0) {
            return [
                'success' => false,
                'message' => 'La cantidad debe ser mayor que cero.',
                'totalItems' => 0,
                'totalAmount' => 0.0,
                'currency' => 'USD',
            ];
        }
        
        // Search for product by name
        $products = $this->productRepository->search(trim($productName), null, null, null);
        
        // Find exact case-insensitive match
        $product = null;
        foreach ($products as $p) {
            if (strcasecmp($p->getName(), trim($productName)) === 0) {
                $product = $p;
                break;
            }
        }
        
        if ($product === null) {
            return [
                'success' => false,
                'message' => sprintf('Producto "%s" no encontrado.', $productName),
                'totalItems' => 0,
                'totalAmount' => 0.0,
                'currency' => 'USD',
            ];
        }
        
        // Check stock
        if (!$product->isInStock()) {
            return [
                'success' => false,
                'message' => sprintf('El producto "%s" está fuera de stock.', $product->getName()),
                'totalItems' => 0,
                'totalAmount' => 0.0,
                'currency' => 'USD',
            ];
        }
        
        if ($product->getStock() < $quantity) {
            return [
                'success' => false,
                'message' => sprintf(
                    'Stock insuficiente para "%s". Disponible: %d, Solicitado: %d',
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
        $totalAmountInCents = 0;
        $currency = 'USD';
        
        foreach ($cart->getItems() as $item) {
            $totalItems += $item->getQuantity();
            $itemPrice = $item->getProduct()->getPrice();
            $totalAmountInCents += $itemPrice->getAmountInCents() * $item->getQuantity();
            $currency = $itemPrice->getCurrency();
        }
        
        $totalAmount = $totalAmountInCents / 100;
        
        return [
            'success' => true,
            'message' => sprintf(
                'Se agregó %d x "%s" al carrito exitosamente.',
                $quantity,
                $product->getName()
            ),
            'totalItems' => $totalItems,
            'totalAmount' => $totalAmount,
            'currency' => $currency,
        ];
    }
}
