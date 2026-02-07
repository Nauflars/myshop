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
        if ($cart->isEmpty()) {
            return [
                'items' => [],
                'itemCount' => 0,
                'total' => 0.0,
                'currency' => 'USD',
                'isEmpty' => true,
            ];
        }

        $items = [];
        
        // Use Cart's calculateTotal() method which handles currency correctly
        $totalMoney = $cart->calculateTotal();

        foreach ($cart->getItems() as $cartItem) {
            $product = $cartItem->getProduct();
            $quantity = $cartItem->getQuantity();
            $priceSnapshot = $cartItem->getPriceSnapshot();
            $subtotal = $priceSnapshot->multiply($quantity);

            $items[] = [
                'productName' => $product->getName(),
                'quantity' => $quantity,
                'unitPrice' => $priceSnapshot->getAmountAsDecimal(),
                'subtotal' => $subtotal->getAmountAsDecimal(),
                'currency' => $priceSnapshot->getCurrency(),
            ];
        }

        return [
            'items' => $items,
            'itemCount' => count($items),
            'total' => $totalMoney->getAmountAsDecimal(),
            'currency' => $totalMoney->getCurrency(),
            'isEmpty' => false,
        ];
    }
}
