<?php

declare(strict_types=1);

namespace App\Application\UseCase;

use App\Domain\Repository\UserEmbeddingRepositoryInterface;
use App\Domain\ValueObject\EmbeddingWeights;
use App\Domain\ValueObject\EventType;
use App\Domain\ValueObject\UserEmbedding;
use Psr\Log\LoggerInterface;

/**
 * CalculateUserEmbedding - Use case for calculating user embedding from event.
 *
 * Implements spec-014 algorithm: Incremental embedding update with temporal decay
 * Formula: new_embedding = (current * decay_factor + event * weight) / (decay_factor + weight)
 */
class CalculateUserEmbedding
{
    public function __construct(
        private readonly UserEmbeddingRepositoryInterface $embeddingRepository,
        private readonly EmbeddingWeights $weights,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Calculate and save user embedding from event.
     *
     * @param string             $userId         User UUID identifier
     * @param EventType          $eventType      Type of interaction
     * @param array<float>       $eventEmbedding 1536-dimensional event embedding from AI
     * @param \DateTimeImmutable $occurredAt     When the event occurred
     *
     * @return UserEmbedding Updated user embedding
     *
     * @throws \InvalidArgumentException If embedding dimensions invalid
     */
    public function execute(
        string $userId,
        EventType $eventType,
        array $eventEmbedding,
        \DateTimeImmutable $occurredAt,
    ): UserEmbedding {
        try {
            // Validate event embedding dimensions
            if (1536 !== count($eventEmbedding)) {
                throw new \InvalidArgumentException(sprintf('Event embedding must have 1536 dimensions, got %d', count($eventEmbedding)));
            }

            // Retrieve existing embedding (if exists)
            $existingEmbedding = $this->embeddingRepository->findByUserId($userId);

            if (null === $existingEmbedding) {
                // First event: create initial embedding
                $newEmbedding = UserEmbedding::fromEventEmbedding(
                    userId: $userId,
                    eventEmbedding: $eventEmbedding,
                    eventType: $eventType,
                    occurredAt: $occurredAt
                );

                $this->logger->info('Created initial user embedding', [
                    'user_id' => $userId,
                    'event_type' => $eventType->value,
                    'dimensions' => count($eventEmbedding),
                    'occurred_at' => $occurredAt->format('c'),
                ]);
            } else {
                // Update existing embedding with temporal decay
                $newEmbedding = $existingEmbedding->updateWith(
                    eventEmbedding: $eventEmbedding,
                    eventType: $eventType,
                    occurredAt: $occurredAt,
                    weights: $this->weights
                );

                $this->logger->info('Updated user embedding with temporal decay', [
                    'user_id' => $userId,
                    'event_type' => $eventType->value,
                    'event_weight' => $eventType->weight(),
                    'occurred_at' => $occurredAt->format('c'),
                    'days_since_last_update' => $occurredAt->diff($existingEmbedding->lastUpdatedAt)->days,
                ]);
            }

            // Save to MongoDB with optimistic locking
            $saved = $this->embeddingRepository->save($newEmbedding);

            if (!$saved) {
                throw new \RuntimeException('Failed to save user embedding (possible concurrent update)');
            }

            return $newEmbedding;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to calculate user embedding', [
                'user_id' => $userId,
                'event_type' => $eventType->value,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
                'previous' => $e->getPrevious() ? $e->getPrevious()->getMessage() : null,
            ]);

            throw $e;
        }
    }

    /**
     * Batch calculate embeddings for multiple events.
     *
     * @param array<array{user_id: int, event_type: EventType, embedding: array<float>, occurred_at: \DateTimeImmutable}> $events
     *
     * @return array{success: int, failed: int, embeddings: array<UserEmbedding>}
     */
    public function executeBatch(array $events): array
    {
        $success = 0;
        $failed = 0;
        $embeddings = [];

        foreach ($events as $event) {
            try {
                $embedding = $this->execute(
                    userId: $event['user_id'],
                    eventType: $event['event_type'],
                    eventEmbedding: $event['embedding'],
                    occurredAt: $event['occurred_at']
                );

                $embeddings[] = $embedding;
                ++$success;
            } catch (\Throwable $e) {
                $this->logger->error('Batch processing failed for event', [
                    'user_id' => $event['user_id'],
                    'error' => $e->getMessage(),
                ]);
                ++$failed;
            }
        }

        $this->logger->info('Batch calculation completed', [
            'total' => count($events),
            'success' => $success,
            'failed' => $failed,
        ]);

        return [
            'success' => $success,
            'failed' => $failed,
            'embeddings' => $embeddings,
        ];
    }

    /**
     * Calculate embedding similarity to query.
     *
     * @param int          $userId         User identifier
     * @param array<float> $queryEmbedding Query embedding vector
     *
     * @return float Cosine similarity (-1 to 1), or 0.0 if no embedding exists
     */
    public function calculateSimilarity(int $userId, array $queryEmbedding): float
    {
        if (1536 !== count($queryEmbedding)) {
            throw new \InvalidArgumentException(sprintf('Query embedding must have 1536 dimensions, got %d', count($queryEmbedding)));
        }

        $userEmbedding = $this->embeddingRepository->findByUserId($userId);

        if (null === $userEmbedding) {
            return 0.0; // No embedding exists
        }

        return $userEmbedding->cosineSimilarity($queryEmbedding);
    }
}
