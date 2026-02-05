<?php

namespace App\Application\UseCase;

use App\Domain\Entity\Cart;
use App\Domain\Entity\User;
use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;

final class AddProductToCart
{
    public function __construct(
        private readonly CartRepositoryInterface $cartRepository,
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    public function execute(User $user, string $productId, int $quantity = 1): Cart
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero');
        }

        // Find or create cart for user
        $cart = $this->cartRepository->findByUser($user);
        if ($cart === null) {
            $cart = new Cart($user);
        }

        // Find product
        $product = $this->productRepository->findById($productId);
        if ($product === null) {
            throw new \InvalidArgumentException('Product not found');
        }

        // Check stock availability
        if (!$product->isInStock()) {
            throw new \InvalidArgumentException('Product is out of stock');
        }

        if ($product->getStock() < $quantity) {
            throw new \InvalidArgumentException(
                sprintf('Insufficient stock. Available: %d', $product->getStock())
            );
        }

        // Add product to cart
        $cart->addProduct($product, $quantity);

        $this->cartRepository->save($cart);

        return $cart;
    }
}
