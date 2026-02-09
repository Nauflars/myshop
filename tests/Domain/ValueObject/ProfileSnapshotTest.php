<?php

declare(strict_types=1);

namespace App\Tests\Domain\ValueObject;

use App\Domain\ValueObject\ProfileSnapshot;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ProfileSnapshot value object
 */
class ProfileSnapshotTest extends TestCase
{
    public function testProfileSnapshotCreation(): void
    {
        $purchases = ['Laptop', 'Mouse', 'Keyboard'];
        $searches = ['gaming laptop', 'wireless mouse'];
        $categories = ['Electronics', 'Accessories'];
        
        $snapshot = new ProfileSnapshot($purchases, $searches, $categories);
        
        $this->assertEquals($purchases, $snapshot->getRecentPurchases());
        $this->assertEquals($searches, $snapshot->getRecentSearches());
        $this->assertEquals($categories, $snapshot->getDominantCategories());
    }
    
    public function testProfileSnapshotWithEmptyData(): void
    {
        $snapshot = new ProfileSnapshot([], [], []);
        
        $this->assertEmpty($snapshot->getRecentPurchases());
        $this->assertEmpty($snapshot->getRecentSearches());
        $this->assertEmpty($snapshot->getDominantCategories());
    }
    
    public function testToWeightedText(): void
    {
        $purchases = ['Laptop'];
        $searches = ['gaming'];
        $categories = ['Electronics'];
        
        $snapshot = new ProfileSnapshot($purchases, $searches, $categories);
        $text = $snapshot->toWeightedText();
        
        // Purchases should appear 7 times (70% weight)
        $this->assertEquals(7, substr_count($text, 'Laptop'));
        
        // Searches should appear 2 times (20% weight)
        $this->assertEquals(2, substr_count($text, 'gaming'));
        
        // Categories should appear 1 time (10% weight)
        $this->assertEquals(1, substr_count($text, 'Electronics'));
    }
    
    public function testToWeightedTextWithMultipleItems(): void
    {
        $purchases = ['Laptop', 'Mouse'];
        $searches = ['gaming', 'wireless'];
        $categories = ['Electronics', 'Accessories'];
        
        $snapshot = new ProfileSnapshot($purchases, $searches, $categories);
        $text = $snapshot->toWeightedText();
        
        // Each purchase should appear 7 times
        $this->assertEquals(7, substr_count($text, 'Laptop'));
        $this->assertEquals(7, substr_count($text, 'Mouse'));
        
        // Each search should appear 2 times
        $this->assertEquals(2, substr_count($text, 'gaming'));
        $this->assertEquals(2, substr_count($text, 'wireless'));
    }
    
    public function testToWeightedTextWithEmptyData(): void
    {
        $snapshot = new ProfileSnapshot([], [], []);
        $text = $snapshot->toWeightedText();
        
        $this->assertEmpty($text);
    }
    
    public function testToArray(): void
    {
        $purchases = ['Laptop'];
        $searches = ['gaming'];
        $categories = ['Electronics'];
        
        $snapshot = new ProfileSnapshot($purchases, $searches, $categories);
        $array = $snapshot->toArray();
        
        $this->assertArrayHasKey('recentPurchases', $array);
        $this->assertArrayHasKey('recentSearches', $array);
        $this->assertArrayHasKey('dominantCategories', $array);
        $this->assertEquals($purchases, $array['recentPurchases']);
        $this->assertEquals($searches, $array['recentSearches']);
        $this->assertEquals($categories, $array['dominantCategories']);
    }
    
    public function testFromArray(): void
    {
        $data = [
            'recentPurchases' => ['Laptop', 'Mouse'],
            'recentSearches' => ['gaming'],
            'dominantCategories' => ['Electronics'],
        ];
        
        $snapshot = ProfileSnapshot::fromArray($data);
        
        $this->assertEquals(['Laptop', 'Mouse'], $snapshot->getRecentPurchases());
        $this->assertEquals(['gaming'], $snapshot->getRecentSearches());
        $this->assertEquals(['Electronics'], $snapshot->getDominantCategories());
    }
    
    public function testFromArrayWithMissingKeys(): void
    {
        $data = [
            'recentPurchases' => ['Laptop'],
        ];
        
        $snapshot = ProfileSnapshot::fromArray($data);
        
        $this->assertEquals(['Laptop'], $snapshot->getRecentPurchases());
        $this->assertEmpty($snapshot->getRecentSearches());
        $this->assertEmpty($snapshot->getDominantCategories());
    }
}
