<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Service\ProductEmbeddingSyncService;
use App\Domain\Entity\Product;
use Psr\Log\LoggerInterface;

/**
 * SyncProductEmbedding - Use case for syncing product to embedding store.
 *
 * Implements spec-010 US2: Automatic embedding generation on product changes
 */
class SyncProductEmbedding
{
    public function __construct(
        private readonly ProductEmbeddingSyncService $syncService,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Execute sync for product creation.
     */
    public function onCreate(Product $product): void
    {
        $this->logger->info('Executing sync for product creation', [
            'product_id' => $product->getId(),
        ]);

        $this->syncService->createEmbedding($product);
    }

    /**
     * Execute sync for product update.
     */
    public function onUpdate(Product $product): void
    {
        $this->logger->info('Executing sync for product update', [
            'product_id' => $product->getId(),
        ]);

        $this->syncService->updateEmbedding($product);
    }

    /**
     * Execute sync for product deletion.
     */
    public function onDelete(Product $product): void
    {
        $this->logger->info('Executing sync for product deletion', [
            'product_id' => $product->getId(),
        ]);

        $this->syncService->deleteEmbedding($product);
    }
}
