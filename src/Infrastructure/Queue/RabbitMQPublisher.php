<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Application\Port\MessagePublisherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/**
 * RabbitMQPublisher - Publish messages to RabbitMQ queue.
 *
 * Implements spec-014 architecture: Message publisher for user embedding updates
 * Uses Symfony Messenger for RabbitMQ abstraction
 *
 * This is an ADAPTER in Hexagonal Architecture:
 * - Implements MessagePublisherInterface (PORT)
 * - Provides concrete RabbitMQ implementation
 */
final readonly class RabbitMQPublisher implements MessagePublisherInterface
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * Publish user embedding update message to queue.
     *
     * @param UpdateUserEmbeddingMessage $message Message to publish
     * @param int                        $delayMs Optional delay in milliseconds (default: 0)
     *
     * @return bool True if published successfully
     */
    public function publish(UpdateUserEmbeddingMessage $message, int $delayMs = 0): bool
    {
        try {
            $stamps = [
                // FORCE message to go to RabbitMQ transport (NEVER handle synchronously)
                new TransportNamesStamp(['user_embedding_updates']),
            ];

            // Add delay stamp if specified
            if ($delayMs > 0) {
                $stamps[] = new DelayStamp($delayMs);
            }

            // Dispatch message - will be sent to transport, NOT handled synchronously
            $this->messageBus->dispatch($message, $stamps);

            $this->logger->info('Published user embedding update message to queue', [
                'message_id' => $message->messageId,
                'user_id' => $message->userId,
                'event_type' => $message->eventType->value,
                'routing_key' => sprintf('user.embedding.%s', $message->eventType->value),
                'delay_ms' => $delayMs,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to publish message to queue', [
                'message_id' => $message->messageId,
                'user_id' => $message->userId,
                'event_type' => $message->eventType->value,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            return false;
        }
    }

    /**
     * Publish batch of messages.
     *
     * @param array<UpdateUserEmbeddingMessage> $messages Messages to publish
     *
     * @return array{success: int, failed: int} Counts of successful and failed publishes
     */
    public function publishBatch(array $messages): array
    {
        $success = 0;
        $failed = 0;

        foreach ($messages as $message) {
            if ($this->publish($message)) {
                ++$success;
            } else {
                ++$failed;
            }
        }

        $this->logger->info('Batch publish completed', [
            'total' => count($messages),
            'success' => $success,
            'failed' => $failed,
        ]);

        return ['success' => $success, 'failed' => $failed];
    }

    /**
     * Publish message with priority (for urgent events).
     *
     * @param UpdateUserEmbeddingMessage $message Message to publish
     *
     * @return bool True if published successfully
     */
    public function publishWithPriority(UpdateUserEmbeddingMessage $message): bool
    {
        // For high-priority events (purchase), process immediately
        return $this->publish($message, delayMs: 0);
    }

    /**
     * Schedule message for future publishing.
     *
     * @param UpdateUserEmbeddingMessage $message      Message to publish
     * @param int                        $delaySeconds Delay in seconds
     *
     * @return bool True if scheduled successfully
     */
    public function schedulePublish(UpdateUserEmbeddingMessage $message, int $delaySeconds): bool
    {
        if ($delaySeconds < 0) {
            throw new \InvalidArgumentException('Delay must be non-negative');
        }

        return $this->publish($message, delayMs: $delaySeconds * 1000);
    }

    /**
     * Publish order created event to queue.
     *
     * @param array<string, mixed> $orderData Order data to publish
     *
     * @return bool True if published successfully
     */
    public function publishOrderCreated(array $orderData): bool
    {
        try {
            // For now, we don't have a specific OrderCreated message
            // This method is here to satisfy the interface
            // In future, we can implement proper order event handling
            $this->logger->info('Order created event (not yet implemented)', [
                'order_id' => $orderData['id'] ?? null,
            ]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to publish order created event', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
