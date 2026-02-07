<?php

declare(strict_types=1);

namespace App\Infrastructure\Controller;

use App\Domain\Repository\EmbeddingServiceInterface;
use Doctrine\DBAL\Connection;
use MongoDB\Client;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Annotation\Route;

/**
 * HealthController - Health check endpoints for monitoring
 * 
 * T087: Health check endpoint for MongoDB, Redis, and OpenAI connectivity
 */
class HealthController extends AbstractController
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ?Client $mongoClient = null,
        private readonly ?EmbeddingServiceInterface $embeddingService = null,
        private readonly ?CacheItemPoolInterface $cache = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?string $mongoDatabaseName = null
    ) {
    }

    #[Route('/health', name: 'health_check', methods: ['GET'])]
    public function check(): JsonResponse
    {
        $health = [
            'status' => 'ok',
            'symfony_version' => Kernel::VERSION,
            'php_version' => PHP_VERSION,
            'environment' => $this->getParameter('kernel.environment'),
            'database' => 'disconnected',
        ];

        try {
            $this->connection->executeQuery('SELECT 1');
            $health['database'] = 'connected';
        } catch (\Exception $e) {
            $health['status'] = 'degraded';
            $health['database_error'] = $e->getMessage();
        }

        $statusCode = $health['status'] === 'ok' ? 200 : 503;

        return $this->json($health, $statusCode);
    }
    
    /**
     * T087: Detailed health check including all semantic search dependencies
     */
    #[Route('/health/detailed', name: 'health_check_detailed', methods: ['GET'])]
    public function detailedHealth(): JsonResponse
    {
        $checks = [
            'mysql' => $this->checkMySQL(),
            'mongodb' => $this->checkMongoDB(),
            'redis' => $this->checkRedis(),
            'openai' => $this->checkOpenAI(),
        ];

        $allHealthy = !in_array('unhealthy', array_column($checks, 'status'), true);

        return new JsonResponse([
            'status' => $allHealthy ? 'healthy' : 'degraded',
            'timestamp' => time(),
            'checks' => $checks,
        ], $allHealthy ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * Readiness probe - returns 200 when app is ready to serve traffic
     */
    #[Route('/health/ready', name: 'health_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        // Check only critical dependencies for readiness
        $mysqlHealthy = $this->checkMySQL()['status'] === 'healthy';
        $mongoHealthy = $this->mongoClient !== null ? $this->checkMongoDB()['status'] === 'healthy' : true;
        $redisHealthy = $this->cache !== null ? $this->checkRedis()['status'] === 'healthy' : true;

        $ready = $mysqlHealthy && $mongoHealthy && $redisHealthy;

        return new JsonResponse([
            'ready' => $ready,
            'timestamp' => time(),
        ], $ready ? Response::HTTP_OK : Response::HTTP_SERVICE_UNAVAILABLE);
    }

    /**
     * Liveness probe - returns 200 if app is alive (not deadlocked)
     */
    #[Route('/health/live', name: 'health_live', methods: ['GET'])]
    public function live(): JsonResponse
    {
        return new JsonResponse([
            'alive' => true,
            'timestamp' => time(),
        ]);
    }

    private function checkMySQL(): array
    {
        try {
            $startTime = microtime(true);
            $this->connection->executeQuery('SELECT 1');
            $responseTime = (microtime(true) - $startTime) * 1000;

            return [
                'service' => 'mysql',
                'status' => 'healthy',
                'response_time_ms' => round($responseTime, 2),
            ];
        } catch (\Exception $e) {
            return [
                'service' => 'mysql',
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkMongoDB(): array
    {
        if ($this->mongoClient === null || $this->mongoDatabaseName === null) {
            return [
                'service' => 'mongodb',
                'status' => 'not_configured',
            ];
        }

        try {
            $startTime = microtime(true);
            
            $database = $this->mongoClient->selectDatabase($this->mongoDatabaseName);
            $result = $database->command(['ping' => 1]);
            
            $responseTime = (microtime(true) - $startTime) * 1000;

            $collections = iterator_to_array($database->listCollections());
            $hasEmbeddings = !empty(array_filter($collections, fn($c) => $c->getName() === 'product_embeddings'));

            return [
                'service' => 'mongodb',
                'status' => 'healthy',
                'response_time_ms' => round($responseTime, 2),
                'database' => $this->mongoDatabaseName,
                'has_embeddings_collection' => $hasEmbeddings,
            ];

        } catch (\Exception $e) {
            $this->logger?->error('MongoDB health check failed', ['error' => $e->getMessage()]);

            return [
                'service' => 'mongodb',
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkRedis(): array
    {
        if ($this->cache === null) {
            return [
                'service' => 'redis',
                'status' => 'not_configured',
            ];
        }

        try {
            $startTime = microtime(true);
            
            $testKey = 'health_check_' . uniqid();
            $testValue = ['timestamp' => time()];
            
            $item = $this->cache->getItem($testKey);
            $item->set($testValue);
            $item->expiresAfter(5);
            $this->cache->save($item);
            
            $readItem = $this->cache->getItem($testKey);
            $readValue = $readItem->isHit() ? $readItem->get() : null;
            
            $this->cache->deleteItem($testKey);
            
            $responseTime = (microtime(true) - $startTime) * 1000;

            if ($readValue !== $testValue) {
                throw new \RuntimeException('Cache read/write mismatch');
            }

            return [
                'service' => 'redis',
                'status' => 'healthy',
                'response_time_ms' => round($responseTime, 2),
            ];

        } catch (\Exception $e) {
            $this->logger?->error('Redis health check failed', ['error' => $e->getMessage()]);

            return [
                'service' => 'redis',
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkOpenAI(): array
    {
        if ($this->embeddingService === null || $this->cache === null) {
            return [
                'service' => 'openai',
                'status' => 'not_configured',
            ];
        }

        try {
            // Check circuit breaker state
            $cbItem = $this->cache->getItem('openai_circuit_breaker');
            
            if ($cbItem->isHit()) {
                $cbState = $cbItem->get();
                if ($cbState['is_open'] ?? false) {
                    return [
                        'service' => 'openai',
                        'status' => 'degraded',
                        'message' => 'Circuit breaker open',
                    ];
                }
            }

            // Don't call API in health check (too slow/costly)
            $modelName = $this->embeddingService->getModelName();

            return [
                'service' => 'openai',
                'status' => 'healthy',
                'model' => $modelName,
                'note' => 'Configuration check only (no API call)',
            ];

        } catch (\Exception $e) {
            $this->logger?->error('OpenAI health check failed', ['error' => $e->getMessage()]);

            return [
                'service' => 'openai',
                'status' => 'unhealthy',
                'error' => $e->getMessage(),
            ];
        }
    }
}
