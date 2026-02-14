<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * EventType - Type-safe enumeration for user interaction types with associated weights.
 *
 * Implements spec-014 data model: Event type with weight calculation
 * Weights determined by business value: purchase > search > click > view
 */
enum EventType: string
{
    case SEARCH = 'search';
    case PRODUCT_VIEW = 'product_view';
    case PRODUCT_CLICK = 'product_click';
    case PRODUCT_PURCHASE = 'product_purchase';

    /**
     * Get event weight for embedding calculation.
     *
     * Weights based on business value:
     * - Purchase: 1.0 (strongest signal of interest)
     * - Search: 0.7 (explicit intent)
     * - Click: 0.5 (moderate engagement)
     * - View: 0.3 (passive browsing)
     */
    public function weight(): float
    {
        return match ($this) {
            self::PRODUCT_PURCHASE => 1.0,
            self::SEARCH => 0.7,
            self::PRODUCT_CLICK => 0.5,
            self::PRODUCT_VIEW => 0.3,
        };
    }

    /**
     * Check if event type requires product_id.
     */
    public function requiresProduct(): bool
    {
        return match ($this) {
            self::PRODUCT_VIEW, self::PRODUCT_CLICK, self::PRODUCT_PURCHASE => true,
            self::SEARCH => false,
        };
    }

    /**
     * Check if event type requires search_phrase.
     */
    public function requiresSearchPhrase(): bool
    {
        return self::SEARCH === $this;
    }

    /**
     * Get human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::SEARCH => 'Search Query',
            self::PRODUCT_VIEW => 'Product View',
            self::PRODUCT_CLICK => 'Product Click',
            self::PRODUCT_PURCHASE => 'Product Purchase',
        };
    }

    /**
     * Create from string value with validation.
     */
    public static function fromString(string $value): self
    {
        return self::from($value);
    }
}
