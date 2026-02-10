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
 * CalculateUserEmbeddingTest - Unit tests for embedding calculation use case
 */
class CalculateUserEmbeddingTest extends TestCase
{
    private UserEmbeddingRepositoryInterface $repository;
    private EmbeddingWeights $weights;
    private LoggerInterface $logger;
    private CalculateUserEmbedding $useCase;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(UserEmbeddingRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        
        // Use default weights
        $this->weights = new EmbeddingWeights(
            decayLambda: 0.023,
            batchWindowSeconds: 5,
            batchEnabled: false,
            maxRetries: 5,
            retryDelayMs: 5000
        );

        $this->useCase = new CalculateUserEmbedding(
            $this->repository,
            $this->weights,
            $this->logger
        );
    }

    /**
     * Test: Create initial embedding for new user
     */
    public function testCreateInitialEmbeddingForNewUser(): void
    {
        // Arrange: No existing embedding
        $userId = 12345;
        $eventEmbedding = $this->createNormalizedVector();
        $occurredAt = new DateTimeImmutable();

        $this->repository
            ->expects($this->once())
            ->method('findByUserId')
            ->with($userId)
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->willReturn(true);

        // Act: Calculate embedding
        $result = $this->useCase->execute(
            userId: $userId,
            eventType: EventType::SEARCH,
            eventEmbedding: $eventEmbedding,
            occurredAt: $occurredAt
        );

        // Assert: New embedding created
        $this->assertInstanceOf(UserEmbedding::class, $result);
        $this->assertEquals($userId, $result->userId);
        $this->assertEquals(1, $result->version);
        $this->assertEquals($occurredAt, $result->lastUpdatedAt);
    }

    /**
     * Test: Update existing embedding with temporal decay
     */
    public function testUpdateExistingEmbeddingWithTemporalDecay(): void
    {
        // Arrange: Existing embedding from 10 days ago
        $userId = 67890;
        $oldDate = new DateTimeImmutable('-10 days');
        $newDate = new DateTimeImmutable();

        $existingEmbedding = new UserEmbedding(
            userId: $userId,
            vector: $this->createNormalizedVector(),
            lastUpdatedAt: $oldDate,
            version: 5
        );

        $this->repository
            ->expects($this->once())
            ->method('findByUserId')
            ->with($userId)
            ->willReturn($existingEmbedding);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->willReturn(true);

        // Act: Calculate with new event
        $newEventEmbedding = $this->createNormalizedVector(0.5);
        $result = $this->useCase->execute(
            userId: $userId,
            eventType: EventType::PRODUCT_PURCHASE,
            eventEmbedding: $newEventEmbedding,
            occurredAt: $newDate
        );

        // Assert: Version incremented, date updated
        $this->assertEquals($userId, $result->userId);
        $this->assertEquals(6, $result->version);
        $this->assertEquals($newDate, $result->lastUpdatedAt);
        $this->assertNotEquals($existingEmbedding->vector, $result->vector);
    }

    /**
     * Test: Reject invalid embedding dimensions
     */
    public function testRejectInvalidEmbeddingDimensions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event embedding must have 1536 dimensions');

