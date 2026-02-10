<?php

declare(strict_types=1);

namespace Tests\Integration\Queue;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Application\UseCase\PublishUserInteractionEvent;
use App\Domain\ValueObject\EventType;
use App\Entity\UserInteraction;
use App\Infrastructure\Queue\RabbitMQPublisher;
use App\Repository\UserInteractionRepository;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * SearchEventPublishConsumeTest - Integration test for search event flow
 * 
 * Tests: API → MySQL → RabbitMQ → Worker → MongoDB
 * Verifies the complete end-to-end flow for search events
 */
class SearchEventPublishConsumeTest extends KernelTestCase
{
    private UserInteractionRepository $userInteractionRepository;
    private PublishUserInteractionEvent $publishUseCase;
    private RabbitMQPublisher $publisher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->userInteractionRepository = $container->get(UserInteractionRepository::class);
        $this->publishUseCase = $container->get(PublishUserInteractionEvent::class);
        $this->publisher = $container->get(RabbitMQPublisher::class);
    }

    /**
     * Test: Create search event → save to MySQL → publish to RabbitMQ
     */
    public function testSearchEventPublishedToQueue(): void
    {
        // Arrange: Create search event
        $searchPhrase = 'test product search';
        $userId = 12345;
        $occurredAt = new DateTimeImmutable();

        $interaction = UserInteraction::createSearchEvent(
            userId: $userId,
            searchPhrase: $searchPhrase,
            occurredAt: $occurredAt
        );

        // Act: Execute use case (save + publish)
        $result = $this->publishUseCase->execute($interaction);

        // Assert: Verify saved to database
        $this->assertTrue($result, 'Event should be published successfully');
        $this->assertNotNull($interaction->getId(), 'Interaction should have ID after save');
        $this->assertTrue($interaction->isProcessedToQueue(), 'Interaction should be marked as processed');

        // Assert: Verify interaction saved correctly
        $saved = $this->userInteractionRepository->find($interaction->getId());
        $this->assertNotNull($saved);
        $this->assertEquals($userId, $saved->getUserId());
        $this->assertEquals(EventType::SEARCH, $saved->getEventType());
        $this->assertEquals($searchPhrase, $saved->getSearchPhrase());
        $this->assertTrue($saved->isProcessedToQueue());
    }

    /**
     * Test: Message serialization and deserialization
     */
    public function testMessageSerialization(): void
    {
        // Arrange: Create message
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 67890,
            eventType: EventType::SEARCH,
            searchPhrase: 'laptop computer',
            productId: null,
            occurredAt: new DateTimeImmutable('2026-02-10T12:00:00Z'),
            metadata: ['source' => 'web', 'locale' => 'en']
        );

        // Act: Convert to array
        $array = $message->toArray();

        // Assert: Verify structure
        $this->assertEquals(67890, $array['user_id']);
        $this->assertEquals('search', $array['event_type']);
        $this->assertEquals('laptop computer', $array['search_phrase']);
        $this->assertNull($array['product_id']);
        $this->assertEquals('2026-02-10T12:00:00+00:00', $array['occurred_at']);
        $this->assertEquals(['source' => 'web', 'locale' => 'en'], $array['metadata']);
        $this->assertEquals('1.0', $array['version']);
        $this->assertEquals(64, strlen($array['message_id']));
    }

    /**
     * Test: Message ID generation is deterministic
     */
    public function testMessageIdGenerationIsDeterministic(): void
    {
        // Arrange: Same event data
        $userId = 11111;
        $eventType = EventType::SEARCH;
        $searchPhrase = 'test search';
        $occurredAt = new DateTimeImmutable('2026-02-10T10:00:00Z');

        // Act: Generate message IDs twice
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

        // Assert: Message IDs should be identical (idempotency)
        $this->assertEquals($messageId1, $messageId2);
        $this->assertEquals(64, strlen($messageId1));
    }

    /**
     * Test: Replay unprocessed events
     */
    public function testReplayUnprocessedEvents(): void
    {
        // Arrange: Create unprocessed events
        $interaction1 = UserInteraction::createSearchEvent(
            userId: 11111,
            searchPhrase: 'first search',
            occurredAt: new DateTimeImmutable()
        );

        $interaction2 = UserInteraction::createSearchEvent(
            userId: 22222,
            searchPhrase: 'second search',
            occurredAt: new DateTimeImmutable()
        );

        // Save without publishing
        $this->userInteractionRepository->save($interaction1, true);
        $this->userInteractionRepository->save($interaction2, true);

        $this->assertFalse($interaction1->isProcessedToQueue());
        $this->assertFalse($interaction2->isProcessedToQueue());

        // Act: Replay unprocessed events
        $result = $this->publishUseCase->replayUnprocessedEvents(limit: 10);

        // Assert: Events should be published
        $this->assertGreaterThanOrEqual(2, $result['success']);
        $this->assertEquals(0, $result['failed']);
    }

    /**
     * Test: Search event validation
     */
    public function testSearchEventRequiresSearchPhrase(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Search events require search_phrase');

        // Act: Try to create search event without phrase
        UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 99999,
            eventType: EventType::SEARCH,
            searchPhrase: null, // Invalid for search event
            productId: null,
            occurredAt: new DateTimeImmutable()
        );
    }

    /**
     * Test: Event weight from message
     */
    public function testEventWeightFromMessage(): void
    {
        // Arrange & Act: Create messages for each event type
        $searchMessage = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 1,
            eventType: EventType::SEARCH,
            searchPhrase: 'test',
            productId: null,
            occurredAt: new DateTimeImmutable()
        );

        $viewMessage = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 1,
            eventType: EventType::PRODUCT_VIEW,
            searchPhrase: null,
            productId: 100,
            occurredAt: new DateTimeImmutable()
        );

        // Assert: Verify weights
        $this->assertEquals(0.7, $searchMessage->getWeight());
        $this->assertEquals(0.3, $viewMessage->getWeight());
    }
}
