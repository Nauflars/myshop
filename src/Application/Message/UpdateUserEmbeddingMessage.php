<?php

declare(strict_types=1);

namespace App\Application\Message;

use App\Domain\ValueObject\EventType;

/**
 * UpdateUserEmbeddingMessage - Async message for RabbitMQ queue.
 *
 * Implements spec-014 data model: Queue message for user embedding updates
 * Published when user interactions occur, consumed by workers to update embeddings
 */
final readonly class UpdateUserEmbeddingMessage
{
    /**
     * @param string               $userId       User UUID identifier
     * @param EventType            $eventType    Type of interaction (search, view, click, purchase)
     * @param string|null          $searchPhrase Search query text (for search events)
     * @param int|null             $productId    Product reference (for product events)
     * @param \DateTimeImmutable   $occurredAt   When the interaction occurred
     * @param array<string, mixed> $metadata     Additional context
     * @param string               $messageId    SHA-256 hash for idempotency
     */
    public function __construct(
        public string $userId,
        public EventType $eventType,
        public ?string $searchPhrase,
        public ?int $productId,
        public \DateTimeImmutable $occurredAt,
        public array $metadata,
        public string $messageId,
    ) {
        $this->validate();
    }

    /**
     * Validate message data consistency.
     */
    private function validate(): void
    {
        if (empty($this->userId)) {
            throw new \InvalidArgumentException('User ID cannot be empty');
        }

        // Search events require search_phrase
        if ($this->eventType->requiresSearchPhrase() && empty($this->searchPhrase)) {
            throw new \InvalidArgumentException('Search events require search_phrase');
        }

        // Product events require product_id
        if ($this->eventType->requiresProduct() && null === $this->productId) {
            throw new \InvalidArgumentException(sprintf('%s events require product_id', $this->eventType->value));
        }

        // Validate message_id format
        if (64 !== strlen($this->messageId)) {
            throw new \InvalidArgumentException('Message ID must be 64-character SHA-256 hash');
        }
    }

    /**
     * Create from domain event.
     */
    public static function fromDomainEvent(
        string $userId,
        EventType $eventType,
        ?string $searchPhrase,
        ?int $productId,
        \DateTimeImmutable $occurredAt,
        array $metadata = [],
    ): self {
        $messageId = self::generateMessageId($userId, $eventType, $searchPhrase, $productId, $occurredAt);

        return new self(
            userId: $userId,
            eventType: $eventType,
            searchPhrase: $searchPhrase,
            productId: $productId,
            occurredAt: $occurredAt,
            metadata: $metadata,
            messageId: $messageId
        );
    }

    /**
     * Generate unique message ID for idempotency.
     *
     * SHA-256 hash of: user_id + event_type + reference + occurred_at
     */
    public static function generateMessageId(
        string $userId,
        EventType $eventType,
        ?string $searchPhrase,
        ?int $productId,
        \DateTimeImmutable $occurredAt,
    ): string {
        $reference = $searchPhrase ?? (string) $productId;
        $data = sprintf(
            '%d|%s|%s|%s',
            $userId,
            $eventType->value,
            $reference,
            $occurredAt->format('c')
        );

        return hash('sha256', $data);
    }

    /**
     * Get event weight from event type.
     */
    public function getWeight(): float
    {
        return $this->eventType->weight();
    }

    /**
     * Convert to array for serialization.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'user_id' => $this->userId,
            'event_type' => $this->eventType->value,
            'search_phrase' => $this->searchPhrase,
            'product_id' => $this->productId,
            'occurred_at' => $this->occurredAt->format('c'),
            'metadata' => $this->metadata,
            'message_id' => $this->messageId,
            'version' => '1.0',
        ];
    }
}
