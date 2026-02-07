<?php

declare(strict_types=1);

namespace App\Application\Service;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;

/**
 * T096: High Failure Rate Alerting
 * 
 * Monitors embedding sync failure rates and triggers alerts
 * when threshold is exceeded (>10% failures in 5 minutes)
 * 
 * Uses sliding window counter in Redis cache
 */
class FailureRateMonitor
{
    private const WINDOW_SECONDS = 300; // 5 minutes
    private const THRESHOLD_PERCENT = 10.0; // 10%
    private const CACHE_KEY_SUCCESS = 'embedding_sync_success_count';
    private const CACHE_KEY_FAILURE = 'embedding_sync_failure_count';
    private const CACHE_KEY_LAST_ALERT = 'embedding_sync_last_alert';
    private const ALERT_COOLDOWN_SECONDS = 900; // 15 minutes between alerts

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Record a successful embedding sync
     */
    public function recordSuccess(): void
    {
        $this->incrementCounter(self::CACHE_KEY_SUCCESS);
    }

    /**
     * Record a failed embedding sync
     * Triggers alert if failure rate exceeds threshold
     */
    public function recordFailure(string $productId, string $operation, \Throwable $error): void
    {
        $this->incrementCounter(self::CACHE_KEY_FAILURE);

        // Check if alert should be triggered
        $stats = $this->getStatistics();

        if ($stats['failure_rate'] > self::THRESHOLD_PERCENT) {
            $this->triggerAlert($stats, $productId, $operation, $error);
        }
    }

    /**
     * Get failure rate statistics
     * 
     * @return array ['success_count' => int, 'failure_count' => int, 'total_count' => int, 'failure_rate' => float]
     */
    public function getStatistics(): array
    {
        $successCount = $this->getCounter(self::CACHE_KEY_SUCCESS);
        $failureCount = $this->getCounter(self::CACHE_KEY_FAILURE);
        $totalCount = $successCount + $failureCount;

        $failureRate = $totalCount > 0 
            ? ($failureCount / $totalCount) * 100 
            : 0.0;

        return [
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'total_count' => $totalCount,
            'failure_rate' => round($failureRate, 2),
            'window_seconds' => self::WINDOW_SECONDS,
            'threshold_percent' => self::THRESHOLD_PERCENT,
        ];
    }

    /**
     * Reset failure rate counters (for testing or manual intervention)
     */
    public function reset(): void
    {
        try {
            $this->cache->deleteItem(self::CACHE_KEY_SUCCESS);
            $this->cache->deleteItem(self::CACHE_KEY_FAILURE);
            $this->cache->deleteItem(self::CACHE_KEY_LAST_ALERT);

            $this->logger->info('Failure rate monitor counters reset');

        } catch (\Exception $e) {
            $this->logger->error('Failed to reset failure rate counters', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Increment a counter in cache with sliding window
     */
    private function incrementCounter(string $cacheKey): void
    {
        try {
            $item = $this->cache->getItem($cacheKey);
            $count = $item->isHit() ? (int) $item->get() : 0;

            $count++;

            $item->set($count);
            $item->expiresAfter(self::WINDOW_SECONDS);
            $this->cache->save($item);

        } catch (\Exception $e) {
            $this->logger->error('Failed to increment counter', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get counter value from cache
     */
    private function getCounter(string $cacheKey): int
    {
        try {
            $item = $this->cache->getItem($cacheKey);
            return $item->isHit() ? (int) $item->get() : 0;

        } catch (\Exception $e) {
            $this->logger->error('Failed to get counter', [
                'cache_key' => $cacheKey,
                'error' => $e->getMessage(),
            ]);

            return 0;
        }
    }

    /**
     * Trigger critical alert for high failure rate
     * Implements cooldown to prevent alert spam
     */
    private function triggerAlert(array $stats, string $productId, string $operation, \Throwable $error): void
    {
        // Check alert cooldown
        try {
            $lastAlertItem = $this->cache->getItem(self::CACHE_KEY_LAST_ALERT);
            
            if ($lastAlertItem->isHit()) {
                $lastAlertTime = $lastAlertItem->get();
                $elapsed = time() - $lastAlertTime;

                if ($elapsed < self::ALERT_COOLDOWN_SECONDS) {
                    // Still in cooldown, don't trigger another alert
                    return;
                }
            }

            // Log critical alert
            $this->logger->critical('HIGH FAILURE RATE ALERT: Embedding sync failing at critical rate', [
                'failure_rate' => $stats['failure_rate'] . '%',
                'threshold' => self::THRESHOLD_PERCENT . '%',
                'total_operations' => $stats['total_count'],
                'failed_operations' => $stats['failure_count'],
                'window_seconds' => self::WINDOW_SECONDS,
                'recent_failure' => [
                    'product_id' => $productId,
                    'operation' => $operation,
                    'error_type' => get_class($error),
                    'error_message' => $error->getMessage(),
                ],
                'recommendation' => 'Check MongoDB and OpenAI service health, review circuit breaker status, examine recent error logs',
            ]);

            // Update last alert timestamp
            $lastAlertItem->set(time());
            $lastAlertItem->expiresAfter(self::ALERT_COOLDOWN_SECONDS);
            $this->cache->save($lastAlertItem);

        } catch (\Exception $e) {
            $this->logger->error('Failed to trigger alert', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
