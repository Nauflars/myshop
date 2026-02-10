<?php

declare(strict_types=1);

namespace Tests\Unit\Application\UseCase;

use App\Application\UseCase\CalculateUserEmbedding;
use App\Domain\Repository\UserEmbeddingRepositoryInterface;
use App\Domain\ValueObject\EmbeddingWeights;
use App\Domain\ValueObject\EventType;
use App\Domain\ValueObject\UserEmbedding;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * TemporalDecayTest - Unit tests for temporal decay calculations
 * 
 * Tests spec-014 US3: Older events receive reduced weighting
 */
class TemporalDecayTest extends TestCase
{
    private UserEmbeddingRepositoryInterface $embeddingRepository;
    private LoggerInterface $logger;
    private CalculateUserEmbedding $calculateUseCase;

    protected function setUp(): void
    {
        $this->embeddingRepository = $this->createMock(UserEmbeddingRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    /**
     * Test: 30-day old embedding decays by ~50% (half-life)
     */
    public function testThirtyDayDecayApproximatelyFifty(): void
    {
        // Arrange: Lambda = 0.023 gives 30-day half-life
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        // Act: Calculate decay for 30 days
        $decayFactor = $weights->calculateDecayFactor(30);

        // Assert: Should be approximately 0.5 (50% retained)
        $this->assertEqualsWithDelta(0.5, $decayFactor, 0.01);
    }

    /**
     * Test: 60-day old embedding decays by ~75% (two half-lives)
     */
    public function testSixtyDayDecayApproximatelyTwentyFive(): void
    {
        // Arrange
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        // Act: Calculate decay for 60 days
        $decayFactor = $weights->calculateDecayFactor(60);

        // Assert: Should be approximately 0.25 (25% retained)
        $this->assertEqualsWithDelta(0.25, $decayFactor, 0.01);
    }

    /**
     * Test: 7-day old embedding retains 85%+ weight (recent)
     */
    public function testSevenDayDecayRetainsMostWeight(): void
    {
        // Arrange
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        // Act: Calculate decay for 7 days
        $decayFactor = $weights->calculateDecayFactor(7);

        // Assert: Should retain at least 85%
        $this->assertGreaterThanOrEqual(0.85, $decayFactor);
        $this->assertEqualsWithDelta(0.85, $decayFactor, 0.01);
    }

    /**
     * Test: Same-day update has no decay (decay_factor = 1.0)
     */
    public function testZeroDaysNoDecay(): void
    {
        // Arrange
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        // Act: Calculate decay for 0 days
        $decayFactor = $weights->calculateDecayFactor(0);

        // Assert: Should be exactly 1.0 (no decay)
        $this->assertEquals(1.0, $decayFactor);
    }

    /**
     * Test: Half-life calculation matches expected value
     */
    public function testHalfLifeCalculation(): void
    {
        // Arrange: Lambda = 0.023
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        // Act: Get half-life in days
        $halfLife = $weights->getHalfLifeDays();

        // Assert: Should be approximately 30 days
        $this->assertEqualsWithDelta(30.0, $halfLife, 0.5);
    }

    /**
     * Test: Different lambda values produce different half-lives
     */
    public function testDifferentLambdasProduceDifferentHalfLives(): void
    {
        // Arrange
        $weights15Days = new EmbeddingWeights(decayLambda: 0.046);
        $weights30Days = new EmbeddingWeights(decayLambda: 0.023);
        $weights60Days = new EmbeddingWeights(decayLambda: 0.011);

        // Act
        $halfLife15 = $weights15Days->getHalfLifeDays();
        $halfLife30 = $weights30Days->getHalfLifeDays();
        $halfLife60 = $weights60Days->getHalfLifeDays();

        // Assert
        $this->assertEqualsWithDelta(15.0, $halfLife15, 0.5);
        $this->assertEqualsWithDelta(30.0, $halfLife30, 0.5);
        $this->assertEqualsWithDelta(63.0, $halfLife60, 1.5); // ln(2)/0.011 ≈ 63 days
    }

    /**
     * Test: Embedding update with temporal decay
     */
    public function testEmbeddingUpdateAppliesDecay(): void
    {
        // Arrange: Create existing embedding from 30 days ago
        $oldDate = new DateTimeImmutable('-30 days');
        $existingEmbedding = new UserEmbedding(
            userId: 123,
            vector: array_fill(0, 1536, 1.0 / sqrt(1536)), // Normalized vector
            lastUpdatedAt: $oldDate,
            version: 1
        );

        // New event today with different vector
        $newDate = new DateTimeImmutable();
        $newEventVector = array_fill(0, 1536, -1.0 / sqrt(1536)); // Opposite direction

        $weights = new EmbeddingWeights(decayLambda: 0.023);

        // Act: Update with temporal decay
        $updatedEmbedding = $existingEmbedding->updateWith(
            eventEmbedding: $newEventVector,
            eventType: EventType::SEARCH,
            occurredAt: $newDate,
            weights: $weights
        );

        // Assert: Version incremented
        $this->assertEquals(2, $updatedEmbedding->version);

        // Assert: Timestamp updated
        $this->assertEquals($newDate, $updatedEmbedding->lastUpdatedAt);

        // Assert: Vector is normalized (magnitude = 1)
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v ** 2, $updatedEmbedding->vector)));
        $this->assertEqualsWithDelta(1.0, $magnitude, 0.0001);
    }

    /**
     * Test: Recent update (1 day) retains most of original weight
     */
    public function testRecentUpdateRetainsWeight(): void
    {
        // Arrange: Embedding from 1 day ago
        $oldDate = new DateTimeImmutable('-1 day');
        $existingVector = array_fill(0, 1536, 1.0 / sqrt(1536));
        $existingEmbedding = new UserEmbedding(
            userId: 456,
            vector: $existingVector,
            lastUpdatedAt: $oldDate,
            version: 5
        );

        // Small perturbation event
        $newDate = new DateTimeImmutable();
        $newEventVector = array_fill(0, 1536, 0.9 / sqrt(1536));

        $weights = new EmbeddingWeights(decayLambda: 0.023);

        // Act
        $updatedEmbedding = $existingEmbedding->updateWith(
            eventEmbedding: $newEventVector,
            eventType: EventType::PRODUCT_VIEW,
            occurredAt: $newDate,
            weights: $weights
        );

        // Assert: Version incremented
        $this->assertEquals(6, $updatedEmbedding->version);

        // Assert: Decay factor for 1 day is ~0.977 (very little decay)
        $decayFactor = $weights->calculateDecayFactor(1);
        $this->assertGreaterThan(0.97, $decayFactor);
    }

    /**
     * Test: Weighted average formula with decay
     */
    public function testWeightedAverageWithDecay(): void
    {
        // Arrange: Simple case with known values
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        // Existing embedding: all 1.0 values (normalized)
        $existingVector = array_fill(0, 1536, 1.0 / sqrt(1536));
        $existingEmbedding = new UserEmbedding(
            userId: 789,
            vector: $existingVector,
            lastUpdatedAt: new DateTimeImmutable('-30 days'),
            version: 1
        );

        // New event: all -1.0 values (normalized, opposite direction)
        $newEventVector = array_fill(0, 1536, -1.0 / sqrt(1536));

        // Act: Update (search event weight = 0.7, 30-day decay ≈ 0.5)
        $updatedEmbedding = $existingEmbedding->updateWith(
            eventEmbedding: $newEventVector,
            eventType: EventType::SEARCH,
            occurredAt: new DateTimeImmutable(),
            weights: $weights
        );

        // Assert: Result should be closer to new event (weight 0.7) than old (weight ~0.5)
        // Formula: (old * 0.5 + new * 0.7) / (0.5 + 0.7) = (0.5 - 0.7) / 1.2 ≈ -0.167 per dimension
        // After normalization, should be more negative than positive
        $firstValue = $updatedEmbedding->vector[0];
        $this->assertLessThan(0, $firstValue, 'New event should dominate after decay');
    }

    /**
     * Test: Invalid lambda throws exception
     */
    public function testNegativeLambdaThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Decay lambda must be positive');

        new EmbeddingWeights(decayLambda: -0.01);
    }

    /**
     * Test: Zero lambda throws exception
     */
    public function testZeroLambdaThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Decay lambda must be positive');

        new EmbeddingWeights(decayLambda: 0.0);
    }

    /**
     * Test: Environment variable loading
     */
    public function testFromEnvironmentLoadsLambda(): void
    {
        // Arrange: Set environment variable
        $_ENV['EMBEDDING_DECAY_LAMBDA'] = '0.046';

        // Act: Create from environment
        $weights = EmbeddingWeights::fromEnvironment();

        // Assert: Lambda matches environment
        $this->assertEquals(0.046, $weights->decayLambda);

        // Cleanup
        unset($_ENV['EMBEDDING_DECAY_LAMBDA']);
    }

    /**
     * Test: Decay progression over multiple time periods
     */
