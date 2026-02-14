<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Entity\UserInteraction;
use App\Infrastructure\Queue\RabbitMQPublisher;
use App\Repository\UserInteractionRepository;
use Psr\Log\LoggerInterface;

/**
 * PublishUserInteractionEvent - Use case for saving and publishing user interaction events.
 *
 * Implements spec-014 US1: Save event to MySQL and publish to RabbitMQ queue
 * Acts as orchestrator between persistence and message queue
 */
final readonly class PublishUserInteractionEvent
{
    public function __construct(
        private RabbitMQPublisher $publisher,
        private UserInteractionRepository $userInteractionRepository,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Execute use case: save interaction and publish to queue.
     *
     * @param UserInteraction $interaction User interaction entity
     *
     * @return bool True if both save and publish succeeded
     */
    public function execute(UserInteraction $interaction): bool
    {
        try {
            // 1. Save to MySQL (source of truth)
            $this->userInteractionRepository->save($interaction, true); // flush immediately

            $this->logger->info('Saved user interaction to database', [
                'id' => $interaction->getId(),
                'user_id' => $interaction->getUserId(),
                'event_type' => $interaction->getEventType()->value,
            ]);

            // 2. Create message for queue
            $message = UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: $interaction->getUserId(),
                eventType: $interaction->getEventType(),
                searchPhrase: $interaction->getSearchPhrase(),
                productId: $interaction->getProductId() ? (int) $interaction->getProductId() : null,
                occurredAt: $interaction->getOccurredAt(),
                metadata: $interaction->getMetadata() ?? []
            );

            // 3. Publish to RabbitMQ
            $published = $this->publisher->publish($message);

            if ($published) {
                // Mark as processed in database
                $interaction->markAsProcessedToQueue();
                $this->userInteractionRepository->save($interaction, true);

                $this->logger->info('Published user interaction event to queue', [
                    'message_id' => $message->messageId,
                    'user_id' => $interaction->getUserId(),
                    'event_type' => $interaction->getEventType()->value,
                ]);

                return true;
            }

            $this->logger->warning('Failed to publish event to queue (saved in DB for replay)', [
                'id' => $interaction->getId(),
                'user_id' => $interaction->getUserId(),
            ]);

            return false;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to publish user interaction event', [
                'user_id' => $interaction->getUserId(),
                'event_type' => $interaction->getEventType()->value,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            throw $e;
        }
    }

    /**
     * Replay unprocessed events from database.
     *
     * @param int $limit Maximum number of events to replay
     *
     * @return array{success: int, failed: int}
     */
    public function replayUnprocessedEvents(int $limit = 100): array
    {
        $unprocessed = $this->userInteractionRepository->findUnprocessedEvents($limit);

        $success = 0;
        $failed = 0;

        foreach ($unprocessed as $interaction) {
            try {
                $message = UpdateUserEmbeddingMessage::fromDomainEvent(
                    userId: $interaction->getUserId(),
                    eventType: $interaction->getEventType(),
                    searchPhrase: $interaction->getSearchPhrase(),
                    productId: $interaction->getProductId() ? (int) $interaction->getProductId() : null,
                    occurredAt: $interaction->getOccurredAt(),
                    metadata: $interaction->getMetadata() ?? []
                );

                if ($this->publisher->publish($message)) {
                    $interaction->markAsProcessedToQueue();
                    $this->userInteractionRepository->save($interaction, true);
                    ++$success;
                } else {
                    ++$failed;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Failed to replay event', [
                    'id' => $interaction->getId(),
                    'error' => $e->getMessage(),
                ]);
                ++$failed;
            }
        }

        $this->logger->info('Replay completed', [
            'total' => count($unprocessed),
            'success' => $success,
            'failed' => $failed,
        ]);

        return ['success' => $success, 'failed' => $failed];
    }
}
