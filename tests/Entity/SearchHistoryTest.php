<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Entity\SearchHistory;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for SearchHistory entity
 */
class SearchHistoryTest extends TestCase
{
    private User $user;
    
    protected function setUp(): void
    {
        $this->user = new User(
            'Test User',
            new Email('test@example.com'),
            'password_hash'
        );
    }
    
    public function testSearchHistoryCreation(): void
    {
        $searchHistory = new SearchHistory(
            $this->user,
            'gaming laptop',
            'semantic',
            'Electronics'
        );
        
        $this->assertNotEmpty($searchHistory->getId());
        $this->assertSame($this->user, $searchHistory->getUser());
        $this->assertEquals('gaming laptop', $searchHistory->getQuery());
        $this->assertEquals('semantic', $searchHistory->getMode());
        $this->assertEquals('Electronics', $searchHistory->getCategory());
        $this->assertInstanceOf(\DateTimeImmutable::class, $searchHistory->getCreatedAt());
    }
    
    public function testSearchHistoryWithoutCategory(): void
    {
        $searchHistory = new SearchHistory(
            $this->user,
            'wireless mouse'
        );
        
        $this->assertEquals('wireless mouse', $searchHistory->getQuery());
        $this->assertEquals('semantic', $searchHistory->getMode()); // default mode
        $this->assertNull($searchHistory->getCategory());
    }
    
    public function testSearchHistoryWithKeywordMode(): void
    {
        $searchHistory = new SearchHistory(
            $this->user,
            'laptop',
            'keyword'
        );
        
        $this->assertEquals('keyword', $searchHistory->getMode());
    }
    
    public function testSearchHistoryCreatedAtIsAutoSet(): void
    {
        $beforeCreation = new \DateTimeImmutable();
        
        $searchHistory = new SearchHistory(
            $this->user,
            'test query'
        );
        
        $afterCreation = new \DateTimeImmutable();
        
        $this->assertGreaterThanOrEqual(
            $beforeCreation->getTimestamp(),
            $searchHistory->getCreatedAt()->getTimestamp()
        );
        $this->assertLessThanOrEqual(
            $afterCreation->getTimestamp(),
            $searchHistory->getCreatedAt()->getTimestamp()
        );
    }
}
