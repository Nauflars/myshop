<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\EventType;
use DateTimeImmutable;

/**
 * UserInteractionEvent - Domain event representing a user interaction
 * 
 * Implements spec-014 data model: User interaction domain event
 * Captures user behavior events that trigger embedding updates
 */
final readonly class UserInteractionEvent
{
    /**
     * @param int $userId User who performed the interaction
     * @param EventType $eventType Type of interaction
     * @param string|null $searchPhrase Search query (required for search events)
     * @param int|null $productId Product reference (required for product events)
     * @param DateTimeImmutable $occurredAt When the interaction occurred
     * @param array<string, mixed> $metadata Additional context (device, channel, etc.)
     */
    public function __construct(
        public int $userId,
        public EventType $eventType,
        public ?string $searchPhrase,
        public ?int $productId,
        public DateTimeImmutable $occurredAt,
        public array $metadata = []
    ) {
        $this->validate();
    }

    /**
     * Validate event data consistency
     * 
     * @throws \InvalidArgumentException
     */
    private function validate(): void
    {
        if ($this->userId <= 0) {
            throw new \InvalidArgumentException('User ID must be positive');
        }

        // Search events require search_phrase
        if ($this->eventType->requiresSearchPhrase() && empty($this->searchPhrase)) {
            throw new \InvalidArgumentException('Search events require search_phrase');
        }

        // Product events require product_id
        if ($this->eventType->requiresProduct() && $this->productId === null) {
            throw new \InvalidArgumentException(
                sprintf('%s events require product_id', $this->eventType->value)
            );
        }

        // Search events should not have product_id
        if ($this->eventType === EventType::SEARCH && $this->productId !== null) {
            throw new \InvalidArgumentException('Search events should not have product_id');
        }

        // Product events should not have search_phrase
        if ($this->eventType->requiresProduct() && $this->searchPhrase !== null) {
            throw new \InvalidArgumentException('Product events should not have search_phrase');
        }

        // Occurred_at cannot be in the future
        $now = new DateTimeImmutable();
        if ($this->occurredAt > $now) {
            throw new \InvalidArgumentException('Event occurrence time cannot be in the future');
        }
    }

    /**
     * Create search event
     */
    public static function createSearchEvent(
        int $userId,
        string $searchPhrase,
        DateTimeImmutable $occurredAt,
        array $metadata = []
    ): self {
        return new self(
            userId: $userId,
            eventType: EventType::SEARCH,
            searchPhrase: $searchPhrase,
            productId: null,
            occurredAt: $occurredAt,
            metadata: $metadata
        );
    }

    /**
     * Create product view event
     */
    public static function createProductViewEvent(
        int $userId,
        int $productId,
        DateTimeImmutable $occurredAt,
        array $metadata = []
    ): self {
        return new self(
            userId: $userId,
            eventType: EventType::PRODUCT_VIEW,
            searchPhrase: null,
            productId: $productId,
            occurredAt: $occurredAt,
            metadata: $metadata
        );
    }

    /**
     * Create product click event
     */
    public static function createProductClickEvent(
        int $userId,
        int $productId,
        DateTimeImmutable $occurredAt,
        array $metadata = []
    ): self {
        return new self(
            userId: $userId,
            eventType: EventType::PRODUCT_CLICK,
            searchPhrase: null,
            productId: $productId,
            occurredAt: $occurredAt,
            metadata: $metadata
        );
    }

    /**
     * Create product purchase event
     */
    public static function createProductPurchaseEvent(
        int $userId,
        int $productId,
        DateTimeImmutable $occurredAt,
        array $metadata = []
    ): self {
        return new self(
            userId: $userId,
            eventType: EventType::PRODUCT_PURCHASE,
            searchPhrase: null,
            productId: $productId,
            occurredAt: $occurredAt,
            metadata: $metadata
        );
    }

    /**
     * Get event weight from event type
     */
    public function getWeight(): float
    {
        return $this->eventType->weight();
    }

    /**
     * Generate unique message ID for idempotency
     * 
     * SHA-256 hash of: user_id + event_type + reference + occurred_at
     */
    public function generateMessageId(): string
    {
        $reference = $this->searchPhrase ?? (string) $this->productId;
        $data = sprintf(
            '%d|%s|%s|%s',
            $this->userId,
            $this->eventType->value,
            $reference,
            $this->occurredAt->format('c')
        );

        return hash('sha256', $data);
    }

    /**
     * Convert to array for serialization
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
        ];
    }
}
