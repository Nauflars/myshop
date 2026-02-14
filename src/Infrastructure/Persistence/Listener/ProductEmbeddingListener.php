<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Listener;

use App\Application\Service\FailedJobRegistry;
use App\Application\Service\FailureRateMonitor;
use App\Application\UseCase\SyncProductEmbedding;
use App\Domain\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * ProductEmbeddingListener - Sync product changes to MongoDB embeddings.
 *
 * Implements spec-010 FR-004: Auto-sync on product CRUD operations
 * Listens to Doctrine events and triggers embedding sync
 */
#[AsDoctrineListener(event: Events::postPersist, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postUpdate, priority: 500, connection: 'default')]
#[AsDoctrineListener(event: Events::postRemove, priority: 500, connection: 'default')]
class ProductEmbeddingListener
{
    public function __construct(
        private readonly SyncProductEmbedding $syncUseCase,
        private readonly LoggerInterface $logger,
        private readonly FailedJobRegistry $failedJobRegistry,
        private readonly FailureRateMonitor $failureRateMonitor,
    ) {
    }

    /**
     * Handle product creation.
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Product) {
            return;
        }

        try {
            $this->logger->debug('Product created, syncing embedding', [
                'product_id' => $entity->getId(),
            ]);

            $this->syncUseCase->onCreate($entity);

            // T096: Record successful sync for failure rate monitoring
            $this->failureRateMonitor->recordSuccess();
        } catch (\Exception $e) {
            // T095: Record failure in dead letter queue for later retry
            $this->failedJobRegistry->recordFailure($entity, 'create', $e);

            // T096: Record failure for high failure rate alerting
            $this->failureRateMonitor->recordFailure($entity->getId(), 'create', $e);

            // Log error but don't throw - allow transaction to complete
            $this->logger->error('Failed to sync embedding on product creation', [
                'product_id' => $entity->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle product update.
     */
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Product) {
            return;
        }

        try {
            $this->logger->debug('Product updated, syncing embedding', [
                'product_id' => $entity->getId(),
            ]);

            $this->syncUseCase->onUpdate($entity);

            // T096: Record successful sync for failure rate monitoring
            $this->failureRateMonitor->recordSuccess();
        } catch (\Exception $e) {
            // T095: Record failure in dead letter queue for later retry
            $this->failedJobRegistry->recordFailure($entity, 'update', $e);

            // T096: Record failure for high failure rate alerting
            $this->failureRateMonitor->recordFailure($entity->getId(), 'update', $e);

            $this->logger->error('Failed to sync embedding on product update', [
                'product_id' => $entity->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle product deletion.
     */
    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if (!$entity instanceof Product) {
            return;
        }

        try {
            $this->logger->debug('Product deleted, removing embedding', [
                'product_id' => $entity->getId(),
            ]);

            $this->syncUseCase->onDelete($entity);

            // T096: Record successful sync for failure rate monitoring
            $this->failureRateMonitor->recordSuccess();
        } catch (\Exception $e) {
            // T095: Record failure in dead letter queue for later retry
            $this->failedJobRegistry->recordFailure($entity, 'delete', $e);

            // T096: Record failure for high failure rate alerting
            $this->failureRateMonitor->recordFailure($entity->getId(), 'delete', $e);

            $this->logger->error('Failed to remove embedding on product deletion', [
                'product_id' => $entity->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
