<?php

declare(strict_types=1);

namespace Tests\Integration\Queue;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use App\Infrastructure\Queue\RabbitMQPublisher;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * ProductEventProcessingTest - Integration tests for product event processing
 * 
 * Tests spec-014 US2: Product view/click/purchase events update embeddings with product vectors
 */
class ProductEventProcessingTest extends KernelTestCase
{
    private RabbitMQPublisher $publisher;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();

        $this->publisher = $container->get(RabbitMQPublisher::class);
    }

    /**
     * Test: Product view event publishes successfully
     */
    public function testProductViewEventPublishes(): void
    {
        // Arrange: Product view message
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 1001,
            eventType: EventType::PRODUCT_VIEW,
            searchPhrase: null,
            productId: 555,
            occurredAt: new DateTimeImmutable(),
            metadata: ['source' => 'product_page']
        );

        // Act: Publish to queue
        $result = $this->publisher->publish($message);

        // Assert: Success
        $this->assertTrue($result, 'Product view event should publish successfully');
        $this->assertEquals(0.3, $message->getWeight(), 'Product view weight should be 0.3');
    }

    /**
     * Test: Product click event has correct weight
     */
    public function testProductClickEventWeight(): void
    {
        // Arrange: Product click message
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 1002,
            eventType: EventType::PRODUCT_CLICK,
            searchPhrase: null,
            productId: 777,
            occurredAt: new DateTimeImmutable()
        );

        // Assert: Weight is 0.5
        $this->assertEquals(0.5, $message->getWeight(), 'Product click weight should be 0.5');
    }

    /**
     * Test: Product purchase event has highest weight
     */
    public function testProductPurchaseEventWeight(): void
    {
        // Arrange: Product purchase message
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 1003,
            eventType: EventType::PRODUCT_PURCHASE,
            searchPhrase: null,
            productId: 999,
            occurredAt: new DateTimeImmutable()
        );

        // Assert: Weight is 1.0 (highest)
        $this->assertEquals(1.0, $message->getWeight(), 'Product purchase weight should be 1.0 (highest)');
    }

    /**
     * Test: Product event without product_id throws validation error
     */
    public function testProductEventWithoutProductIdThrowsError(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('require product_id');

        // Act: Try to create product event without product_id
        new UpdateUserEmbeddingMessage(
            userId: 1004,
            eventType: EventType::PRODUCT_VIEW,
            searchPhrase: null,
            productId: null, // Invalid - product events require this
            occurredAt: new DateTimeImmutable(),
            metadata: [],
            messageId: str_repeat('a', 64)
        );
    }

    /**
     * Test: Mixed event batch processing
     */
    public function testMixedEventBatchProcessing(): void
    {
        // Arrange: Multiple event types
        $events = [
            UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: 2001,
                eventType: EventType::SEARCH,
                searchPhrase: 'laptop',
                productId: null,
                occurredAt: new DateTimeImmutable()
            ),
            UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: 2001,
                eventType: EventType::PRODUCT_VIEW,
                searchPhrase: null,
                productId: 101,
                occurredAt: new DateTimeImmutable()
            ),
            UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: 2001,
                eventType: EventType::PRODUCT_CLICK,
                searchPhrase: null,
                productId: 101,
                occurredAt: new DateTimeImmutable()
            ),
            UpdateUserEmbeddingMessage::fromDomainEvent(
                userId: 2001,
                eventType: EventType::PRODUCT_PURCHASE,
                searchPhrase: null,
                productId: 101,
                occurredAt: new DateTimeImmutable()
            ),
        ];

        // Act: Batch publish
        $result = $this->publisher->publishBatch($events);

        // Assert: All published
        $this->assertEquals(4, $result['success']);
        $this->assertEquals(0, $result['failed']);
    }

    /**
     * Test: Product event serialization includes product_id
     */
    public function testProductEventSerializationIncludesProductId(): void
    {
        // Arrange: Product event
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 3001,
            eventType: EventType::PRODUCT_PURCHASE,
            searchPhrase: null,
            productId: 888,
            occurredAt: new DateTimeImmutable('2026-02-10T10:00:00Z')
        );

        // Act: Serialize to array
        $array = $message->toArray();

        // Assert: product_id present
        $this->assertArrayHasKey('product_id', $array);
        $this->assertEquals(888, $array['product_id']);
        $this->assertNull($array['search_phrase']);
        $this->assertEquals('product_purchase', $array['event_type']);
    }

    /**
     * Test: Event weight progression (view < click < purchase)
     */
    public function testEventWeightProgression(): void
    {
        $view = EventType::PRODUCT_VIEW->weight();
        $click = EventType::PRODUCT_CLICK->weight();
        $purchase = EventType::PRODUCT_PURCHASE->weight();

        // Assert: Ascending progression
        $this->assertLessThan($click, $view, 'View weight should be less than click');
        $this->assertLessThan($purchase, $click, 'Click weight should be less than purchase');
        $this->assertEquals(0.3, $view, 'View weight should be 0.3');
        $this->assertEquals(0.5, $click, 'Click weight should be 0.5');
        $this->assertEquals(1.0, $purchase, 'Purchase weight should be 1.0');
    }

    /**
     * Test: Search vs purchase weight comparison
     */
    public function testSearchVsPurchaseWeightComparison(): void
    {
        $searchWeight = EventType::SEARCH->weight();
        $purchaseWeight = EventType::PRODUCT_PURCHASE->weight();

        // Assert: Purchase stronger signal than search
        $this->assertLessThan($purchaseWeight, $searchWeight);
        $this->assertEquals(0.7, $searchWeight, 'Search weight should be 0.7');
        $this->assertEquals(1.0, $purchaseWeight, 'Purchase weight should be 1.0');
    }

    /**
     * Test: Product event requires product_id via EventType check
     */
    public function testEventTypeRequiresProduct(): void
    {
        $this->assertTrue(EventType::PRODUCT_VIEW->requiresProduct());
        $this->assertTrue(EventType::PRODUCT_CLICK->requiresProduct());
        $this->assertTrue(EventType::PRODUCT_PURCHASE->requiresProduct());
        $this->assertFalse(EventType::SEARCH->requiresProduct());
    }

    /**
     * Test: Search event cannot have product_id validation
     */
    public function testSearchEventProductIdValidation(): void
    {
        // Arrange & Act: Create search event (product_id is allowed but ignored)
        $message = UpdateUserEmbeddingMessage::fromDomainEvent(
            userId: 4001,
            eventType: EventType::SEARCH,
            searchPhrase: 'headphones',
            productId: null, // Search events don't require product_id
            occurredAt: new DateTimeImmutable()
        );

        // Assert: Valid message created
        $this->assertInstanceOf(UpdateUserEmbeddingMessage::class, $message);
        $this->assertEquals(EventType::SEARCH, $message->eventType);
        $this->assertNull($message->productId);
    }
}
