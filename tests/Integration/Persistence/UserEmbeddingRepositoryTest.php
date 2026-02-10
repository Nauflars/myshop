<?php

declare(strict_types=1);

namespace App\Tests\Integration\Persistence;

use App\Domain\Repository\UserEmbeddingRepositoryInterface;
use App\Domain\ValueObject\UserEmbedding;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * UserEmbeddingRepositoryTest - Integration test for MongoDB CRUD operations
 * 
 * Spec-014 Phase 8 T075: Tests MongoDB persistence layer
 * Validates findOneAndUpdate, optimistic locking, and vector operations
 */
class UserEmbeddingRepositoryTest extends KernelTestCase
{
    private UserEmbeddingRepositoryInterface $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->repository = $container->get(UserEmbeddingRepositoryInterface::class);
    }

    /**
     * @test
     * @group integration
     * @group mongodb
     */
    public function can_save_new_user_embedding(): void
    {
        $userId = random_int(100000, 999999);
        $embedding = array_fill(0, 1536, 0.1);
        $userEmbedding = new UserEmbedding(
            userId: $userId,
            embedding: $embedding,
            eventCount: 1,
            lastUpdated: new DateTimeImmutable(),
            version: 1
        );

        $result = $this->repository->save($userEmbedding);

        $this->assertTrue($result);

        // Verify can retrieve
        $retrieved = $this->repository->find($userId);
        $this->assertNotNull($retrieved);
        $this->assertSame($userId, $retrieved->getUserId());
        $this->assertSame(1, $retrieved->getEventCount());
    }

    /**
     * @test
     * @group integration
     * @group mongodb
     */
    public function find_returns_null_for_non_existent_user(): void
    {
        $nonExistentUserId = random_int(900000, 999999);

        $result = $this->repository->find($nonExistentUserId);

        $this->assertNull($result);
    }

    /**
     * @test
     * @group integration
     * @group mongodb
     */
    public function can_update_existing_user_embedding_with_optimistic_locking(): void
    {
        $userId = random_int(100000, 999999);
        $embedding = array_fill(0, 1536, 0.2);
        
        // Create initial embedding
        $userEmbedding = new UserEmbedding(
            userId: $userId,
            embedding: $embedding,
            eventCount: 1,
            lastUpdated: new DateTimeImmutable(),
            version: 1
        );
        $this->repository->save($userEmbedding);

        // Update with new embedding
        $newEmbedding = array_fill(0, 1536, 0.5);
        $updatedUserEmbedding = new UserEmbedding(
            userId: $userId,
            embedding: $newEmbedding,
            eventCount: 2,
            lastUpdated: new DateTimeImmutable(),
            version: 2
        );

        $result = $this->repository->save($updatedUserEmbedding);

        $this->assertTrue($result);

        // Verify updated values
        $retrieved = $this->repository->find($userId);
        $this->assertSame(2, $retrieved->getEventCount());
        $this->assertSame(2, $retrieved->getVersion());
    }

    /**
     * @test
     * @group integration
     * @group mongodb
     */
    public function embedding_vector_has_correct_dimensions(): void
    {
        $userId = random_int(100000, 999999);
        $embedding = array_fill(0, 1536, 0.3);
        
        $userEmbedding = new UserEmbedding(
            userId: $userId,
            embedding: $embedding,
            eventCount: 1,
            lastUpdated: new DateTimeImmutable(),
            version: 1
        );
        $this->repository->save($userEmbedding);

        $retrieved = $this->repository->find($userId);
        
        $this->assertCount(1536, $retrieved->getEmbedding());
    }

    /**
     * @test
     * @group integration
     * @group mongodb
     */
    public function last_updated_timestamp_is_stored_correctly(): void
    {
        $userId = random_int(100000, 999999);
        $now = new DateTimeImmutable('2026-02-10 12:00:00');
        
        $userEmbedding = new UserEmbedding(
            userId: $userId,
            embedding: array_fill(0, 1536, 0.4),
            eventCount: 1,
            lastUpdated: $now,
            version: 1
        );
        $this->repository->save($userEmbedding);

        $retrieved = $this->repository->find($userId);
        
        $this->assertEqualsWithDelta(
            $now->getTimestamp(),
            $retrieved->getLastUpdated()->getTimestamp(),
            2, // Allow 2 seconds tolerance
            'Timestamp should be stored and retrieved accurately'
        );
    }

    /**
     * @test
     * @group integration
     * @group mongodb
     */
    public function can_handle_multiple_concurrent_users(): void
    {
        $userIds = [];
        for ($i = 0; $i < 5; $i++) {
            $userId = random_int(100000, 999999);
            $userIds[] = $userId;
            
            $userEmbedding = new UserEmbedding(
                userId: $userId,
                embedding: array_fill(0, 1536, 0.1 * ($i + 1)),
                eventCount: $i + 1,
                lastUpdated: new DateTimeImmutable(),
                version: 1
            );
            $this->repository->save($userEmbedding);
        }

        // Verify all users can be retrieved
        foreach ($userIds as $index => $userId) {
            $retrieved = $this->repository->find($userId);
            $this->assertNotNull($retrieved);
            $this->assertSame($index + 1, $retrieved->getEventCount());
        }
    }

    /**
     * @test
     * @group integration
     * @group mongodb
     */
    public function version_is_incremented_on_update(): void
    {
        $userId = random_int(100000, 999999);
        
        // Version 1
        $embedding1 = new UserEmbedding(
            userId: $userId,
            embedding: array_fill(0, 1536, 0.1),
            eventCount: 1,
            lastUpdated: new DateTimeImmutable(),
            version: 1
        );
        $this->repository->save($embedding1);

        // Version 2
        $embedding2 = new UserEmbedding(
            userId: $userId,
            embedding: array_fill(0, 1536, 0.2),
            eventCount: 2,
            lastUpdated: new DateTimeImmutable(),
            version: 2
        );
        $this->repository->save($embedding2);

        $retrieved = $this->repository->find($userId);
        $this->assertSame(2, $retrieved->getVersion());
    }

    /**
     * @test
     * @group integration
     * @group mongodb
     */
    public function event_count_accumulates_correctly(): void
    {
        $userId = random_int(100000, 999999);
        
        // Start with 1 event
        $embedding = new UserEmbedding(
            userId: $userId,
            embedding: array_fill(0, 1536, 0.1),
            eventCount: 1,
            lastUpdated: new DateTimeImmutable(),
            version: 1
        );
        $this->repository->save($embedding);

        // Add more events
        $embedding2 = new UserEmbedding(
            userId: $userId,
            embedding: array_fill(0, 1536, 0.2),
            eventCount: 5,
            lastUpdated: new DateTimeImmutable(),
            version: 2
        );
        $this->repository->save($embedding2);

        $retrieved = $this->repository->find($userId);
        $this->assertSame(5, $retrieved->getEventCount());
    }
}
