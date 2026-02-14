<?php

declare(strict_types=1);

namespace App\Infrastructure\EventListener;

use App\Application\UseCase\PublishUserInteractionEvent;
use App\Entity\UserInteraction;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Events;
use Psr\Log\LoggerInterface;

/**
 * UserInteractionEventListener - Doctrine listener for UserInteraction entity.
 *
 * Implements spec-014 US1: Automatically publish events to RabbitMQ after persisting
 * Triggers on PostPersist event to ensure MySQL commit succeeded first
 */
#[AsDoctrineListener(event: Events::postPersist, priority: 500)]
final readonly class UserInteractionEventListener
{
    public function __construct(
        private PublishUserInteractionEvent $publishUseCase,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Handle PostPersist event for UserInteraction entities.
     *
     * @param PostPersistEventArgs $args Event arguments
     */
    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();

        // Only process UserInteraction entities
        if (!$entity instanceof UserInteraction) {
            return;
        }

        try {
            // Publish event to RabbitMQ queue
            $this->publishUseCase->execute($entity);
        } catch (\Throwable $e) {
            // Log error but don't fail the HTTP request
            // Event will be replayed later via manual command
            $this->logger->error('Failed to publish user interaction in PostPersist listener', [
                'id' => $entity->getId(),
                'user_id' => $entity->getUserId(),
                'event_type' => $entity->getEventType()->value,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);
        }
    }
}
