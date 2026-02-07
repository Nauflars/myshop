<?php

declare(strict_types=1);

namespace App\Application\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * SearchMetricsCollector - Collect and analyze search performance metrics
 * 
 * Implements spec-010 T075-T077:
 * - Response time tracking (p50, p95, p99)
 * - OpenAI API call counter and cost estimation
 * - Query performance monitoring
 * 
 * Stores metrics in Redis with rolling windows for performance analysis
 */
class SearchMetricsCollector
{
    private const CACHE_TTL = 86400; // 24 hours
    private const MAX_SAMPLES = 10000; // Keep last 10K searches for percentile calculation
    
    // OpenAI API costs (as of 2024)
    private const EMBEDDING_COST_PER_1M_TOKENS = 0.02; // text-embedding-3-small
    private const AVG_TOKENS_PER_QUERY = 20; // Estimated average query length
    
    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Record a semantic search operation
     * 
     * @param float $responseTimeMs Response time in milliseconds
     * @param string $searchMode 'semantic' or 'keyword'
     * @param int $resultsCount Number of results returned
     * @param bool $cacheHit Whether embedding was cached
     * @param bool $openaiCalled Whether OpenAI API was called
     */
    public function recordSearch(
        float $responseTimeMs,
        string $searchMode,
        int $resultsCount,
        bool $cacheHit = false,
        bool $openaiCalled = false
    ): void {
        try {
            // Record response time for percentile calculation
            $this->recordResponseTime($responseTimeMs, $searchMode);
            
            // Track search count by mode
            $this->incrementCounter("search_count_{$searchMode}");
            $this->incrementCounter('search_count_total');
            
            // Track empty searches (no results)
            if ($resultsCount === 0) {
                $this->incrementCounter("empty_search_count_{$searchMode}");
            }
            
            // Track cache performance
            if ($searchMode === 'semantic') {
                if ($cacheHit) {
                    $this->incrementCounter('embedding_cache_hits');
                } else {
                    $this->incrementCounter('embedding_cache_misses');
                }
            }
            
            // Track OpenAI API usage and costs
            if ($openaiCalled) {
                $this->recordOpenAICall();
            }
            
            $this->logger->debug('Search metrics recorded', [
                'response_time_ms' => $responseTimeMs,
                'mode' => $searchMode,
                'results' => $resultsCount,
                'cache_hit' => $cacheHit,
                'openai_called' => $openaiCalled,
            ]);
            
        } catch (\Exception $e) {
            // Don't fail search operations due to metrics issues
            $this->logger->error('Failed to record search metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record MongoDB query performance
     * 
     * @param float $queryTimeMs MongoDB query execution time in milliseconds
     * @param int $documentsScanned Number of documents scanned
     * @param int $documentsReturned Number of documents returned
     */
    public function recordMongoDBQuery(
        float $queryTimeMs,
        int $documentsScanned,
        int $documentsReturned
    ): void {
        try {
            $this->recordResponseTime($queryTimeMs, 'mongodb_query');
            $this->incrementCounter('mongodb_queries_total');
            
            // Track scan efficiency
            if ($documentsScanned > 0 && $documentsReturned > 0) {
                $efficiency = ($documentsReturned / $documentsScanned) * 100;
                $this->recordValue('mongodb_scan_efficiency', $efficiency);
            }
            
            $this->logger->debug('MongoDB metrics recorded', [
                'query_time_ms' => $queryTimeMs,
                'scanned' => $documentsScanned,
                'returned' => $documentsReturned,
            ]);
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to record MongoDB metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get response time percentiles
     * 
     * @return array{p50: float, p95: float, p99: float, count: int}
     */
    public function getResponseTimeStats(string $mode = 'semantic'): array
    {
        try {
            $cacheKey = "metrics_response_times_{$mode}";
            $item = $this->cache->getItem($cacheKey);
            
            if (!$item->isHit()) {
                return ['p50' => 0, 'p95' => 0, 'p99' => 0, 'count' => 0];
            }
            
            $times = $item->get();
            if (empty($times)) {
                return ['p50' => 0, 'p95' => 0, 'p99' => 0, 'count' => 0];
            }
            
            sort($times);
            $count = count($times);
            
            return [
                'p50' => $this->percentile($times, 50),
                'p95' => $this->percentile($times, 95),
                'p99' => $this->percentile($times, 99),
                'count' => $count,
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get response time stats', [
                'mode' => $mode,
                'error' => $e->getMessage(),
            ]);
            
            return ['p50' => 0, 'p95' => 0, 'p99' => 0, 'count' => 0];
        }
    }

    /**
     * Get OpenAI API usage statistics
     * 
     * @return array{calls: int, estimated_tokens: int, estimated_cost_usd: float}
     */
    public function getOpenAIStats(): array
    {
        try {
            $calls = $this->getCounter('openai_api_calls');
            $estimatedTokens = $calls * self::AVG_TOKENS_PER_QUERY;
            $estimatedCost = ($estimatedTokens / 1_000_000) * self::EMBEDDING_COST_PER_1M_TOKENS;
            
            return [
                'calls' => $calls,
                'estimated_tokens' => $estimatedTokens,
                'estimated_cost_usd' => round($estimatedCost, 4),
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get OpenAI stats', [
                'error' => $e->getMessage(),
            ]);
            
            return ['calls' => 0, 'estimated_tokens' => 0, 'estimated_cost_usd' => 0.0];
        }
    }

    /**
     * Get cache performance statistics
     * 
     * @return array{hits: int, misses: int, hit_rate: float}
     */
    public function getCacheStats(): array
    {
        try {
            $hits = $this->getCounter('embedding_cache_hits');
            $misses = $this->getCounter('embedding_cache_misses');
            $total = $hits + $misses;
            $hitRate = $total > 0 ? ($hits / $total) * 100 : 0;
            
            return [
                'hits' => $hits,
                'misses' => $misses,
                'hit_rate' => round($hitRate, 2),
            ];
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to get cache stats', [
                'error' => $e->getMessage(),
            ]);
            
            return ['hits' => 0, 'misses' => 0, 'hit_rate' => 0.0];
        }
    }

    /**
     * Get comprehensive metrics summary
     * 
     * @return array Complete metrics dashboard data
     */
    public function getMetricsSummary(): array
    {
        return [
            'search' => [
                'total' => $this->getCounter('search_count_total'),
                'semantic' => $this->getCounter('search_count_semantic'),
                'keyword' => $this->getCounter('search_count_keyword'),
                'empty_results_semantic' => $this->getCounter('empty_search_count_semantic'),
                'empty_results_keyword' => $this->getCounter('empty_search_count_keyword'),
            ],
            'performance' => [
                'semantic' => $this->getResponseTimeStats('semantic'),
                'keyword' => $this->getResponseTimeStats('keyword'),
                'mongodb' => $this->getResponseTimeStats('mongodb_query'),
            ],
            'openai' => $this->getOpenAIStats(),
            'cache' => $this->getCacheStats(),
            'mongodb' => [
                'queries' => $this->getCounter('mongodb_queries_total'),
            ],
        ];
    }

    /**
     * Reset all metrics (for testing or maintenance)
     */
    public function resetMetrics(): void
    {
        try {
            $this->cache->clear();
            $this->logger->info('All metrics reset');
        } catch (\Exception $e) {
            $this->logger->error('Failed to reset metrics', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Record a response time sample
     */
    private function recordResponseTime(float $timeMs, string $mode): void
    {
        $cacheKey = "metrics_response_times_{$mode}";
        $item = $this->cache->getItem($cacheKey);
        
        $times = $item->isHit() ? $item->get() : [];
        $times[] = $timeMs;
        
        // Keep only last N samples for percentile calculation
        if (count($times) > self::MAX_SAMPLES) {
            $times = array_slice($times, -self::MAX_SAMPLES);
        }
        
        $item->set($times);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);
    }

    /**
     * Record an OpenAI API call
     */
    private function recordOpenAICall(): void
    {
        $this->incrementCounter('openai_api_calls');
        
        // Log hourly costs for monitoring
        $hour = date('Y-m-d-H');
        $this->incrementCounter("openai_calls_hour_{$hour}");
    }

    /**
     * Increment a counter metric
     */
    private function incrementCounter(string $name, int $amount = 1): void
    {
        $cacheKey = "metrics_counter_{$name}";
        $item = $this->cache->getItem($cacheKey);
        
        $value = $item->isHit() ? $item->get() : 0;
        $value += $amount;
        
        $item->set($value);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);
    }

    /**
     * Get a counter value
     */
    private function getCounter(string $name): int
    {
        $cacheKey = "metrics_counter_{$name}";
        $item = $this->cache->getItem($cacheKey);
        
        return $item->isHit() ? $item->get() : 0;
    }

    /**
     * Record a generic value (for non-time metrics)
     */
    private function recordValue(string $name, float $value): void
    {
        $cacheKey = "metrics_values_{$name}";
        $item = $this->cache->getItem($cacheKey);
        
        $values = $item->isHit() ? $item->get() : [];
        $values[] = $value;
        
        // Keep only last N samples
        if (count($values) > 1000) {
            $values = array_slice($values, -1000);
        }
        
        $item->set($values);
        $item->expiresAfter(self::CACHE_TTL);
        $this->cache->save($item);
    }

    /**
     * Calculate percentile from sorted array
     */
    private function percentile(array $sortedValues, int $percentile): float
    {
        $count = count($sortedValues);
        if ($count === 0) {
            return 0;
        }
        
        $index = (int) ceil(($percentile / 100) * $count) - 1;
        $index = max(0, min($index, $count - 1));
        
        return $sortedValues[$index];
    }
}
