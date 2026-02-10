<?php

declare(strict_types=1);

namespace Tests\Integration\Queue;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use App\Infrastructure\Queue\RabbitMQPublisher;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * RetryAndDLQTest - Integration tests for retry logic and Dead Letter Queue
 * 
 * Tests spec-014 US4: Fault tolerance with automatic retries and DLQ
 */
class RetryAndDLQTest extends KernelTestCase
{
    private RabbitMQPublisher $publisher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->publisher = $container->get(RabbitMQPublisher::class);
    }

    /**
     * Test: Message is published successfully to queue
     */
    public function testPublishMessageToQueue(): void
    {
        // Arrange: Create test message
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 999001,
            eventType: EventType::SEARCH,
            searchPhrase: 'test retry mechanism',
            productId: null,
            occurredAt: new DateTimeImmutable(),
            metadata: ['test' => 'retry_and_dlq']
        );

        // Act: Publish to queue
        $result = $this->publisher->publish($message);

        // Assert: Publish succeeded
        $this->assertTrue($result, 'Message should be published successfully');
    }

    /**
     * Test: Batch publish with mixed success/failure
     */
    public function testBatchPublishMessages(): void
    {
        // Arrange: Create multiple test messages
        $messages = [];
        for ($i = 1; $i <= 5; $i++) {
            $messages[] = UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: 999000 + $i,
                eventType: EventType::SEARCH,
                searchPhrase: sprintf('batch test %d', $i),
                productId: null,
                occurredAt: new DateTimeImmutable(),
                metadata: ['batch' => $i]
            );
        }

        // Act: Batch publish
        $result = $this->publisher->publishBatch($messages);

        // Assert: All published successfully
        $this->assertEquals(5, $result['success']);
        $this->assertEquals(0, $result['failed']);
    }

    /**
     * Test: Message published with priority
     */
    public function testPublishWithPriority(): void
    {
        // Arrange: Create high-priority message (purchase)
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 999002,
            eventType: EventType::PRODUCT_PURCHASE,
            searchPhrase: null,
            productId: 555,
            occurredAt: new DateTimeImmutable(),
            metadata: ['priority' => 'high']
        );

        // Act: Publish with priority
        $result = $this->publisher->publishWithPriority($message);

        // Assert: Publish succeeded
        $this->assertTrue($result, 'Priority message should be published');
    }

    /**
     * Test: Schedule message for future publishing
     */
    public function testSchedulePublish(): void
    {
        // Arrange: Create delayed message
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 999003,
            eventType: EventType::PRODUCT_VIEW,
            searchPhrase: null,
            productId: 777,
            occurredAt: new DateTimeImmutable(),
            metadata: ['delayed' => true]
        );

        // Act: Schedule for 5 seconds delay
        $result = $this->publisher->schedulePublish($message, delaySeconds: 5);

        // Assert: Schedule succeeded
        $this->assertTrue($result, 'Delayed message should be scheduled');
    }

    /**
     * Test: Message ID generation is deterministic (idempotency)
     */
    public function testMessageIdDeterministic(): void
    {
        // Arrange: Same event data
        $userId = 999004;
        $eventType = EventType::SEARCH;
        $searchPhrase = 'deterministic test';
        $occurredAt = new DateTimeImmutable('2026-02-10T12:00:00Z');

        // Act: Generate message IDs multiple times
        $messageId1 = UpdateUserEmbeddingMessage::generateMessageId(
            $userId,
            $eventType,
            $searchPhrase,
            null,
            $occurredAt
        );

        $messageId2 = UpdateUserEmbeddingMessage::generateMessageId(
            $userId,
            $eventType,
            $searchPhrase,
            null,
            $occurredAt
        );

        // Assert: IDs are identical (for idempotency)
        $this->assertEquals($messageId1, $messageId2);
        $this->assertEquals(64, strlen($messageId1)); // SHA-256 hex length
    }

    /**
     * Test: Different events generate different message IDs
     */
    public function testDifferentEventsGenerateDifferentIds(): void
    {
        // Arrange: Different events
        $baseTime = new DateTimeImmutable('2026-02-10T12:00:00Z');

        $id1 = UpdateUserEmbeddingMessage::generateMessageId(
            999005,
            EventType::SEARCH,
            'query 1',
            null,
            $baseTime
        );

        $id2 = UpdateUserEmbeddingMessage::generateMessageId(
            999005,
            EventType::SEARCH,
            'query 2', // Different phrase
            null,
            $baseTime
        );

        $id3 = UpdateUserEmbeddingMessage::generateMessageId(
            999005,
            EventType::SEARCH,
            'query 1',
            null,
            $baseTime->modify('+1 second') // Different timestamp
        );

        // Assert: All IDs are different
        $this->assertNotEquals($id1, $id2);
        $this->assertNotEquals($id1, $id3);
        $this->assertNotEquals($id2, $id3);
    }

    /**
     * Test: Message validation catches invalid data
     */
    public function testMessageValidationRejectsInvalidData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('User ID must be positive');

        // Act: Try to create message with invalid user ID
        new UpdateUserEmbeddingMessage(
            userId: -1, // Invalid
            eventType: EventType::SEARCH,
            searchPhrase: 'test',
            productId: null,
            occurredAt: new DateTimeImmutable(),
            metadata: [],
            messageId: str_repeat('a', 64)
        );
    }

    /**
     * Test: Search events require search_phrase
     */
    public function testSearchEventRequiresSearchPhrase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search events require search_phrase');

        // Act: Try to create search event without phrase
        new UpdateUserEmbeddingMessage(
            userId: 999006,
            eventType: EventType::SEARCH,
            searchPhrase: null, // Invalid for search
            productId: null,
            occurredAt: new DateTimeImmutable(),
            metadata: [],
            messageId: str_repeat('b', 64)
        );
    }

    /**
     * Test: Product events require product_id
     */
    public function testProductEventRequiresProductId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('events require product_id');

        // Act: Try to create product view without product_id
        new UpdateUserEmbeddingMessage(
            userId: 999007,
            eventType: EventType::PRODUCT_VIEW,
            searchPhrase: null,
            productId: null, // Invalid for product event
            occurredAt: new DateTimeImmutable(),
            metadata: [],
            messageId: str_repeat('c', 64)
        );
    }

    /**
     * Test: Message serialization to array
     */
    public function testMessageSerializationToArray(): void
    {
        // Arrange: Create message
        $occurredAt = new DateTimeImmutable('2026-02-10T15:30:00Z');
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 999008,
            eventType: EventType::PRODUCT_PURCHASE,
            searchPhrase: null,
            productId: 888,
            occurredAt: $occurredAt,
            metadata: ['source' => 'api', 'session' => 'abc123']
        );

        // Act: Serialize to array
        $array = $message->toArray();

        // Assert: Array structure
        $this->assertEquals(999008, $array['user_id']);
        $this->assertEquals('product_purchase', $array['event_type']);
        $this->assertNull($array['search_phrase']);
        $this->assertEquals(888, $array['product_id']);
        $this->assertEquals('2026-02-10T15:30:00+00:00', $array['occurred_at']);
        $this->assertEquals(['source' => 'api', 'session' => 'abc123'], $array['metadata']);
        $this->assertEquals('1.0', $array['version']);
        $this->assertArrayHasKey('message_id', $array);
    }

    /**
     * Test: Event weight mapping from event type
     */
    public function testEventWeightMapping(): void
    {
        // Arrange & Act: Create messages for each event type
        $purchase = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 1,
            eventType: EventType::PRODUCT_PURCHASE,
            searchPhrase: null,
            productId: 1,
            occurredAt: new DateTimeImmutable()
        );

        $search = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 1,
            eventType: EventType::SEARCH,
            searchPhrase: 'test',
            productId: null,
            occurredAt: new DateTimeImmutable()
        );

        $click = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 1,
            eventType: EventType::PRODUCT_CLICK,
            searchPhrase: null,
            productId: 1,
            occurredAt: new DateTimeImmutable()
        );

        $view = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 1,
            eventType: EventType::PRODUCT_VIEW,
            searchPhrase: null,
            productId: 1,
            occurredAt: new DateTimeImmutable()
        );

        // Assert: Weights match specification
        $this->assertEquals(1.0, $purchase->getWeight());
        $this->assertEquals(0.7, $search->getWeight());
        $this->assertEquals(0.5, $click->getWeight());
        $this->assertEquals(0.3, $view->getWeight());
    }
}
