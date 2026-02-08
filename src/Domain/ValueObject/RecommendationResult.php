<?php

namespace App\Domain\ValueObject;

use App\Domain\Entity\Product;

/**
 * Result containing recommended products with similarity scores
 * 
 * Immutable value object returned by recommendation service
 */
final class RecommendationResult
{
    private array $products;
    private array $similarityScores;
    private \DateTimeImmutable $generatedAt;

    /**
     * @param Product[] $products Array of Product entities
     * @param float[] $similarityScores Cosine similarity scores (0-1)
     */
    public function __construct(array $products, array $similarityScores)
    {
        if (count($products) !== count($similarityScores)) {
            throw new \InvalidArgumentException('Products and scores arrays must have the same length');
        }

        $this->products = $products;
        $this->similarityScores = $similarityScores;
        $this->generatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Product[]
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    /**
     * @return float[]
     */
    public function getSimilarityScores(): array
    {
        return $this->similarityScores;
    }

    public function getGeneratedAt(): \DateTimeImmutable
    {
        return $this->generatedAt;
    }

    /**
     * Get product at specific index with its score
     */
    public function getProductWithScore(int $index): ?array
    {
        if (!isset($this->products[$index])) {
            return null;
        }

        return [
            'product' => $this->products[$index],
            'score' => $this->similarityScores[$index],
        ];
    }

    /**
     * Get all products with their scores
     * 
     * @return array Array of ['product' => Product, 'score' => float]
     */
    public function getProductsWithScores(): array
    {
        $result = [];
        foreach ($this->products as $index => $product) {
            $result[] = [
                'product' => $product,
                'score' => $this->similarityScores[$index],
            ];
        }
        return $result;
    }

    /**
     * Get count of recommendations
     */
    public function count(): int
    {
        return count($this->products);
    }

    /**
     * Check if result is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->products);
    }

    /**
     * Get average similarity score
     */
    public function getAverageScore(): float
    {
        if (empty($this->similarityScores)) {
            return 0.0;
        }

        return array_sum($this->similarityScores) / count($this->similarityScores);
    }

    /**
     * Filter products by minimum similarity score
     */
    public function filterByMinScore(float $minScore): self
    {
        $filtered = [];
        $filteredScores = [];

        foreach ($this->products as $index => $product) {
            if ($this->similarityScores[$index] >= $minScore) {
                $filtered[] = $product;
                $filteredScores[] = $this->similarityScores[$index];
            }
        }

        return new self($filtered, $filteredScores);
    }
}
