<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\EventType;
use PHPUnit\Framework\TestCase;

/**
 * EventTypeTest - Unit tests for EventType enum
 * 
 * Tests spec-014 T019: Event type value object with weight calculation
 */
class EventTypeTest extends TestCase
{
    public function testEventTypeValues(): void
    {
        $this->assertSame('search', EventType::SEARCH->value);
        $this->assertSame('product_view', EventType::PRODUCT_VIEW->value);
        $this->assertSame('product_click', EventType::PRODUCT_CLICK->value);
        $this->assertSame('product_purchase', EventType::PRODUCT_PURCHASE->value);
    }

    public function testEventWeights(): void
    {
        // Verify spec-defined weights
        $this->assertSame(1.0, EventType::PRODUCT_PURCHASE->weight());
        $this->assertSame(0.7, EventType::SEARCH->weight());
        $this->assertSame(0.5, EventType::PRODUCT_CLICK->weight());
        $this->assertSame(0.3, EventType::PRODUCT_VIEW->weight());
    }

    public function testWeightOrderingIsCorrect(): void
    {
        // Purchase should have highest weight
        $this->assertGreaterThan(EventType::SEARCH->weight(), EventType::PRODUCT_PURCHASE->weight());
        $this->assertGreaterThan(EventType::PRODUCT_CLICK->weight(), EventType::PRODUCT_PURCHASE->weight());
        $this->assertGreaterThan(EventType::PRODUCT_VIEW->weight(), EventType::PRODUCT_PURCHASE->weight());

        // Search should be second
        $this->assertGreaterThan(EventType::PRODUCT_CLICK->weight(), EventType::SEARCH->weight());
        $this->assertGreaterThan(EventType::PRODUCT_VIEW->weight(), EventType::SEARCH->weight());

        // Click should be third
        $this->assertGreaterThan(EventType::PRODUCT_VIEW->weight(), EventType::PRODUCT_CLICK->weight());
    }

    public function testRequiresProduct(): void
    {
        $this->assertFalse(EventType::SEARCH->requiresProduct());
        $this->assertTrue(EventType::PRODUCT_VIEW->requiresProduct());
        $this->assertTrue(EventType::PRODUCT_CLICK->requiresProduct());
        $this->assertTrue(EventType::PRODUCT_PURCHASE->requiresProduct());
    }

    public function testRequiresSearchPhrase(): void
    {
        $this->assertTrue(EventType::SEARCH->requiresSearchPhrase());
        $this->assertFalse(EventType::PRODUCT_VIEW->requiresSearchPhrase());
        $this->assertFalse(EventType::PRODUCT_CLICK->requiresSearchPhrase());
        $this->assertFalse(EventType::PRODUCT_PURCHASE->requiresSearchPhrase());
    }

    public function testLabels(): void
    {
        $this->assertSame('Search Query', EventType::SEARCH->label());
        $this->assertSame('Product View', EventType::PRODUCT_VIEW->label());
        $this->assertSame('Product Click', EventType::PRODUCT_CLICK->label());
        $this->assertSame('Product Purchase', EventType::PRODUCT_PURCHASE->label());
    }

    public function testFromStringValid(): void
    {
        $this->assertSame(EventType::SEARCH, EventType::fromString('search'));
        $this->assertSame(EventType::PRODUCT_VIEW, EventType::fromString('product_view'));
        $this->assertSame(EventType::PRODUCT_CLICK, EventType::fromString('product_click'));
        $this->assertSame(EventType::PRODUCT_PURCHASE, EventType::fromString('product_purchase'));
    }

    public function testFromStringInvalid(): void
    {
        $this->expectException(\ValueError::class);
        EventType::fromString('invalid_event');
    }

    public function testAllEventTypesHavePositiveWeight(): void
    {
        foreach (EventType::cases() as $eventType) {
            $this->assertGreaterThan(0, $eventType->weight(), 
                sprintf('Event type %s must have positive weight', $eventType->value)
            );
            $this->assertLessThanOrEqual(1.0, $eventType->weight(),
                sprintf('Event type %s weight must not exceed 1.0', $eventType->value)
            );
        }
    }

    public function testExactlyOneEventTypeRequiresSearchPhrase(): void
    {
        $searchPhraseCount = 0;
        foreach (EventType::cases() as $eventType) {
            if ($eventType->requiresSearchPhrase()) {
                $searchPhraseCount++;
            }
        }
        $this->assertSame(1, $searchPhraseCount, 'Exactly one event type should require search phrase');
    }

    public function testProductEventsMutuallyExclusive(): void
    {
        // Events should either require product OR search phrase, not both
        foreach (EventType::cases() as $eventType) {
            $requiresProduct = $eventType->requiresProduct();
            $requiresSearch = $eventType->requiresSearchPhrase();
            
            $this->assertFalse(
                $requiresProduct && $requiresSearch,
                sprintf('Event type %s cannot require both product and search phrase', $eventType->value)
            );
        }
    }
}
