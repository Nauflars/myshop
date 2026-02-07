<?php

declare(strict_types=1);

namespace App\Tests\Integration\Search;

use App\Application\Service\EmbeddingCacheService;
use App\Application\Service\SemanticSearchService;
use App\Domain\ValueObject\SearchQuery;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Integration tests for embedding cache behavior in semantic search
 * 
 * Tests spec-010 T062: Cache integration in search workflow
 */
class EmbeddingCacheIntegrationTest extends KernelTestCase
{
    private EmbeddingCacheService $cacheService;
    private SemanticSearchService $semanticSearchService;

    protected function setUp(): void
    {
        self::bootKernel();

        $container = static::getContainer();
        $this->cacheService = $container->get(EmbeddingCacheService::class);
        $this->semanticSearchService = $container->get(SemanticSearchService::class);

        // Clear cache before each test
        $this->cacheService->clear();
    }

    protected function tearDown(): void
    {
        // Clear cache after tests
        $this->cacheService->clear();
        
        parent::tearDown();
    }

    public function testFirstSearchGeneratesEmbeddingAndCachesIt(): void
    {
        // Arrange
        $query = 'unique_test_query_' . time();
        
        // Verify cache is empty
        $cachedBefore = $this->cacheService->get($query);
        $this->assertNull($cachedBefore, 'Cache should be empty before first search');

        // Act - Generate embedding (will be cached)
        $embedding = $this->semanticSearchService->generateQueryEmbedding($query);

        // Assert
        $this->assertIsArray($embedding, 'Should return embedding array');
        $this->assertCount(1536, $embedding, 'Embedding should have 1536 dimensions');

        // Verify embedding is now cached
        $cachedAfter = $this->cacheService->get($query);
        $this->assertNotNull($cachedAfter, 'Embedding should be cached after first call');
        $this->assertEquals($embedding, $cachedAfter, 'Cached embedding should match generated one');
    }

    public function testSecondSearchUsesCachedEmbedding(): void
    {
        // Arrange
        $query = 'repeated_query_' . time();

        // Act - First call generates and caches
        $embedding1 = $this->semanticSearchService->generateQueryEmbedding($query);
        
        // Get cache statistics after first call
        $statsAfterFirst = $this->cacheService->getStats();
        $missesAfterFirst = $statsAfterFirst['misses'];

        // Second call should use cache
        $embedding2 = $this->semanticSearchService->generateQueryEmbedding($query);
        
        // Get cache statistics after second call
        $statsAfterSecond = $this->cacheService->getStats();

        // Assert
        $this->assertEquals($embedding1, $embedding2, 'Both calls should return same embedding');
        $this->assertGreaterThan(
            $missesAfterFirst, 
            $statsAfterSecond['hits'], 
            'Second call should register as cache hit'
        );
    }

    public function testCacheReducesOpenAIApiCalls(): void
    {
        // Arrange
        $query = 'cache_test_' . time();
        
        // Clear cache stats
        $this->cacheService->clear();

        // Act - Perform same search 5 times
        for ($i = 0; $i < 5; $i++) {
            $embedding = $this->semanticSearchService->generateQueryEmbedding($query);
            $this->assertCount(1536, $embedding);
        }

        // Get statistics
        $stats = $this->cacheService->getStats();

        // Assert
        $this->assertEquals(1, $stats['misses'], 'Should have exactly 1 cache miss (first call)');
        $this->assertEquals(4, $stats['hits'], 'Should have 4 cache hits (subsequent calls)');
        $this->assertEquals(80.0, $stats['hit_rate'], 'Hit rate should be 80%');
    }

    public function testDifferentQueriesHaveSeparateCacheEntries(): void
    {
        // Arrange
        $query1 = 'laptop for gaming';
        $query2 = 'smartphone for photography';

        // Act
        $embedding1 = $this->semanticSearchService->generateQueryEmbedding($query1);
        $embedding2 = $this->semanticSearchService->generateQueryEmbedding($query2);

        // Assert
        $this->assertNotEquals($embedding1, $embedding2, 'Different queries should have different embeddings');
        
        // Verify both are cached independently
        $cached1 = $this->cacheService->get($query1);
        $cached2 = $this->cacheService->get($query2);
        
        $this->assertEquals($embedding1, $cached1, 'First query should be cached');
        $this->assertEquals($embedding2, $cached2, 'Second query should be cached');
    }

    public function testCacheNormalizesQueryCase(): void
    {
        // Arrange
        $query1 = 'Laptop';
        $query2 = 'laptop';
        $query3 = 'LAPTOP';

        // Act - First call caches the embedding
        $embedding1 = $this->semanticSearchService->generateQueryEmbedding($query1);
        
        // Subsequent calls with different case should hit cache
        $embedding2 = $this->semanticSearchService->generateQueryEmbedding($query2);
        $embedding3 = $this->semanticSearchService->generateQueryEmbedding($query3);

        // Assert - All should return same cached embedding
        $this->assertEquals($embedding1, $embedding2, 'Lowercase should hit same cache');
        $this->assertEquals($embedding1, $embedding3, 'Uppercase should hit same cache');
        
        // Verify only one cache miss (first call)
        $stats = $this->cacheService->getStats();
        $this->assertGreaterThanOrEqual(1, $stats['misses'], 'Should have at least 1 miss');
        $this->assertGreaterThanOrEqual(2, $stats['hits'], 'Should have at least 2 hits');
    }