public function testDecayProgressionOverTime(): void
    {
        // Arrange
        $weights = new EmbeddingWeights(decayLambda: 0.023);

        // Act: Calculate decay for different time periods
        $decay1Day = $weights->calculateDecayFactor(1);
        $decay7Days = $weights->calculateDecayFactor(7);
        $decay30Days = $weights->calculateDecayFactor(30);
        $decay90Days = $weights->calculateDecayFactor(90);
        $decay365Days = $weights->calculateDecayFactor(365);

        // Assert: Monotonic decrease
        $this->assertGreaterThan($decay7Days, $decay1Day);
        $this->assertGreaterThan($decay30Days, $decay7Days);
        $this->assertGreaterThan($decay90Days, $decay30Days);
        $this->assertGreaterThan($decay365Days, $decay90Days);

        // Assert: Specific values
        $this->assertGreaterThan(0.97, $decay1Day); // 1 day: ~97% retained
        $this->assertGreaterThan(0.85, $decay7Days); // 7 days: ~85% retained
        $this->assertEqualsWithDelta(0.5, $decay30Days, 0.01); // 30 days: ~50% retained
        $this->assertLessThan(0.15, $decay90Days); // 90 days: <15% retained
        $this->assertLessThan(0.001, $decay365Days); // 365 days: negligible
    }
}
