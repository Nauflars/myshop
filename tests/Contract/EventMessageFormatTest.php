<?php

declare(strict_types=1);

namespace App\Tests\Contract;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use PHPUnit\Framework\TestCase;

/**
 * EventMessageFormatTest - Contract test for message schema compliance.
 *
 * Spec-014 Phase 8 T074: Validates message format for queue interoperability
 * Ensures messages can be serialized/deserialized consistently
 */
class EventMessageFormatTest extends TestCase
{
    private const TEST_USER_ID_1 = '550e8400-e29b-41d4-a716-446655440000';
    private const TEST_USER_ID_2 = '660e8400-e29b-41d4-a716-446655440001';

    /**
     * @test
     */
    public function messageHasRequiredFieldsForSearchEvent(): void
    {
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: self::TEST_USER_ID_1,
            eventType: EventType::SEARCH,
            searchPhrase: 'wireless headphones',
            productId: null,
            occurredAt: new \DateTimeImmutable('2026-02-10 12:00:00')
        );

        $this->assertSame(self::TEST_USER_ID_1, $message->userId);
        $this->assertSame(EventType::SEARCH, $message->eventType);
        $this->assertSame('wireless headphones', $message->searchPhrase);
        $this->assertNull($message->productId);
        $this->assertNotEmpty($message->messageId);
        $this->assertSame(64, strlen($message->messageId)); // SHA-256
        $this->assertInstanceOf(\DateTimeImmutable::class, $message->occurredAt);
    }

    /**
     * @test
     */
    public function messageHasRequiredFieldsForProductEvent(): void
    {
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: self::TEST_USER_ID_2,
            eventType: EventType::PRODUCT_PURCHASE,
            searchPhrase: null,
            productId: 789,
            occurredAt: new \DateTimeImmutable('2026-02-10 12:00:00')
        );

        $this->assertSame(self::TEST_USER_ID_2, $message->userId);
        $this->assertSame(EventType::PRODUCT_PURCHASE, $message->eventType);
        $this->assertNull($message->searchPhrase);
        $this->assertSame(789, $message->productId);
        $this->assertNotEmpty($message->messageId);
        $this->assertSame(64, strlen($message->messageId)); // SHA-256
    }

    /**
     * @test
     */
    public function messageIdIsDeterministicForSameInputs(): void
    {
        $timestamp = new \DateTimeImmutable('2026-02-10 12:00:00');

        $message1 = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: self::TEST_USER_ID_1,
            eventType: EventType::SEARCH,
            searchPhrase: 'test',
            productId: null,
            occurredAt: $timestamp
        );

        $message2 = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: self::TEST_USER_ID_1,
            eventType: EventType::SEARCH,
            searchPhrase: 'test',
            productId: null,
            occurredAt: $timestamp
        );

        // Same inputs = same messageId (for idempotency)
        $this->assertSame($message1->messageId, $message2->messageId);

        // Different timestamp = different messageId
        $message3 = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: self::TEST_USER_ID_1,
            eventType: EventType::SEARCH,
            searchPhrase: 'test',
            productId: null,
            occurredAt: new \DateTimeImmutable('2026-02-10 12:00:01')
        );
        $this->assertNotSame($message1->messageId, $message3->messageId);

        // Validate SHA-256 format (64 hex characters)
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $message1->messageId);
    }

    /**
     * @test
     */
    public function searchEventRequiresSearchPhrase(): void
    {
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: self::TEST_USER_ID_1,
            eventType: EventType::SEARCH,
            searchPhrase: 'laptop',
            productId: null,
            occurredAt: new \DateTimeImmutable()
        );

        $this->assertNotNull($message->searchPhrase);
        $this->assertSame('laptop', $message->searchPhrase);

        // Test validation: search events without search_phrase should throw
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search events require search_phrase');

        new UpdateUserEmbeddingMessage(
            userId: self::TEST_USER_ID_1,
            eventType: EventType::SEARCH,
            searchPhrase: null, // Invalid
            productId: null,
            occurredAt: new \DateTimeImmutable(),
            metadata: [],
            messageId: str_repeat('a', 64)
        );
    }

    /**
     * @test
     */
    public function productEventRequiresProductId(): void
    {
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: self::TEST_USER_ID_1,
            eventType: EventType::PRODUCT_VIEW,
            searchPhrase: null,
            productId: 999,
            occurredAt: new \DateTimeImmutable()
        );

        $this->assertNotNull($message->productId);
        $this->assertSame(999, $message->productId);

        // Test validation: product events without product_id should throw
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('product_view events require product_id');

        new UpdateUserEmbeddingMessage(
            userId: self::TEST_USER_ID_1,
            eventType: EventType::PRODUCT_VIEW,
            searchPhrase: null,
            productId: null, // Invalid
            occurredAt: new \DateTimeImmutable(),
            metadata: [],
            messageId: str_repeat('a', 64)
        );
    }

    /**
     * @test
     */
    public function eventTypeHasCorrectWeight(): void
    {
        $this->assertSame(1.0, EventType::PRODUCT_PURCHASE->weight());
        $this->assertSame(0.7, EventType::SEARCH->weight());
        $this->assertSame(0.5, EventType::PRODUCT_CLICK->weight());
        $this->assertSame(0.3, EventType::PRODUCT_VIEW->weight());
    }

    /**
     * @test
     */
    public function occurredAtTimestampIsImmutable(): void
    {
        $timestamp = new \DateTimeImmutable('2026-02-10 12:00:00');
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: self::TEST_USER_ID_1,
            eventType: EventType::SEARCH,
            searchPhrase: 'test',
            productId: null,
            occurredAt: $timestamp
        );

        $retrievedTimestamp = $message->occurredAt;
        $this->assertInstanceOf(\DateTimeImmutable::class, $retrievedTimestamp);
        $this->assertEquals($timestamp->getTimestamp(), $retrievedTimestamp->getTimestamp());

        // Verify immutability - modifying timestamp doesn't affect message
        $newTimestamp = $timestamp->modify('+1 hour');
        $this->assertNotSame($retrievedTimestamp->getTimestamp(), $newTimestamp->getTimestamp());
    }

    /**
     * @test
     */
    public function messageSupportsMetadata(): void
    {
        $metadata = [
            'source' => 'web',
            'session_id' => 'abc123',
            'ip_address' => '192.168.1.1',
        ];

        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: self::TEST_USER_ID_1,
            eventType: EventType::SEARCH,
            searchPhrase: 'test',
            productId: null,
            occurredAt: new \DateTimeImmutable(),
            metadata: $metadata
        );

        $this->assertSame($metadata, $message->metadata);
        $this->assertArrayHasKey('source', $message->metadata);
        $this->assertSame('web', $message->metadata['source']);
    }

    /**
     * @test
     */
    public function allEventTypesAreValid(): void
    {
        $validTypes = [
            EventType::SEARCH,
            EventType::PRODUCT_VIEW,
            EventType::PRODUCT_CLICK,
            EventType::PRODUCT_PURCHASE,
        ];

        foreach ($validTypes as $eventType) {
            $message = UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: self::TEST_USER_ID_1,
                eventType: $eventType,
                searchPhrase: EventType::SEARCH === $eventType ? 'test' : null,
                productId: EventType::SEARCH !== $eventType ? 1 : null,
                occurredAt: new \DateTimeImmutable()
            );

            $this->assertSame($eventType, $message->eventType);
            $this->assertGreaterThan(0, $eventType->weight());
        }
    }

    /**
     * @test
     */
    public function userIdMustBeValidUuid(): void
    {
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: self::TEST_USER_ID_1,
            eventType: EventType::SEARCH,
            searchPhrase: 'test',
            productId: null,
            occurredAt: new \DateTimeImmutable()
        );

        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $message->userId);

        // Test validation: zero or negative user ID should throw
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID cannot be empty');

        new UpdateUserEmbeddingMessage(
            userId: '', // Invalid
            eventType: EventType::SEARCH,
            searchPhrase: 'test',
            productId: null,
            occurredAt: new \DateTimeImmutable(),
            metadata: [],
            messageId: str_repeat('a', 64)
        );
    }
}
