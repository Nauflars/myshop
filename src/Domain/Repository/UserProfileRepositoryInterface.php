<?php

namespace App\Domain\Repository;

use App\Domain\Entity\UserProfile;

/**
 * Repository interface for UserProfile persistence.
 */
interface UserProfileRepositoryInterface
{
    /**
     * Find user profile by user ID.
     */
    public function findByUserId(string $userId): ?UserProfile;

    /**
     * Save or update user profile.
     */
    public function save(UserProfile $profile): void;

    /**
     * Delete user profile by user ID.
     */
    public function delete(string $userId): void;

    /**
     * Find products similar to user's embedding.
     *
     * @param array $embedding User's embedding vector (1536 dimensions)
     * @param int   $limit     Maximum number of results
     *
     * @return array Array of ['productId' => string, 'score' => float]
     */
    public function findSimilarProducts(array $embedding, int $limit = 20): array;

    /**
     * Find all profiles that need refresh (stale).
     *
     * @param int $daysOld Profiles older than this many days
     *
     * @return UserProfile[]
     */
    public function findStaleProfiles(int $daysOld = 30): array;

    /**
     * Get total profile count.
     */
    public function countProfiles(): int;

    /**
     * Check if profile exists for user.
     */
    public function exists(string $userId): bool;
}
