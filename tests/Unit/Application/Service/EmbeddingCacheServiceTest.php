<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Service\EmbeddingCacheService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Unit tests for EmbeddingCacheService
 * 
 * Tests spec-010 T061: Cache service functionality
 */
class EmbeddingCacheServiceTest extends TestCase
{
    private CacheInterface $cache;
    private LoggerInterface $logger;
    private EmbeddingCacheService $cacheService;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->cacheService = new EmbeddingCacheService(
            $this->cache,
            $this->logger,
            '3600' // 1 hour TTL
        );
    }

    public function testGetReturnsNullOnCacheMiss(): void
    {
        // Arrange
        $query = 'test query';
        
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(null);

        // Act
        $result = $this->cacheService->get($query);

        // Assert
        $this->assertNull($result, 'Should return null on cache miss');
    }

    public function testGetReturnsCachedEmbeddingOnHit(): void
    {
        // Arrange
        $query = 'laptop for gaming';
        $embedding = array_fill(0, 1536, 0.5); // Mock 1536-dimension embedding
        $cachedJson = json_encode($embedding);
        
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($cachedJson);

        // Act
        $result = $this->cacheService->get($query);

        // Assert
        $this->assertIsArray($result);
        $this->assertCount(1536, $result);
        $this->assertEquals($embedding, $result);
    }

    public function testSetStoresEmbeddingSuccessfully(): void
    {
        // Arrange
        $query = 'test product';
        $embedding = array_fill(0, 1536, 0.75);
        
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(json_encode($embedding));

        // Act
        $result = $this->cacheService->set($query, $embedding);

        // Assert
        $this->assertTrue($result, 'Should return true on successful cache write');
    }

    public function testSetRejectsInvalidDimensions(): void
    {
        // Arrange
        $query = 'test query';
        $invalidEmbedding = array_fill(0, 100, 0.5); // Wrong dimensions
        
        // Expect no cache write
        $this->cache->expects($this->never())
            ->method('get');

        // Act
        $result = $this->cacheService->set($query, $invalidEmbedding);

        // Assert
        $this->assertFalse($result, 'Should return false for invalid embedding dimensions');
    }

    public function testDeleteRemovesCacheEntry(): void
    {
        // Arrange
        $query = 'test query';
        
        $this->cache->expects($this->once())
            ->method('delete')
            ->willReturn(true);

        // Act
        $result = $this->cacheService->delete($query);

        // Assert
        $this->assertTrue($result, 'Should return true on successful deletion');
    }

    public function testClearRemovesAllEntries(): void
    {
        // Arrange
        $this->cache->expects($this->once())
            ->method('clear')
            ->willReturn(true);

        // Act
        $result = $this->cacheService->clear();

        // Assert
        $this->assertTrue($result, 'Should return true on successful clear');
    }

    public function testGetStatsTracksHitsAndMisses(): void
    {
        // Arrange - Simulate cache miss then hit
        $query1 = 'first query';
        $query2 = 'second query';
        $embedding = array_fill(0, 1536, 0.5);
        
        // First call: cache miss
        $this->cache->expects($this->at(0))
            ->method('get')
            ->willReturn(null);
        
        // Second call: cache hit
        $this->cache->expects($this->at(1))
            ->method('get')
            ->willReturn(json_encode($embedding));

        // Act
        $this->cacheService->get($query1); // Miss
        $this->cacheService->get($query2); // Hit
        
        $stats = $this->cacheService->getStats();

        // Assert
        $this->assertEquals(1, $stats['hits'], 'Should track cache hits');
        $this->assertEquals(1, $stats['misses'], 'Should track cache misses');
        $this->assertEquals(50.0, $stats['hit_rate'], 'Hit rate should be 50%');
    }

    public function testGetHandlesRedisConnectionError(): void
    {
        // Arrange
        $query = 'test query';
        
        $this->cache->expects($this->once())
            ->method('get')
            ->willThrowException(new \RuntimeException('Redis connection failed'));

        // Act
        $result = $this->cacheService->get($query);

        // Assert
        $this->assertNull($result, 'Should return null on Redis error (graceful degradation)');
    }

    public function testSetHandlesRedisConnectionError(): void
    {
        // Arrange
        $query = 'test query';
        $embedding = array_fill(0, 1536, 0.5);
        
        $this->cache->expects($this->once())
            ->method('get')
            ->willThrowException(new \RuntimeException('Redis write failed'));

       // Act
        $result = $this->cacheService->set($query, $embedding);

        // Assert
        $this->assertFalse($result, 'Should return false on Redis error');
    }

    public function testCacheKeyGenerationIsConsistent(): void
    {
        // Arrange
        $query1 = 'laptop';
        $query2 = 'LAPTOP'; // Different case
        $query3 = '  laptop  '; // Extra whitespace
        
        $embedding = array_fill(0, 1536, 0.5);
        $cachedJson = json_encode($embedding);
        
        // All three should generate the same cache key (normalized)
        $this->cache->expects($this->exactly(3))
            ->method('get')
            ->willReturn($cachedJson);

        // Act
        $result1 = $this->cacheService->get($query1);
        $result2 = $this->cacheService->get($query2);
        $result3 = $this->cacheService->get($query3);

        // Assert
        $this->assertEquals($result1, $result2, 'Case should not affect cache key');
        $this->assertEquals($result1, $result3, 'Whitespace should not affect cache key');
    }

    public function testSerializationPreservesEmbeddingValues(): void
    {
        // Arrange
        $query = 'test';
        $embedding = [];
        for ($i = 0; $i < 1536; $i++) {
            $embedding[] = $i / 1536.0; // Generate unique values
        }
        
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturnCallback(function ($key, $callback) use ($embedding) {
                // Simulate cache write and read
                $item = $this->createMock(ItemInterface::class);
                $serialized = $callback($item);
                return $serialized;
            });

        // Act
        $this->cacheService->set($query, $embedding);
        
        // Simulate retrieval
        $this->cache = $this->createMock(CacheInterface::class);
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn(json_encode($embedding));
        
        $this->cacheService = new EmbeddingCacheService(
            $this->cache,
            $this->logger,
            '3600'
        );
        
        $retrieved = $this->cacheService->get($query);

        // Assert
        $this->assertEquals($embedding, $retrieved, 'Serialization should preserve embedding values');
    }

    public function testGetRejectsCorruptedCacheData(): void
    {
        // Arrange
        $query = 'test';
        $corruptedJson = '{invalid json';
        
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($corruptedJson);

        // Act
        $result = $this->cacheService->get($query);

        // Assert
        $this->assertNull($result, 'Should return null for corrupted cache data');
    }

    public function testGetRejectsWrongDimensionsInCache(): void
    {
        // Arrange
        $query = 'test';
        $wrongDimensions = array_fill(0, 512, 0.5); // Wrong size
        $cachedJson = json_encode($wrongDimensions);
        
        $this->cache->expects($this->once())
            ->method('get')
            ->willReturn($cachedJson);

        // Act
        $result = $this->cacheService->get($query);

        // Assert
        $this->assertNull($result, 'Should return null for wrong dimensions in cache');
    }
}
