<?php

namespace App\Domain\ValueObject;

/**
 * Snapshot of user's recent activity for profile generation
 * 
 * Immutable value object containing aggregated data used to generate
 * the user's embedding vector.
 */
final class ProfileSnapshot
{
    private array $recentPurchases;
    private array $recentSearches;
    private array $dominantCategories;

    /**
     * @param array $recentPurchases Array of product names from recent purchases
     * @param array $recentSearches Array of search query strings
     * @param array $dominantCategories Array of category names
     */
    public function __construct(
        array $recentPurchases,
        array $recentSearches,
        array $dominantCategories
    ) {
        $this->recentPurchases = $recentPurchases;
        $this->recentSearches = $recentSearches;
        $this->dominantCategories = $dominantCategories;
    }

    public function getRecentPurchases(): array
    {
        return $this->recentPurchases;
    }

    public function getRecentSearches(): array
    {
        return $this->recentSearches;
    }

    public function getDominantCategories(): array
    {
        return $this->dominantCategories;
    }

    /**
     * Check if snapshot has any meaningful data
     */
    public function isEmpty(): bool
    {
        return empty($this->recentPurchases) 
            && empty($this->recentSearches) 
            && empty($this->dominantCategories);
    }

    /**
     * Get weighted text representation for embedding generation
     * 
     * Purchases: 70%, Searches: 20%, Categories: 10%
     */
    public function toWeightedText(): string
    {
        $parts = [];

        // Purchases (70% weight) - repeat 7 times
        if (!empty($this->recentPurchases)) {
            $purchaseText = implode(', ', $this->recentPurchases);
            $parts[] = str_repeat($purchaseText . '. ', 7);
        }

        // Searches (20% weight) - repeat 2 times
        if (!empty($this->recentSearches)) {
            $searchText = implode(', ', $this->recentSearches);
            $parts[] = str_repeat($searchText . '. ', 2);
        }

        // Categories (10% weight) - single occurrence
        if (!empty($this->dominantCategories)) {
            $categoryText = implode(', ', $this->dominantCategories);
            $parts[] = $categoryText . '.';
        }

        return implode(' ', $parts);
    }

    /**
     * Get count of total data points
     */
    public function getDataPointCount(): int
    {
        return count($this->recentPurchases) 
            + count($this->recentSearches) 
            + count($this->dominantCategories);
    }
}
