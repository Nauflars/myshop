<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\SearchQuery;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SearchQuery value object
 * 
 * Tests validation rules, getters, and edge cases
 * Implements spec-010 T105
 */
class SearchQueryTest extends TestCase
{
    public function testCreateWithValidQuery(): void
    {
        $query = new SearchQuery(
            query: 'laptop',
            limit: 10,
            offset: 0,
            minSimilarity: 0.7,
            category: 'electronics'
        );

        $this->assertSame('laptop', $query->getQuery());
        $this->assertSame(10, $query->getLimit());
        $this->assertSame(0, $query->getOffset());
        $this->assertSame(0.7, $query->getMinSimilarity());
        $this->assertSame('electronics', $query->getCategory());
        $this->assertTrue($query->hasCategory());
    }

    public function testCreateWithDefaults(): void
    {
        $query = new SearchQuery('laptop');

        $this->assertSame('laptop', $query->getQuery());
        $this->assertSame(10, $query->getLimit());
        $this->assertSame(0, $query->getOffset());
      $this->assertSame(0.6, $query->getMinSimilarity());
        $this->assertNull($query->getCategory());
        $this->assertFalse($query->hasCategory());
    }

    public function testCreateWithoutCategory(): void
    {
        $query = new SearchQuery('laptop', category: null);

        $this->assertNull($query->getCategory());
        $this->assertFalse($query->hasCategory());
    }

    public function testHasCategoryWithEmptyString(): void
    {
        $query = new SearchQuery('laptop', category: '');

        $this->assertFalse($query->hasCategory());
    }

    public function testQueryTooShortThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Query must be at least 2 characters long');

        new SearchQuery('a');
    }

    public function testQueryTooLongThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Query must not exceed 500 characters');

        new SearchQuery(str_repeat('a', 501));
    }

    public function testQueryWithWhitespaceOnlyThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Query must be at least 2 characters long');

        new SearchQuery('   ');
    }

    public function testLimitTooSmallThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100');

        new SearchQuery('laptop', limit: 0);
    }

    public function testLimitTooLargeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be between 1 and 100');

        new SearchQuery('laptop', limit: 101);
    }

    public function testNegativeOffsetThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Offset must be non-negative');

        new SearchQuery('laptop', offset: -1);
    }

    public function testMinSimilarityNegativeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minSimilarity must be between 0.0 and 1.0');

        new SearchQuery('laptop', minSimilarity: -0.1);
    }

    public function testMinSimilarityTooLargeThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('minSimilarity must be between 0.0 and 1.0');

        new SearchQuery('laptop', minSimilarity: 1.1);
    }

    public function testMinSimilarityBoundaries(): void
    {
        $queryMin = new SearchQuery('laptop', minSimilarity: 0.0);
        $this->assertSame(0.0, $queryMin->getMinSimilarity());

        $queryMax = new SearchQuery('laptop', minSimilarity: 1.0);
        $this->assertSame(1.0, $queryMax->getMinSimilarity());
    }

    public function testLimitBoundaries(): void
    {
        $queryMin = new SearchQuery('laptop', limit: 1);
        $this->assertSame(1, $queryMin->getLimit());

        $queryMax = new SearchQuery('laptop', limit: 100);
        $this->assertSame(100, $queryMax->getLimit());
    }

    public function testQueryWithMinimumLength(): void
    {
        $query = new SearchQuery('ab');
        $this->assertSame('ab', $query->getQuery());
    }

    public function testQueryWithMaximumLength(): void
    {
        $longQuery = str_repeat('a', 500);
        $query = new SearchQuery($longQuery);
        $this->assertSame($longQuery, $query->getQuery());
    }

    public function testToArray(): void
    {
        $query = new SearchQuery(
            query: 'gaming laptop',
            limit: 20,
            offset: 10,
            minSimilarity: 0.8,
            category: 'computers'
        );

        $expected = [
            'query' => 'gaming laptop',
            'limit' => 20,
            'offset' => 10,
            'min_similarity' => 0.8,
            'category' => 'computers',
        ];

        $this->assertSame($expected, $query->toArray());
    }

    public function testToArrayWithNullCategory(): void
    {
        $query = new SearchQuery('laptop');

        $result = $query->toArray();

        $this->assertArrayHasKey('category', $result);
        $this->assertNull($result['category']);
    }

    public function testQueryWithMultibyteCharacters(): void
    {
        $query = new SearchQuery('智能手机');
        $this->assertSame('智能手机', $query->getQuery());
    }

    public function testQueryWithEmoji(): void
    {
        $query = new SearchQuery('laptop 💻');
        $this->assertSame('laptop 💻', $query->getQuery());
    }

    public function testQueryWithSpecialCharacters(): void
    {
        $query = new SearchQuery('C++ programming book');
        $this->assertSame('C++ programming book', $query->getQuery());
    }

    public function testZeroOffset(): void
    {
        $query = new SearchQuery('laptop', offset: 0);
        $this->assertSame(0, $query->getOffset());
    }

    public function testLargeOffset(): void
    {
        $query = new SearchQuery('laptop', offset: 1000);
        $this->assertSame(1000, $query->getOffset());
    }

    /**
     * Test spec-010 FR-031: Query validation and sanitization
     */
    public function testQueryIsImmutable(): void
    {
        $query = new SearchQuery('original query');
        $array = $query->toArray();
        
        $this->assertSame('original query', $query->getQuery());
        
        // Value object should be immutable - unchangeable after construction
        $reflectionClass = new \ReflectionClass($query);
        $properties = $reflectionClass->getProperties();
        
        foreach ($properties as $property) {
            $this->assertTrue($property->isReadOnly(), "Property {$property->getName()} should be readonly");
        }
    }
}
