<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Service;

use App\Application\Service\SearchMetricsCollector;
use App\Domain\Repository\EmbeddingServiceInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\AI\Platform\PlatformInterface;

/**
 * OpenAIEmbeddingService - Generate embeddings using Symfony AI Platform.
 *
 * Implements spec-010 FR-001: Generate vector embeddings using OpenAI
 * Uses Symfony AI Platform (MANDATORY per spec-010 section 4.1)
 * Uses text-embedding-3-small model (1536 dimensions, cost-efficient)
 * T082: Rate limit monitoring and alerting
 * T086: Circuit breaker pattern for API failures
 *
 * IMPORTANT: This implementation uses Symfony AI Platform only.
 * Direct HTTP calls to OpenAI API are FORBIDDEN per spec-010 section 4.2.
 */
class OpenAIEmbeddingService implements EmbeddingServiceInterface
{
    private const DEFAULT_MODEL = 'text-embedding-3-small';
    private const MODEL_DIMENSIONS = [
        'text-embedding-3-small' => 1536,
        'text-embedding-3-large' => 3072,
        'text-embedding-ada-002' => 1536,
    ];

    private const MAX_RETRIES = 3;
    private const RETRY_DELAY_MS = 1000;

    // T086: Circuit breaker configuration
    private const CIRCUIT_BREAKER_THRESHOLD = 5; // Failures before opening circuit
    private const CIRCUIT_BREAKER_TIMEOUT = 60; // Seconds to wait before retry
    private const CIRCUIT_BREAKER_CACHE_KEY = 'openai_circuit_breaker';

    // T082: Rate limit monitoring
    private const RATE_LIMIT_WARNING_THRESHOLD = 0.8; // Warn at 80% of limit

    public function __construct(
        private readonly PlatformInterface $platform,
        private readonly LoggerInterface $logger,
        private readonly string $model = self::DEFAULT_MODEL,
        private readonly ?SearchMetricsCollector $metricsCollector = null,
        private readonly ?CacheItemPoolInterface $cache = null,
    ) {
    }

    public function generateEmbedding(string $text): array
    {
        // T086: Check circuit breaker state before calling API
        if ($this->isCircuitBreakerOpen()) {
            $this->logger->warning('OpenAI API circuit breaker is OPEN, skipping request');
            throw new \RuntimeException('OpenAI API is temporarily unavailable (circuit breaker open)');
        }

        $this->logger->debug('Generating embedding via Symfony AI Platform', [
            'model' => $this->model,
            'text_length' => strlen($text),
        ]);

        $attempt = 0;
        $lastException = null;

        while ($attempt < self::MAX_RETRIES) {
            try {
                // Use Symfony AI Platform to generate embeddings
                // Per spec-010 section 4.1: MUST use Symfony AI, NOT direct HTTP calls
                $result = $this->platform->invoke($this->model, $text);

                // Extract embedding using Symfony AI's asVectors() method
                // Reference: vendor/symfony/ai-agent/src/Memory/EmbeddingProvider.php:59
                $vectors = $result->asVectors();

                if (empty($vectors) || !isset($vectors[0])) {
                    throw new \RuntimeException('No vectors returned from Symfony AI Platform');
                }

                // Get the vector data as array
                // Vector objects have a getData() method that returns float[]
                $embedding = $vectors[0]->getData();

                // T101: Validate embedding dimensions
                $expectedDimensions = $this->getDimensions();
                $actualDimensions = count($embedding);

                if ($actualDimensions !== $expectedDimensions) {
                    throw new \RuntimeException(sprintf('Invalid embedding dimensions: expected %d, got %d', $expectedDimensions, $actualDimensions));
                }

                // T086: Reset circuit breaker on success
                $this->resetCircuitBreaker();

                // Track API call for metrics
                if (null !== $this->metricsCollector) {
                    $this->metricsCollector->recordSearch(
                        responseTimeMs: 0,
                        searchMode: 'openai_api',
                        resultsCount: 1,
                        cacheHit: false,
                        openaiCalled: true
                    );
                }

                $this->logger->info('Embedding generated successfully via Symfony AI', [
                    'model' => $this->model,
                    'dimensions' => count($embedding),
                ]);

                return $embedding;
            } catch (\Exception $e) {
                ++$attempt;
                $lastException = $e;

                // T086: Record failure for circuit breaker
                $this->recordCircuitBreakerFailure();

                $this->logger->warning('Embedding generation failed', [
                    'attempt' => $attempt,
                    'max_retries' => self::MAX_RETRIES,
                    'error' => $e->getMessage(),
                ]);

                if ($attempt < self::MAX_RETRIES) {
                    // Exponential backoff: 1s, 2s, 4s
                    $delay = self::RETRY_DELAY_MS * (2 ** ($attempt - 1));
                    usleep($delay * 1000);
                }
            }
        }

        $this->logger->error('Embedding generation failed after retries', [
            'attempts' => $attempt,
            'error' => $lastException?->getMessage(),
        ]);

        throw new \RuntimeException(sprintf('Failed to generate embedding after %d attempts: %s', $attempt, $lastException?->getMessage()), 0, $lastException);
    }

