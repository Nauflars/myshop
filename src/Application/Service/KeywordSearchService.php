<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\SearchQuery;
use App\Domain\ValueObject\SearchResult;
use Psr\Log\LoggerInterface;

/**
 * KeywordSearchService - Traditional keyword-based search using MySQL
 * 
 * Implements spec-010 T040-T041: MySQL LIKE queries for keyword search
 */
class KeywordSearchService
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger,
        private readonly ?SearchMetricsCollector $metricsCollector = null
    ) {
    }

    /**
     * Search products using keyword matching
     */
    public function search(SearchQuery $searchQuery): SearchResult
    {
        $startTime = microtime(true);

        $this->logger->info('Executing keyword search', [
            'query' => $searchQuery->getQuery(),
            'limit' => $searchQuery->getLimit(),
            'offset' => $searchQuery->getOffset(),
        ]);

        try {
            $products = $this->searchByKeyword(
                $searchQuery->getQuery(),
                $searchQuery->getLimit(),
                $searchQuery->getOffset(),
                $searchQuery->getCategory()
            );

            $executionTime = (microtime(true) - $startTime) * 1000;

            // For keyword search, all results have score 1.0 (binary match)
            $scores = [];
            foreach ($products as $product) {
                $scores[$product->getId()] = 1.0;
            }

            $result = new SearchResult(
                products: $products,
                scores: $scores,
                mode: 'keyword',
                totalResults: count($products),
                executionTimeMs: $executionTime
            );

            $this->logger->info('Keyword search completed', [
                'results_count' => $result->count(),
                'execution_time_ms' => $result->getExecutionTimeMs(),
            ]);

            // Record metrics for monitoring
            if ($this->metricsCollector !== null) {
                $this->metricsCollector->recordSearch(
                    responseTimeMs: $executionTime,
                    searchMode: 'keyword',
                    resultsCount: count($products),
                    cacheHit: false,
                    openaiCalled: false
                );
            }

            return $result;

        } catch (\Exception $e) {
            $this->logger->error('Keyword search failed', [
                'query' => $searchQuery->getQuery(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Perform MySQL LIKE queries
     * 
     * @return array<Product>
     */
    private function searchByKeyword(
        string $query,
        int $limit,
        int $offset,
        ?string $category
    ): array {
        // This would ideally use a custom repository method
        // For now, we'll use findAll and filter in memory
        // In production, this should be moved to a repository method with proper SQL

        $allProducts = $this->productRepository->findAll();
        $searchTerm = strtolower($query);
        
        $filtered = array_filter($allProducts, function (Product $product) use ($searchTerm, $category) {
            // Category filter
            if ($category !== null && $product->getCategory() !== $category) {
                return false;
            }

            // Keyword matching in name or description
            $name = strtolower($product->getName());
            $description = strtolower($product->getDescription());

            return str_contains($name, $searchTerm) || str_contains($description, $searchTerm);
        });

        // Apply pagination
        $filtered = array_values($filtered);
        return array_slice($filtered, $offset, $limit);
    }
}