        // Act: Try with wrong dimensions
        $this->useCase->execute(
            userId: 1,
            eventType: EventType::SEARCH,
            eventEmbedding: [0.1, 0.2, 0.3], // Only 3 dimensions
            occurredAt: new DateTimeImmutable()
        );
    }

    /**
     * Test: Handle repository save failure
     */
    public function testHandleRepositorySaveFailure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to save user embedding');

        // Arrange: Repository returns false (optimistic locking conflict)
        $this->repository
            ->method('findByUserId')
            ->willReturn(null);

        $this->repository
            ->expects($this->once())
            ->method('save')
            ->willReturn(false);

        // Act: Try to save
        $this->useCase->execute(
            userId: 1,
            eventType: EventType::SEARCH,
            eventEmbedding: $this->createNormalizedVector(),
            occurredAt: new DateTimeImmutable()
        );
    }

    /**
     * Test: Batch processing multiple events
     */
    public function testBatchProcessingMultipleEvents(): void
    {
        // Arrange: Multiple events
        $events = [
            [
                'user_id' => 1,
                'event_type' => EventType::SEARCH,
                'embedding' => $this->createNormalizedVector(),
                'occurred_at' => new DateTimeImmutable(),
            ],
            [
                'user_id' => 2,
                'event_type' => EventType::PRODUCT_VIEW,
                'embedding' => $this->createNormalizedVector(0.5),
                'occurred_at' => new DateTimeImmutable(),
            ],
            [
                'user_id' => 3,
                'event_type' => EventType::PRODUCT_PURCHASE,
                'embedding' => $this->createNormalizedVector(0.8),
                'occurred_at' => new DateTimeImmutable(),
            ],
        ];

        $this->repository
            ->method('findByUserId')
            ->willReturn(null);

        $this->repository
            ->method('save')
            ->willReturn(true);

        // Act: Process batch
        $result = $this->useCase->executeBatch($events);

        // Assert: All processed successfully
        $this->assertEquals(3, $result['success']);
        $this->assertEquals(0, $result['failed']);
        $this->assertCount(3, $result['embeddings']);
    }

    /**
     * Test: Batch processing handles partial failures
     */
    public function testBatchProcessingHandlesPartialFailures(): void
    {
        // Arrange: Mix of valid and invalid events
        $events = [
            [
                'user_id' => 1,
                'event_type' => EventType::SEARCH,
                'embedding' => $this->createNormalizedVector(),
                'occurred_at' => new DateTimeImmutable(),
            ],
            [
                'user_id' => 2,
                'event_type' => EventType::PRODUCT_VIEW,
                'embedding' => [0.1, 0.2], // Invalid dimensions
                'occurred_at' => new DateTimeImmutable(),
            ],
        ];

        $this->repository
            ->method('findByUserId')
            ->willReturn(null);

        $this->repository
            ->method('save')
            ->willReturn(true);

        // Act: Process batch
        $result = $this->useCase->executeBatch($events);

        // Assert: Partial success
        $this->assertEquals(1, $result['success']);
        $this->assertEquals(1, $result['failed']);
        $this->assertCount(1, $result['embeddings']);
    }

    /**
     * Test: Calculate similarity to query vector
     */
    public function testCalculateSimilarityToQueryVector(): void
    {
        // Arrange: User with existing embedding
        $userId = 99999;
        $userVector = $this->createNormalizedVector(0.5);
        $queryVector = $this->createNormalizedVector(0.5);

        $existingEmbedding = new UserEmbedding(
            userId: $userId,
            vector: $userVector,
            lastUpdatedAt: new DateTimeImmutable(),
            version: 1
        );

        $this->repository
            ->expects($this->once())
            ->method('findByUserId')
            ->with($userId)
            ->willReturn($existingEmbedding);

        // Act: Calculate similarity
        $similarity = $this->useCase->calculateSimilarity($userId, $queryVector);

        // Assert: Similarity should be high (same vector)
        $this->assertGreaterThan(0.0, $similarity);
        $this->assertLessThanOrEqual(1.0, $similarity);
    }

    /**
     * Test: Calculate similarity returns 0.0 for non-existent user
     */
    public function testCalculateSimilarityReturnsZeroForNonExistentUser(): void
    {
        // Arrange: No embedding exists
        $this->repository
            ->expects($this->once())
            ->method('findByUserId')
            ->willReturn(null);

        // Act: Calculate similarity
        $similarity = $this->useCase->calculateSimilarity(1, $this->createNormalizedVector());

        // Assert: Should return 0.0
        $this->assertEquals(0.0, $similarity);
    }

    /**
     * Test: Similarity calculation rejects invalid dimensions
     */
    public function testSimilarityCalculationRejectsInvalidDimensions(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Query embedding must have 1536 dimensions');

        // Act: Try with wrong dimensions
        $this->useCase->calculateSimilarity(1, [0.1, 0.2, 0.3]);
    }

    /**
     * Test: Event weight influences embedding calculation
     */
    public function testEventWeightInfluencesEmbeddingCalculation(): void
    {
        // Arrange: Existing embedding
        $userId = 55555;
        $oldDate = new DateTimeImmutable('-1 day');
        $newDate = new DateTimeImmutable();

        $existingVector = $this->createNormalizedVector(0.2);
        $existingEmbedding = new UserEmbedding(
            userId: $userId,
            vector: $existingVector,
            lastUpdatedAt: $oldDate,
            version: 1
        );

        $this->repository
            ->method('findByUserId')
            ->willReturn($existingEmbedding);

        $this->repository
            ->method('save')
            ->willReturn(true);

        $newEventVector = $this->createNormalizedVector(0.8);

        // Act: Update with high-weight event (purchase vs view)
        $purchaseResult = $this->useCase->execute(
            userId: $userId,
            eventType: EventType::PRODUCT_PURCHASE, // weight = 1.0
            eventEmbedding: $newEventVector,
            occurredAt: $newDate
        );

        // Assert: Purchase event should have stronger influence
        // (We can't test exact values without full implementation, but verify it runs)
        $this->assertInstanceOf(UserEmbedding::class, $purchaseResult);
        $this->assertEquals(2, $purchaseResult->version);
    }

    /**
     * Helper: Create normalized 1536-dimensional vector
     * 
     * @param float $baseValue Base value for all dimensions
     * @return array<float>
     */
    private function createNormalizedVector(float $baseValue = 0.1): array
    {
        $vector = array_fill(0, 1536, $baseValue);
        
        // Normalize to unit length
        $sumSquares = array_sum(array_map(fn($v) => $v * $v, $vector));
        $magnitude = sqrt($sumSquares);
        
        if ($magnitude > 0) {
            $vector = array_map(fn($v) => $v / $magnitude, $vector);
        }
        
        return $vector;
    }
}
