<?php

namespace App\Application\UseCase\Admin;

use App\Domain\Entity\Product;
use App\Application\Service\AdminAssistantLogger;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Update product stock with validation and multiple update modes
 * Part of spec-008 US2 - Inventory Management
 * 
 * Supports three update modes:
 * - 'set': Set absolute stock value
 * - 'add': Increment stock by specified amount
 * - 'subtract': Decrement stock by specified amount
 */
class UpdateProductStock
{
    private const MAX_STOCK = 999999;
    private const MIN_STOCK = 0;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AdminAssistantLogger $logger
    ) {
    }

    /**
     * @param string $productId Product UUID
     * @param int $quantity Quantity to set/add/subtract
     * @param string $mode Update mode: 'set', 'add', or 'subtract'
     * @param string|null $reason Optional reason for the stock change
     * @return array{success: bool, product: array, old_stock: int, new_stock: int, message: string}
     */
    public function execute(
        string $productId,
        int $quantity,
        string $mode = 'set',
        ?string $reason = null
    ): array {
        // Validate mode
        if (!in_array($mode, ['set', 'add', 'subtract'], true)) {
            throw new \InvalidArgumentException(
                "Invalid mode '$mode'. Must be 'set', 'add', or 'subtract'"
            );
        }

        // Validate quantity
        if ($quantity < 0) {
            throw new \InvalidArgumentException('Quantity cannot be negative');
        }

        // Find product
        $product = $this->entityManager->find(Product::class, $productId);
        if (!$product) {
            throw new \InvalidArgumentException("Product not found: $productId");
        }

        $oldStock = $product->getStock();
        $newStock = $this->calculateNewStock($oldStock, $quantity, $mode);

        // Validate stock limits
        if ($newStock < self::MIN_STOCK) {
            throw new \InvalidArgumentException(
                "Stock cannot be negative (would result in: $newStock)"
            );
        }

        if ($newStock > self::MAX_STOCK) {
            throw new \InvalidArgumentException(
                "Stock exceeds maximum limit of " . self::MAX_STOCK
            );
        }

        // Update stock
        $product->setStock($newStock);
        $this->entityManager->flush();

        // Log the action
        $this->logger->logStockUpdate(
            productId: $productId,
            productName: $product->getName(),
            oldStock: $oldStock,
            newStock: $newStock,
            mode: $mode,
            quantity: $quantity,
            reason: $reason
        );

        return [
            'success' => true,
            'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'nameEs' => $product->getNameEs(),
                'category' => $product->getCategory(),
            ],
            'old_stock' => $oldStock,
            'new_stock' => $newStock,
            'message' => $this->buildSuccessMessage($product->getName(), $oldStock, $newStock, $mode),
        ];
    }

    private function calculateNewStock(int $currentStock, int $quantity, string $mode): int
    {
        return match ($mode) {
            'set' => $quantity,
            'add' => $currentStock + $quantity,
            'subtract' => $currentStock - $quantity,
        };
    }

    private function buildSuccessMessage(string $productName, int $oldStock, int $newStock, string $mode): string
    {
        $delta = $newStock - $oldStock;
        $change = $delta > 0 ? "+$delta" : "$delta";
        
        return match ($mode) {
            'set' => "Stock de '$productName' actualizado de $oldStock a $newStock unidades",
            'add' => "Stock de '$productName' incrementado en $delta unidades ($oldStock → $newStock)",
            'subtract' => "Stock de '$productName' reducido en " . abs($delta) . " unidades ($oldStock → $newStock)",
        };
    }
}
