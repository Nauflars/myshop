<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * SearchQuery - Value object for search queries
 * 
 * Implements spec-010 T030: Encapsulates search query parameters
 */
class SearchQuery
{
    private const MIN_QUERY_LENGTH = 2;
    private const MAX_QUERY_LENGTH = 500;

    public function __construct(
        private readonly string $query,
        private readonly int $limit = 10,
        private readonly int $offset = 0,
        private readonly float $minSimilarity = 0.6,
        private readonly ?string $category = null
    ) {
        $this->validate();
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return $this->offset;
    }

    public function getMinSimilarity(): float
    {
        return $this->minSimilarity;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function hasCategory(): bool
    {
        return $this->category !== null && $this->category !== '';
    }

    private function validate(): void
    {
        $queryLength = mb_strlen(trim($this->query));

        if ($queryLength < self::MIN_QUERY_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Query must be at least %d characters long', self::MIN_QUERY_LENGTH)
            );
        }

        if ($queryLength > self::MAX_QUERY_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Query must not exceed %d characters', self::MAX_QUERY_LENGTH)
            );
        }

        if ($this->limit < 1 || $this->limit > 100) {
            throw new \InvalidArgumentException('Limit must be between 1 and 100');
        }

        if ($this->offset < 0) {
            throw new \InvalidArgumentException('Offset must be non-negative');
        }

        if ($this->minSimilarity < 0.0 || $this->minSimilarity > 1.0) {
            throw new \InvalidArgumentException('minSimilarity must be between 0.0 and 1.0');
        }
    }

    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'limit' => $this->limit,
            'offset' => $this->offset,
            'min_similarity' => $this->minSimilarity,
            'category' => $this->category,
        ];
    }
}
