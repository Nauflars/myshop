<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Entity\Cart;
use App\Domain\Repository\ProductRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class RemoveProductFromCart
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Remove product from cart by product name.
     *
     * @param Cart   $cart        The user's cart
     * @param string $productName The name of the product to remove
     *
     * @return array Result with success status and message
     */
    public function execute(Cart $cart, string $productName): array
    {
        $products = $this->productRepository->findAll();

        // Find product by name (case-insensitive)
        $targetProduct = null;
        foreach ($products as $product) {
            if (0 === strcasecmp($product->getName(), $productName)) {
                $targetProduct = $product;
                break;
            }
        }

        if (null === $targetProduct) {
            return [
                'success' => false,
                'message' => "No se encontró el producto '{$productName}' en el catálogo.",
            ];
        }

        // Find and remove cart item
        $cartItems = $cart->getItems();
        $removed = false;

        foreach ($cartItems as $item) {
            if ($item->getProduct()->getId() === $targetProduct->getId()) {
                $cart->removeItem($item);
                $this->entityManager->flush();
                $removed = true;
                break;
            }
        }

        if ($removed) {
            return [
                'success' => true,
                'message' => "Se eliminó '{$productName}' del carrito.",
                'cartItemCount' => $cart->getItems()->count(),
            ];
        }

        return [
            'success' => false,
            'message' => "El producto '{$productName}' no estaba en tu carrito.",
        ];
    }
}