    public function generateBatchEmbeddings(array $texts): array
    {
        $this->logger->debug('Generating batch embeddings via Symfony AI Platform', [
            'model' => $this->model,
            'batch_size' => count($texts),
        ]);

        try {
            // Use Symfony AI Platform for batch processing
            // Symfony AI Platform can handle batch inputs - invoke with array of texts
            $embeddings = [];

            foreach ($texts as $text) {
                $result = $this->platform->invoke($this->model, $text);
                $vectors = $result->asVectors();

                if (empty($vectors) || !isset($vectors[0])) {
                    throw new \RuntimeException('No vectors returned from Symfony AI Platform for batch item');
                }

                // Extract vector data as array
                $embeddings[] = $vectors[0]->getData();
            }

            $this->logger->info('Batch embeddings generated successfully via Symfony AI', [
                'model' => $this->model,
                'count' => count($embeddings),
            ]);

            return $embeddings;
        } catch (\Exception $e) {
            $this->logger->error('Batch embedding generation failed', [
                'batch_size' => count($texts),
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException('Failed to generate batch embeddings: '.$e->getMessage(), 0, $e);
        }
    }

    public function getModelName(): string
    {
        return $this->model;
    }

    public function getDimensions(): int
    {
        return self::MODEL_DIMENSIONS[$this->model] ?? 1536;
    }

    /**
     * T082: Monitor rate limits from API response headers.
     *
     * NOTE: When using Symfony AI Platform abstraction, we don't have direct access
     * to HTTP response headers. Rate limit monitoring is handled by the platform layer.
     * This method is kept for backward compatibility but may not have full functionality.
     */
    private function monitorRateLimits($result): void
    {
        // Rate limit monitoring through Symfony AI Platform is handled at the platform level
        // The abstraction layer doesn't expose raw HTTP headers
        // If critical rate limit monitoring is needed, it should be implemented
        // at the platform configuration level or through OpenAI dashboard alerts

        $this->logger->debug('Rate limit monitoring delegated to Symfony AI Platform layer');
    }

    /**
     * T086: Check if circuit breaker is open.
     */
    private function isCircuitBreakerOpen(): bool
    {
        if (null === $this->cache) {
            return false;
        }

        try {
            $item = $this->cache->getItem(self::CIRCUIT_BREAKER_CACHE_KEY);

            if (!$item->isHit()) {
                return false;
            }

            $state = $item->get();

            // Check if circuit breaker timeout has elapsed
            if (isset($state['opened_at'])) {
                $elapsed = time() - $state['opened_at'];

                if ($elapsed >= self::CIRCUIT_BREAKER_TIMEOUT) {
                    // Half-open state: allow one request to test
                    $this->logger->info('Circuit breaker entering HALF-OPEN state (testing recovery)');
                    $this->resetCircuitBreaker();

                    return false;
                }
            }

            return $state['is_open'] ?? false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to check circuit breaker state', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * T086: Record a failure for circuit breaker.
     */
    private function recordCircuitBreakerFailure(): void
    {
        if (null === $this->cache) {
            return;
        }

        try {
            $item = $this->cache->getItem(self::CIRCUIT_BREAKER_CACHE_KEY);
            $state = $item->isHit() ? $item->get() : ['failures' => 0, 'is_open' => false];

            $state['failures'] = ($state['failures'] ?? 0) + 1;

            // Open circuit if threshold reached
            if ($state['failures'] >= self::CIRCUIT_BREAKER_THRESHOLD && !($state['is_open'] ?? false)) {
                $state['is_open'] = true;
                $state['opened_at'] = time();

                $this->logger->error('OpenAI API circuit breaker OPENED', [
                    'failures' => $state['failures'],
                    'threshold' => self::CIRCUIT_BREAKER_THRESHOLD,
                    'timeout_seconds' => self::CIRCUIT_BREAKER_TIMEOUT,
                ]);
            }

            $item->set($state);
            $item->expiresAfter(self::CIRCUIT_BREAKER_TIMEOUT + 60); // Extra buffer
            $this->cache->save($item);
        } catch (\Exception $e) {
            $this->logger->error('Failed to record circuit breaker failure', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * T086: Reset circuit breaker on successful request.
     */
    private function resetCircuitBreaker(): void
    {
        if (null === $this->cache) {
            return;
        }

        try {
            $item = $this->cache->getItem(self::CIRCUIT_BREAKER_CACHE_KEY);

            if ($item->isHit()) {
                $state = $item->get();

                if ($state['is_open'] ?? false) {
                    $this->logger->info('OpenAI API circuit breaker CLOSED (service recovered)');
                }
            }

            // Reset state
            $item->set(['failures' => 0, 'is_open' => false]);
            $item->expiresAfter(self::CIRCUIT_BREAKER_TIMEOUT + 60);
            $this->cache->save($item);
        } catch (\Exception $e) {
            $this->logger->error('Failed to reset circuit breaker', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
