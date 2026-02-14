<?php

declare(strict_types=1);

namespace App\Application\MessageHandler;

use App\Application\Message\SyncEmbeddingMessage;
use App\Application\Service\ProductEmbeddingSyncService;
use App\Domain\Repository\ProductRepositoryInterface;
use Psr\Log\LoggerInterface;

/**
 * SyncEmbeddingHandler - Handle async embedding sync messages.
 *
 * Implements spec-010 FR-005: Async processing of embedding sync
 * Note: Requires symfony/messenger component for async processing
 */
class SyncEmbeddingHandler
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductEmbeddingSyncService $syncService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SyncEmbeddingMessage $message): void
    {
        $this->logger->info('Processing embedding sync message', [
            'product_id' => $message->getProductId(),
            'operation' => $message->getOperation(),
        ]);

        $product = $this->productRepository->find($message->getProductId());

        if (null === $product) {
            // Product deleted or not found
            if ('delete' === $message->getOperation()) {
                $this->logger->info('Product already deleted, skipping', [
                    'product_id' => $message->getProductId(),
                ]);

                return;
            }

            $this->logger->warning('Product not found for embedding sync', [
                'product_id' => $message->getProductId(),
            ]);

            return;
        }

        try {
            $success = $this->syncService->syncWithRetry(
                $product,
                $message->getOperation()
            );

            if ($success) {
                $this->logger->info('Embedding sync completed successfully', [
                    'product_id' => $message->getProductId(),
                    'operation' => $message->getOperation(),
                ]);
            } else {
                $this->logger->error('Embedding sync failed', [
                    'product_id' => $message->getProductId(),
                    'operation' => $message->getOperation(),
                ]);
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception during embedding sync', [
                'product_id' => $message->getProductId(),
                'operation' => $message->getOperation(),
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw to trigger message retry
        }
    }
}