    public function testCacheHandlesWhitespaceNormalization(): void
    {
        // Arrange
        $query1 = '  laptop  ';
        $query2 = 'laptop';

        // Act
        $embedding1 = $this->semanticSearchService->generateQueryEmbedding($query1);
        $embedding2 = $this->semanticSearchService->generateQueryEmbedding($query2);

        // Assert - Should use same cache entry
        $this->assertEquals($embedding1, $embedding2, 'Whitespace should not affect caching');
    }

    public function testClearCacheRemovesAllEntries(): void
    {
        // Arrange - Cache multiple queries
        $queries = ['laptop', 'smartphone', 'tablet', 'headphones'];
        
        foreach ($queries as $query) {
            $this->semanticSearchService->generateQueryEmbedding($query);
        }

        // Verify all are cached
        foreach ($queries as $query) {
            $this->assertNotNull(
                $this->cacheService->get($query), 
                "Query '{$query}' should be cached"
            );
        }

        // Act - Clear cache
        $result = $this->cacheService->clear();

        // Assert
        $this->assertTrue($result, 'Cache clear should succeed');
        
        // Verify all entries are removed
        foreach ($queries as $query) {
            $this->assertNull(
                $this->cacheService->get($query), 
                "Query '{$query}' should not be cached after clear"
            );
        }
        
        // Verify statistics are reset
        $stats = $this->cacheService->getStats();
        $this->assertEquals(0, $stats['hits'], 'Hits should be reset to 0');
        $this->assertEquals(count($queries), $stats['misses'], 'Should count misses after clear');
    }

    public function testDeleteRemovesSpecificCacheEntry(): void
    {
        // Arrange - Cache two queries
        $query1 = 'laptop';
        $query2 = 'smartphone';
        
        $this->semanticSearchService->generateQueryEmbedding($query1);
        $this->semanticSearchService->generateQueryEmbedding($query2);

        // Act - Delete only first query
        $result = $this->cacheService->delete($query1);

        // Assert
        $this->assertTrue($result, 'Delete should succeed');
        $this->assertNull($this->cacheService->get($query1), 'Deleted entry should not be cached');
        $this->assertNotNull($this->cacheService->get($query2), 'Other entries should remain cached');
    }

    public function testCacheHandlesVeryLongQueries(): void
    {
        // Arrange - Create long query (within SearchQuery limit of 500 chars)
        $longQuery = str_repeat('laptop gaming performance ', 15); // ~375 chars
        $longQuery = substr($longQuery, 0, 450); // Trim to valid length

        // Act - Should cache successfully
        $embedding1 = $this->semanticSearchService->generateQueryEmbedding($longQuery);
        $embedding2 = $this->semanticSearchService->generateQueryEmbedding($longQuery);

        // Assert
        $this->assertEquals($embedding1, $embedding2, 'Long queries should be cached correctly');
        $this->assertCount(1536, $embedding1, 'Embedding dimensions should be correct');
    }

    public function testCacheHandlesSpecialCharacters(): void
    {
        // Arrange
        $query = 'laptop & gaming "pro" +fast';

        // Act
        $embedding1 = $this->semanticSearchService->generateQueryEmbedding($query);
        $embedding2 = $this->semanticSearchService->generateQueryEmbedding($query);

        // Assert
        $this->assertEquals($embedding1, $embedding2, 'Special characters should not break caching');
    }

    public function testCacheMissMetricsIncreaseCorrectly(): void
    {
        // Arrange
        $this->cacheService->clear(); // Reset statistics
        
        $queries = [
            'query1_' . time(),
            'query2_' . time(),
            'query3_' . time(),
        ];

        // Act - Generate embeddings for unique queries (all will miss)
        foreach ($queries as $query) {
            $this->semanticSearchService->generateQueryEmbedding($query);
        }

        // Assert
        $stats = $this->cacheService->getStats();
        $this->assertEquals(count($queries), $stats['misses'], 'Each new query should count as cache miss');
        $this->assertEquals(0, $stats['hits'], 'No cache hits for new queries');
        $this->assertEquals(0.0, $stats['hit_rate'], 'Hit rate should be 0% for all misses');
    }

    public function testCacheHitRateCalculation(): void
    {
        // Arrange
        $this->cacheService->clear();
        $query1 = 'test_query_' . time();
        $query2 = 'other_query_' . time();

        // Act - 1 miss (query1 first time), 3 hits (query1 repeated), 1 miss (query2)
        $this->semanticSearchService->generateQueryEmbedding($query1); // Miss
        $this->semanticSearchService->generateQueryEmbedding($query1); // Hit
        $this->semanticSearchService->generateQueryEmbedding($query1); // Hit
        $this->semanticSearchService->generateQueryEmbedding($query1); // Hit
        $this->semanticSearchService->generateQueryEmbedding($query2); // Miss

        // Assert
        $stats = $this->cacheService->getStats();
        $this->assertEquals(3, $stats['hits'], 'Should have 3 hits');
        $this->assertEquals(2, $stats['misses'], 'Should have 2 misses');
        $this->assertEquals(60.0, $stats['hit_rate'], 'Hit rate should be 60% (3/5)');
    }
}
