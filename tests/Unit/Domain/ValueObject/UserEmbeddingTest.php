<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\UserEmbedding;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * UserEmbeddingTest - Unit tests for UserEmbedding value object
 * 
 * Tests spec-014 T020: User embedding with update and normalization logic
 */
class UserEmbeddingTest extends TestCase
{
    private const DIMENSIONS = 1536;

    private function createRandomVector(int $dimensions = self::DIMENSIONS): array
    {
        $vector = [];
        for ($i = 0; $i < $dimensions; $i++) {
            $vector[] = (float) (mt_rand(-1000, 1000) / 1000);
        }
        return $vector;
    }

    private function createNormalizedVector(int $dimensions = self::DIMENSIONS): array
    {
        $vector = $this->createRandomVector($dimensions);
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v ** 2, $vector)));
        return array_map(fn($v) => $v / $magnitude, $vector);
    }

    public function testConstructorWithValidData(): void
    {
        $vector = $this->createNormalizedVector();
        $timestamp = new DateTimeImmutable();

        $embedding = new UserEmbedding($vector, $timestamp, 5);

        $this->assertSame($vector, $embedding->vector);
        $this->assertSame($timestamp, $embedding->lastUpdated);
        $this->assertSame(5, $embedding->eventCount);
    }

    public function testConstructorRejectsWrongDimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Embedding must be 1536 dimensions');

        new UserEmbedding(
            vector: [1.0, 2.0, 3.0], // Wrong dimensions
            lastUpdated: new DateTimeImmutable(),
            eventCount: 1
        );
    }

    public function testConstructorRejectsNegativeEventCount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event count cannot be negative');

        new UserEmbedding(
            vector: $this->createNormalizedVector(),
            lastUpdated: new DateTimeImmutable(),
            eventCount: -1
        );
    }

    public function testFromEventEmbedding(): void
    {
        $eventVector = $this->createRandomVector();
        $timestamp = new DateTimeImmutable('2026-02-10 10:00:00');

        $embedding = UserEmbedding::fromEventEmbedding($eventVector, $timestamp);

        $this->assertCount(self::DIMENSIONS, $embedding->vector);
        $this->assertEquals($timestamp, $embedding->lastUpdated);
        $this->assertSame(1, $embedding->eventCount);

        // Verify normalization: sum of squares should be 1.0
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v ** 2, $embedding->vector)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);
    }

    public function testUpdateWithNoTemporalDecay(): void
    {
        // Initial embedding
        $initialVector = $this->createNormalizedVector();
        $initialTime = new DateTimeImmutable('2026-02-10 10:00:00');
        $embedding = new UserEmbedding($initialVector, $initialTime, 1);

        // Update with new event on same day (no decay)
        $eventVector = $this->createNormalizedVector();
        $eventTime = new DateTimeImmutable('2026-02-10 14:00:00'); // Same day
        $eventWeight = 0.7;

        $updated = $embedding->updateWith($eventVector, $eventWeight, $eventTime);

        $this->assertCount(self::DIMENSIONS, $updated->vector);
        $this->assertEquals($eventTime, $updated->lastUpdated);
        $this->assertSame(2, $updated->eventCount);

        // Verify immutability
        $this->assertNotSame($embedding, $updated);
        $this->assertSame(1, $embedding->eventCount);
    }

    public function testUpdateWithTemporalDecay(): void
    {
        // Initial embedding
        $initialVector = $this->createNormalizedVector();
        $initialTime = new DateTimeImmutable('2026-01-10 10:00:00');
        $embedding = new UserEmbedding($initialVector, $initialTime, 1);

        // Update 30 days later (should apply decay)
        $eventVector = $this->createNormalizedVector();
        $eventTime = new DateTimeImmutable('2026-02-09 10:00:00'); // 30 days later
        $eventWeight = 1.0;
        $decayLambda = 0.023; // 30-day half-life

        $updated = $embedding->updateWith($eventVector, $eventWeight, $eventTime, $decayLambda);

        // Verify updated
        $this->assertEquals($eventTime, $updated->lastUpdated);
        $this->assertSame(2, $updated->eventCount);

        // Verify normalization after update
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v ** 2, $updated->vector)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);
    }

    public function testUpdateRejectsWrongDimensions(): void
    {
        $embedding = new UserEmbedding(
            $this->createNormalizedVector(),
            new DateTimeImmutable(),
            1
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event vector must be 1536 dimensions');

        $embedding->updateWith(
            eventVector: [1.0, 2.0], // Wrong dimensions
            eventWeight: 0.5,
            eventTimestamp: new DateTimeImmutable(),
        );
    }

    public function testUpdateRejectsInvalidWeight(): void
    {
        $embedding = new UserEmbedding(
            $this->createNormalizedVector(),
            new DateTimeImmutable(),
            1
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event weight must be between 0 and 1.0');

        $embedding->updateWith(
            eventVector: $this->createNormalizedVector(),
            eventWeight: 1.5, // Invalid weight
            eventTimestamp: new DateTimeImmutable(),
        );
    }

    public function testUpdateRejectsPastTimestamp(): void
    {
        $currentTime = new DateTimeImmutable('2026-02-10 10:00:00');
        $embedding = new UserEmbedding(
            $this->createNormalizedVector(),
            $currentTime,
            1
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Event timestamp cannot be before last update');

        $embedding->updateWith(
            eventVector: $this->createNormalizedVector(),
            eventWeight: 0.5,
            eventTimestamp: new DateTimeImmutable('2026-02-09 10:00:00'), // Before current
        );
    }

    public function testCosineSimilarity(): void
    {
        $time = new DateTimeImmutable();
        
        // Create two embeddings with same vector
        $vector = $this->createNormalizedVector();
        $embedding1 = new UserEmbedding($vector, $time);
        $embedding2 = new UserEmbedding($vector, $time);

        // Same vectors should have similarity of 1.0
        $similarity = $embedding1->cosineSimilarity($embedding2);
        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    public function testCosineSimilarityOrthogonalVectors(): void
    {
        $time = new DateTimeImmutable();
        
        // Create orthogonal vectors
        $vector1 = array_fill(0, self::DIMENSIONS, 0.0);
        $vector1[0] = 1.0; // [1, 0, 0, ...]
        
        $vector2 = array_fill(0, self::DIMENSIONS, 0.0);
        $vector2[1] = 1.0; // [0, 1, 0, ...]

        $embedding1 = new UserEmbedding($vector1, $time);
        $embedding2 = new UserEmbedding($vector2, $time);

        // Orthogonal vectors should have similarity of ~0
        $similarity = $embedding1->cosineSimilarity($embedding2);
        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);
    }

    public function testToArray(): void
    {
        $vector = $this->createNormalizedVector();
        $embedding = new UserEmbedding($vector, new DateTimeImmutable());

        $array = $embedding->toArray();

        $this->assertSame($vector, $array);
        $this->assertCount(self::DIMENSIONS, $array);
    }

    public function testGetDimensions(): void
    {
        $this->assertSame(1536, UserEmbedding::getDimensions());
    }

    public function testIsStale(): void
    {
        // Recent embedding (not stale)
        $recentTime = new DateTimeImmutable('-10 days');
        $recentEmbedding = new UserEmbedding($this->createNormalizedVector(), $recentTime);
        $this->assertFalse($recentEmbedding->isStale(90));

        // Old embedding (stale)
        $oldTime = new DateTimeImmutable('-100 days');
        $oldEmbedding = new UserEmbedding($this->createNormalizedVector(), $oldTime);
        $this->assertTrue($oldEmbedding->isStale(90));
    }

    public function testisStaleWithCustomThreshold(): void
    {
        $time = new DateTimeImmutable('-15 days');
        $embedding = new UserEmbedding($this->createNormalizedVector(), $time);

        $this->assertFalse($embedding->isStale(30)); // Not stale for 30-day threshold
        $this->assertTrue($embedding->isStale(10));  // Stale for 10-day threshold
    }

    public function testNormalizationIsApplied(): void
    {
        // Create unnormalized vector
        $unnormalizedVector = array_fill(0, self::DIMENSIONS, 2.0);
        
        $embedding = UserEmbedding::fromEventEmbedding(
            $unnormalizedVector,
            new DateTimeImmutable()
        );

        // Check vector is normalized (magnitude = 1.0)
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v ** 2, $embedding->vector)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);
    }

    public function testImmutability(): void
    {
        $originalVector = $this->createNormalizedVector();
        $originalTime = new DateTimeImmutable('2026-02-10');
        $embedding = new UserEmbedding($originalVector, $originalTime, 3);

        // Update should return new instance
        $updated = $embedding->updateWith(
            $this->createNormalizedVector(),
            0.5,
            new DateTimeImmutable('2026-02-11')
        );

        // Original should be unchanged
        $this->assertSame($originalVector, $embedding->vector);
        $this->assertSame($originalTime, $embedding->lastUpdated);
        $this->assertSame(3, $embedding->eventCount);

        // Updated should be different
        $this->assertNotSame($embedding, $updated);
        $this->assertSame(4, $updated->eventCount);
    }
}
