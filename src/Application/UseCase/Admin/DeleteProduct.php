<?php

declare(strict_types=1);

namespace App\Application\UseCase\Admin;

use App\Domain\Entity\Product;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;

/**
 * DeleteProduct - Admin use case to delete products.
 *
 * Part of spec-007: Admin Virtual Assistant
 * Validates deletion is safe (no associated orders) before removing product
 */
class DeleteProduct
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly OrderRepositoryInterface $orderRepository,
    ) {
    }

    /**
     * Check if product can be safely deleted.
     *
     * @return array{can_delete: bool, reason: string|null, order_count: int}
     */
    public function canDelete(Product $product): array
    {
        // Check for associated orders
        $orderCount = $this->orderRepository->countByProduct($product);

        if ($orderCount > 0) {
            return [
                'can_delete' => false,
                'reason' => "No se puede eliminar porque tiene {$orderCount} pedido(s) asociado(s)",
                'order_count' => $orderCount,
            ];
        }

        return [
            'can_delete' => true,
            'reason' => null,
            'order_count' => 0,
        ];
    }

    /**
     * Delete product.
     *
     * @throws \RuntimeException if product cannot be deleted
     */
    public function execute(Product $product, bool $force = false): void
    {
        // Check if deletion is safe
        $deleteCheck = $this->canDelete($product);

        if (!$deleteCheck['can_delete'] && !$force) {
            throw new \RuntimeException($deleteCheck['reason']);
        }

        // Perform deletion
        $this->productRepository->delete($product);
    }

    /**
     * Find products by name for deletion.
     *
     * @return Product[]
     */
    public function findProductsByName(string $name): array
    {
        return $this->productRepository->findByName($name);
    }

    /**
     * Get product details for confirmation.
     */
    public function getProductDetails(Product $product): array
    {
        $deleteCheck = $this->canDelete($product);

        return [
            'id' => $product->getId(),
            'name' => $product->getName(),
            'nameEs' => $product->getDisplayName('es'),
            'description' => $product->getDescription(),
            'price' => $product->getPrice(),
            'stock' => $product->getStock(),
            'category' => $product->getCategory(),
            'can_delete' => $deleteCheck['can_delete'],
            'deletion_reason' => $deleteCheck['reason'],
            'order_count' => $deleteCheck['order_count'],
        ];
    }
}
