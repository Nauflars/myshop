<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use App\Domain\Entity\Product;

/**
 * SearchResult - Value object for search results
 * 
 * Implements spec-010 T031: Encapsulates search results with scores and metadata
 */
class SearchResult
{
    /**
     * @param array<Product> $products
     * @param array<string, float> $scores Product ID => similarity score
     * @param string $mode 'semantic' or 'keyword'
     * @param int $totalResults
     * @param float $executionTimeMs
     */
    public function __construct(
        private readonly array $products,
        private readonly array $scores,
        private readonly string $mode,
        private readonly int $totalResults,
        private readonly float $executionTimeMs
    ) {
    }

    /**
     * @return array<Product>
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * @return array<string, float>
     */
    public function getScores(): array
    {
        return $this->scores;
    }

    public function getScoreForProduct(Product $product): ?float
    {
        return $this->scores[$product->getId()] ?? null;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getTotalResults(): int
    {
        return $this->totalResults;
    }

    public function getExecutionTimeMs(): float
    {
        return $this->executionTimeMs;
    }

    public function isEmpty(): bool
    {
        return empty($this->products);
    }

    public function count(): int
    {
        return count($this->products);
    }

    public function toArray(): array
    {
        return [
            'products' => array_map(
                fn(Product $p) => [
                    'id' => $p->getId(),
                    'name' => $p->getName(),
                    'description' => $p->getDescription(),
                    'price' => [
                        'amount' => $p->getPrice()->getAmountAsDecimal(),
                        'currency' => $p->getPrice()->getCurrency(),
                    ],
                    'category' => $p->getCategory(),
                    'stock' => $p->getStock(),
                    'in_stock' => $p->isInStock(),
                    'score' => $this->scores[$p->getId()] ?? null,
                ],
                $this->products
            ),
            'metadata' => [
                'mode' => $this->mode,
                'total_results' => $this->totalResults,
                'returned_results' => count($this->products),
                'execution_time_ms' => round($this->executionTimeMs, 2),
            ],
        ];
    }
}
