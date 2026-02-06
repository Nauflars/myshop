<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Entity\Cart;

final class GetCartSummary
{
    /**
     * Get detailed cart summary with product names, quantities, and total
     *
     * @param Cart $cart The user's cart
     * @return array Cart summary with line items and total
     */
    public function execute(Cart $cart): array
    {
        $items = [];
        $total = 0.0;

        foreach ($cart->getItems() as $cartItem) {
            $product = $cartItem->getProduct();
            $quantity = $cartItem->getQuantity();
            $subtotal = $product->getPrice() * $quantity;

            $items[] = [
                'productName' => $product->getName(),
                'quantity' => $quantity,
                'unitPrice' => $product->getPrice(),
                'subtotal' => $subtotal,
                'currency' => 'USD',
            ];

            $total += $subtotal;
        }

        return [
            'items' => $items,
            'itemCount' => count($items),
            'total' => $total,
            'currency' => 'USD',
            'isEmpty' => count($items) === 0,
        ];
    }
}
