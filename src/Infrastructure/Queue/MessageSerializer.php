<?php

declare(strict_types=1);

namespace App\Infrastructure\Queue;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use DateTimeImmutable;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * MessageSerializer - Serialize/deserialize messages for RabbitMQ AMQP transport
 * 
 * Implements spec-014 contracts: JSON serialization for queue messages
 * Handles conversion between message objects and JSON for RabbitMQ
 */
final readonly class MessageSerializer
{
    public function __construct(
        private SerializerInterface $serializer
    ) {}

    /**
     * Serialize message to JSON for RabbitMQ
     * 
     * @param UpdateUserEmbeddingMessage $message Message to serialize
     * @return string JSON string
     */
    public function serialize(UpdateUserEmbeddingMessage $message): string
    {
        return $this->serializer->serialize($message->toArray(), 'json');
    }

    /**
     * Deserialize JSON from RabbitMQ to message object
     * 
     * @param string $json JSON string from queue
     * @return UpdateUserEmbeddingMessage Deserialized message
     * @throws \InvalidArgumentException If JSON is invalid
     */
    public function deserialize(string $json): UpdateUserEmbeddingMessage
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid message format: expected JSON object');
        }

        $this->validateMessageData($data);

        return new UpdateUserEmbeddingMessage(
            userId: (int) $data['user_id'],
            eventType: EventType::from($data['event_type']),
            searchPhrase: $data['search_phrase'] ?? null,
            productId: isset($data['product_id']) ? (int) $data['product_id'] : null,
            occurredAt: new DateTimeImmutable($data['occurred_at']),
            metadata: $data['metadata'] ?? [],
            messageId: $data['message_id']
        );
    }

    /**
     * Validate message data structure
     * 
     * @param array<string, mixed> $data Message data
     * @throws \InvalidArgumentException If required fields missing
     */
    private function validateMessageData(array $data): void
    {
        $requiredFields = ['user_id', 'event_type', 'occurred_at', 'message_id'];

        foreach ($requiredFields as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException(
                    sprintf('Missing required field: %s', $field)
                );
            }
        }

        // Validate event_type is valid enum value
        if (!in_array($data['event_type'], ['search', 'product_view', 'product_click', 'product_purchase'], true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid event_type: %s', $data['event_type'])
            );
        }

        // Validate occurred_at is valid ISO 8601 datetime
        try {
            new DateTimeImmutable($data['occurred_at']);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                sprintf('Invalid occurred_at format: %s', $e->getMessage())
            );
        }

        // Validate message_id is 64-character hash
        if (!isset($data['message_id']) || strlen($data['message_id']) !== 64) {
            throw new \InvalidArgumentException('message_id must be 64-character SHA-256 hash');
        }
    }

    /**
     * Serialize message with AMQP headers
     * 
     * @param UpdateUserEmbeddingMessage $message Message to serialize
     * @return array{body: string, headers: array<string, mixed>}
     */
    public function serializeWithHeaders(UpdateUserEmbeddingMessage $message): array
    {
        return [
            'body' => $this->serialize($message),
            'headers' => [
                'content_type' => 'application/json',
                'content_encoding' => 'utf-8',
                'delivery_mode' => 2, // Persistent
                'priority' => $this->calculatePriority($message),
                'timestamp' => $message->occurredAt->getTimestamp(),
                'app_id' => 'myshop-api',
                'message_id' => $message->messageId,
            ],
        ];
    }

    /**
     * Calculate message priority based on event type
     * 
     * Purchase events get higher priority (7-9)
     * Search events get medium priority (5-6)
     * View/click events get normal priority (3-4)
     * 
     * @param UpdateUserEmbeddingMessage $message
     * @return int Priority 0-9
     */
    private function calculatePriority(UpdateUserEmbeddingMessage $message): int
    {
        return match($message->eventType) {
            EventType::PRODUCT_PURCHASE => 8,
            EventType::SEARCH => 5,
            EventType::PRODUCT_CLICK => 4,
            EventType::PRODUCT_VIEW => 3,
        };
    }
}
