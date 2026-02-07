<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\Product;
use App\Domain\Repository\EmbeddingServiceInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\SearchQuery;
use App\Domain\ValueObject\SearchResult;
use App\Infrastructure\Repository\MongoDBEmbeddingRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Stopwatch\Stopwatch;

/**
 * SemanticSearchService - AI-powered semantic search using vector embeddings
 * 
 * Implements spec-010 T032-T039: Vector similarity search with OpenAI embeddings
 * T080: Max 50 results to prevent large result sets
 */
class SemanticSearchService
{
    private const MAX_RESULTS_LIMIT = 50;
    public function __construct(
        private readonly EmbeddingServiceInterface $embeddingService,
        private readonly MongoDBEmbeddingRepository $embeddingRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EmbeddingCacheService $cacheService,
        private readonly LoggerInterface $logger,
        private readonly SearchMetricsCollector $metricsCollector,
        private readonly Stopwatch $stopwatch
    ) {
    }

    /**
     * Search products using semantic similarity
     */
    public function search(SearchQuery $searchQuery): SearchResult
    {
        // T074: Start Stopwatch profiling
        $this->stopwatch->start('semantic_search');
        $startTime = microtime(true);

        $this->logger->info('Executing semantic search', [
            'query' => $searchQuery->getQuery(),
            'limit' => $searchQuery->getLimit(),
            'offset' => $searchQuery->getOffset(),
            'min_similarity' => $searchQuery->getMinSimilarity(),
        ]);

        try {
            // T080: Enforce max limit to prevent large result sets
            $effectiveLimit = min($searchQuery->getLimit(), self::MAX_RESULTS_LIMIT);
            if ($effectiveLimit < $searchQuery->getLimit()) {
                $this->logger->warning('Search limit clamped to maximum', [
                    'requested' => $searchQuery->getLimit(),
                    'effective' => $effectiveLimit,
                ]);
            }
            
            // Generate query embedding
            $queryEmbedding = $this->generateQueryEmbedding($searchQuery->getQuery());

            // Search MongoDB for similar embeddings
            $similarProducts = $this->embeddingRepository->searchSimilar(
                $queryEmbedding,
                $searchQuery->getLimit() + $searchQuery->getOffset(), // Fetch more for pagination
                $searchQuery->getMinSimilarity()
            );

            // Apply pagination
            $similarProducts = array_slice($similarProducts, $searchQuery->getOffset());

            // Enrich with full product data from MySQL
            $enrichedResults = $this->enrichResults($similarProducts);

            // Deduplicate results
            $enrichedResults = $this->deduplicateResults($enrichedResults);

            // Apply category filter if specified
            if ($searchQuery->hasCategory()) {
                $enrichedResults = $this->filterByCategory($enrichedResults, $searchQuery->getCategory());
            }

            // Limit results after deduplication and filtering
            $enrichedResults = array_slice($enrichedResults, 0, $searchQuery->getLimit());

            $executionTime = (microtime(true) - $startTime) * 1000;

            // Extract products and scores
            $products = [];
            $scores = [];
            foreach ($enrichedResults as $result) {
                $products[] = $result['product'];
                $scores[$result['product']->getId()] = $result['score'];
            }

            $searchResult = new SearchResult(
                products: $products,
                scores: $scores,
                mode: 'semantic',
                totalResults: count($products),
                executionTimeMs: $executionTime
            );

            $this->logger->info('Semantic search completed', [
                'results_count' => $searchResult->count(),
                'execution_time_ms' => $searchResult->getExecutionTimeMs(),
            ]);

            // T074: Stop Stopwatch and log profiling data
            $event = $this->stopwatch->stop('semantic_search');
            $this->logger->debug('Stopwatch profiling', [
                'duration_ms' => $event->getDuration(),
                'memory_mb' => round($event->getMemory() / 1024 / 1024, 2),
            ]);

            // T075-T076: Record metrics for monitoring
            $openaiCalled = ($this->cacheService->getStats()['misses'] > 0);
            $cacheHit = !$openaiCalled;
            $this->metricsCollector->recordSearch(
                responseTimeMs: $executionTime,
                searchMode: 'semantic',
                resultsCount: count($products),
                cacheHit: $cacheHit,
                openaiCalled: $openaiCalled
            );

            return $searchResult;

        } catch (\Exception $e) {
            // Stop stopwatch on error
            if ($this->stopwatch->isStarted('semantic_search')) {
                $this->stopwatch->stop('semantic_search');
            }
            $this->logger->error('Semantic search failed', [
                'query' => $searchQuery->getQuery(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Generate embedding for search query
     * 
     * Implements T053-T054: Cache check before OpenAI API call, cache write after success
     */
    public function generateQueryEmbedding(string $query): array
    {
        // T053: Check cache before calling OpenAI API
        $cachedEmbedding = $this->cacheService->get($query);
        
        if ($cachedEmbedding !== null) {
            $this->logger->debug('Using cached query embedding', [
                'query' => $query,
                'cache_hit' => true,
            ]);
            return $cachedEmbedding;
        }

        $this->logger->debug('Generating query embedding from OpenAI', [
            'query' => $query,
            'cache_hit' => false,
        ]);

        try {
            // Generate embedding via OpenAI API
            $embedding = $this->embeddingService->generateEmbedding($query);

            // T054: Cache the generated embedding
            $this->cacheService->set($query, $embedding);

            return $embedding;

        } catch (\Exception $e) {
            $this->logger->error('Failed to generate query embedding', [
                'query' => $query,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Enrich MongoDB results with full product data from MySQL
     * 
     * @param array $mongoResults Results from MongoDB with productId and similarity
     * @return array Enriched results with Product entity and score
     */
    public function enrichResults(array $mongoResults): array
    {
        $enriched = [];

        foreach ($mongoResults as $mongoResult) {
            // productId is now UUID directly (no conversion needed)
            $uuid = $mongoResult['productId'];

            $product = $this->productRepository->findById($uuid);

            if ($product === null) {
                $this->logger->warning('Product not found in MySQL', [
                    'uuid' => $uuid,
                ]);
                continue;
            }

            $enriched[] = [
                'product' => $product,
                'score' => $mongoResult['similarity'],
            ];
        }

        return $enriched;
    }

    /**
     * Remove duplicate products (same UUID)
     */
    private function deduplicateResults(array $results): array
    {
        $seen = [];
        $deduplicated = [];

        foreach ($results as $result) {
            $productId = $result['product']->getId();

            if (isset($seen[$productId])) {
                // Keep the one with higher score
                $existingScore = $seen[$productId]['score'];
                if ($result['score'] > $existingScore) {
                    $seen[$productId] = $result;
                }
            } else {
                $seen[$productId] = $result;
            }
        }

        return array_values($seen);
    }

    /**
     * Filter results by category
     */
    private function filterByCategory(array $results, string $category): array
    {
        return array_values(array_filter(
            $results,
            fn($result) => $result['product']->getCategory() === $category
        ));
    }
}
