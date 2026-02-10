<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * UserEmbedding - Domain value object encapsulating user embedding vector with operations
 * 
 * Implements spec-014 data model: Immutable user embedding with update logic
 * Vector is always L2-normalized for cosine similarity calculations
 */
final readonly class UserEmbedding
{
    private const DIMENSIONS = 1536;
    private const EPSILON = 1e-10; // Prevent division by zero

    /**
     * @param int $userId User identifier
     * @param array<int, float> $vector 1536-dimensional normalized vector
     * @param DateTimeImmutable $lastUpdatedAt Timestamp of most recent update
     * @param int $version Optimistic locking version
     */
    public function __construct(
        public int $userId,
        public array $vector,
        public DateTimeImmutable $lastUpdatedAt,
        public int $version = 1
    ) {
        if ($userId <= 0) {
            throw new InvalidArgumentException('User ID must be positive');
        }

        if (count($vector) !== self::DIMENSIONS) {
            throw new InvalidArgumentException(
                sprintf('Embedding must be %d dimensions, got %d', self::DIMENSIONS, count($vector))
            );
        }

        if ($version < 1) {
            throw new InvalidArgumentException('Version must be at least 1');
        }
    }

    /**
     * Create initial embedding from first event
     * 
     * @param int $userId User identifier
     * @param array<int, float> $eventEmbedding Raw 1536-dimensional vector
     * @param EventType $eventType Type of event
     * @param DateTimeImmutable $occurredAt Event occurrence time
     * @return self Normalized user embedding
     */
    public static function fromEventEmbedding(
        int $userId,
        array $eventEmbedding,
        EventType $eventType,
        DateTimeImmutable $occurredAt
    ): self {
        return new self(
            userId: $userId,
            vector: self::normalize($eventEmbedding),
            lastUpdatedAt: $occurredAt,
            version: 1
        );
    }

    /**
     * Update embedding with new event using weighted average with temporal decay
     * 
     * Formula: new_vector = (current_vector * decay_factor + event_vector * event_weight) / (decay_factor + event_weight)
     * Then normalize to unit length
     * 
     * @param array<int, float> $eventEmbedding New event embedding (1536-dim, pre-normalized)
     * @param EventType $eventType Type of event (determines weight)
     * @param DateTimeImmutable $occurredAt When event occurred
     * @param EmbeddingWeights $weights Configuration for decay and weighting
     * @return self New immutable embedding with updated vector
     */
    public function updateWith(
        array $eventEmbedding,
        EventType $eventType,
        DateTimeImmutable $occurredAt,
        EmbeddingWeights $weights
    ): self {
        if (count($eventEmbedding) !== self::DIMENSIONS) {
            throw new InvalidArgumentException(
                sprintf('Event embedding must be %d dimensions', self::DIMENSIONS)
            );
        }

        if ($occurredAt < $this->lastUpdatedAt) {
            throw new InvalidArgumentException('Event timestamp cannot be before last update');
        }

        // Get event weight from event type
        $eventWeight = $eventType->weight();

        // Calculate temporal decay factor
        $daysSinceLastUpdate = $occurredAt->diff($this->lastUpdatedAt)->days;
        $decayFactor = $weights->calculateDecayFactor($daysSinceLastUpdate);

        // Weighted average with decay
        $newVector = [];
        for ($i = 0; $i < self::DIMENSIONS; $i++) {
            $newVector[$i] = (
                $this->vector[$i] * $decayFactor +
                $eventEmbedding[$i] * $eventWeight
            ) / ($decayFactor + $eventWeight);
        }

        return new self(
            userId: $this->userId,
            vector: self::normalize($newVector),
            lastUpdatedAt: $occurredAt,
            version: $this->version + 1
        );
    }

    /**
     * L2-normalize vector to unit length
     * 
     * @param array<int, float> $vector Input vector
     * @return array<int, float> Normalized unit vector
     */
    private static function normalize(array $vector): array
    {
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v ** 2, $vector)));

        // Prevent division by zero for zero vectors
        if ($magnitude < self::EPSILON) {
            throw new InvalidArgumentException('Cannot normalize zero vector');
        }

        return array_map(fn($v) => $v / $magnitude, $vector);
    }

    /**
     * Calculate cosine similarity with query vector
     * 
     * @param array<int, float> $queryVector Query embedding vector
     * @return float Similarity score (-1.0 to 1.0)
     */
    public function cosineSimilarity(array $queryVector): float
    {
        if (count($queryVector) !== self::DIMENSIONS) {
            throw new InvalidArgumentException(
                sprintf('Query vector must be %d dimensions', self::DIMENSIONS)
            );
        }

        $dotProduct = 0.0;
        for ($i = 0; $i < self::DIMENSIONS; $i++) {
            $dotProduct += $this->vector[$i] * $queryVector[$i];
        }

        // Since both vectors are normalized, cosine similarity = dot product
        return $dotProduct;
    }

    /**
     * Get vector as array for MongoDB storage
     * 
     * @return array<int, float>
     */
    public function toArray(): array
    {
        return $this->vector;
    }

    /**
     * Get vector dimension count
     */
    public static function getDimensions(): int
    {
        return self::DIMENSIONS;
    }

    /**
     * Check if embedding is stale (old update)
     * 
     * @param int $maxDaysOld Maximum acceptable age in Atdays
     * @return bool True if embedding hasn't been updated recently
     */
    public function isStale(int $maxDaysOld = 90): bool
    {
        $now = new DateTimeImmutable();
        $daysSinceUpdate = $now->diff($this->lastUpdated)->days;

        return $daysSinceUpdate > $maxDaysOld;
    }
}
