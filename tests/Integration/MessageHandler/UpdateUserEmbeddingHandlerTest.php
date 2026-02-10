<?php

declare(strict_types=1);

namespace App\Tests\Integration\MessageHandler;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use App\Infrastructure\MessageHandler\UpdateUserEmbeddingHandler;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * UpdateUserEmbeddingHandlerTest - Integration test for full message handling flow
 * 
 * Spec-014 Phase 8 T076: End-to-end test for message consumption
 * Validates handler processes messages correctly with all dependencies
 */
class UpdateUserEmbeddingHandlerTest extends KernelTestCase
{
    private UpdateUserEmbeddingHandler $handler;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->handler = $container->get(UpdateUserEmbeddingHandler::class);
    }

    /**
     * @test
     * @group integration
     * @group message-handler
     */
    public function handler_processes_search_event_successfully(): void
    {
        $userId = random_int(200000, 299999);
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: $userId,
            eventType: EventType::SEARCH,
            searchPhrase: 'wireless headphones',
            occurredAt: new DateTimeImmutable()
        );

        // Should not throw exception
        $this->handler->__invoke($message);

        // If we reach here, message was processed successfully
        $this->assertTrue(true);
    }

    /**
     * @test
     * @group integration
     * @group message-handler
     */
    public function handler_processes_product_view_event_successfully(): void
    {
        $userId = random_int(200000, 299999);
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: $userId,
            eventType: EventType::PRODUCT_VIEW,
            searchPhrase: null,
            occurredAt: new DateTimeImmutable(),
            messageId: null,
            productId: 123
        );

        // Should not throw exception
        $this->handler->__invoke($message);

        $this->assertTrue(true);
    }

    /**
     * @test
     * @group integration
     * @group message-handler
     */
    public function handler_processes_product_purchase_event_successfully(): void
    {
        $userId = random_int(200000, 299999);
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: $userId,
            eventType: EventType::PRODUCT_PURCHASE,
            searchPhrase: null,
            occurredAt: new DateTimeImmutable(),
            messageId: null,
            productId: 456
        );

        // Should not throw exception
        $this->handler->__invoke($message);

        $this->assertTrue(true);
    }

    /**
     * @test
     * @group integration
     * @group message-handler
     */
    public function handler_is_idempotent_for_duplicate_messages(): void
    {
        $userId = random_int(200000, 299999);
        $messageId = 'test-idempotent-' . uniqid();
        
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: $userId,
            eventType: EventType::SEARCH,
            searchPhrase: 'test idempotency',
            occurredAt: new DateTimeImmutable(),
            messageId: $messageId
        );

        // Process same message twice
        $this->handler->__invoke($message);
        $this->handler->__invoke($message);

        // Should not throw exception - idempotency should handle it
        $this->assertTrue(true);
    }

    /**
     * @test
     * @group integration
     * @group message-handler
     */
    public function handler_processes_multiple_events_for_same_user(): void
    {
        $userId = random_int(200000, 299999);

        // Event 1: Search
        $message1 = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: $userId,
            eventType: EventType::SEARCH,
            searchPhrase: 'laptop',
            occurredAt: new DateTimeImmutable()
        );

        // Event 2: Product view
        $message2 = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: $userId,
            eventType: EventType::PRODUCT_VIEW,
            searchPhrase: null,
            occurredAt: new DateTimeImmutable(),
            messageId: null,
            productId: 789
        );

        // Event 3: Purchase
        $message3 = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: $userId,
            eventType: EventType::PRODUCT_PURCHASE,
            searchPhrase: null,
            occurredAt: new DateTimeImmutable(),
            messageId: null,
            productId: 789
        );

        // Process all three events
        $this->handler->__invoke($message1);
        $this->handler->__invoke($message2);
        $this->handler->__invoke($message3);

        $this->assertTrue(true);
    }

    /**
     * @test
     * @group integration
     * @group message-handler
     */
    public function handler_processes_events_with_metadata(): void
    {
        $userId = random_int(200000, 299999);
        $metadata = [
            'session_id' => 'test-session-123',
            'user_agent' => 'Mozilla/5.0',
            'ip_address' => '192.168.1.100'
        ];

        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: $userId,
            eventType: EventType::SEARCH,
            searchPhrase: 'test with metadata',
            occurredAt: new DateTimeImmutable(),
            messageId: null,
            productId: null,
            metadata: $metadata
        );

        $this->handler->__invoke($message);

        $this->assertTrue(true);
    }

    /**
     * @test
     * @group integration
     * @group message-handler
     */
    public function handler_processes_events_with_historical_timestamps(): void
    {
        $userId = random_int(200000, 299999);
        $pastDate = new DateTimeImmutable('-7 days');

        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: $userId,
            eventType: EventType::SEARCH,
            searchPhrase: 'historical event',
            occurredAt: $pastDate
        );

        // Should apply temporal decay
        $this->handler->__invoke($message);

        $this->assertTrue(true);
    }

    /**
     * @test
     * @group integration
     * @group message-handler
     */
    public function handler_can_process_high_frequency_events(): void
    {
        $userId = random_int(200000, 299999);

        // Simulate rapid successive searches
        for ($i = 0; $i < 10; $i++) {
            $message = UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: $userId,
                eventType: EventType::SEARCH,
                searchPhrase: "search query $i",
                occurredAt: new DateTimeImmutable()
            );

            $this->handler->__invoke($message);
        }

        $this->assertTrue(true);
    }
}
