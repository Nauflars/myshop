<?php

namespace App\Application\Service;

use App\Domain\Entity\User;
use App\Domain\Entity\UserProfile;
use App\Domain\Repository\UserProfileRepositoryInterface;
use App\Domain\ValueObject\ProfileSnapshot;
use App\Infrastructure\AI\Service\OpenAIEmbeddingService;
use Psr\Log\LoggerInterface;

/**
 * Service for managing user profiles and embeddings
 * 
 * Orchestrates profile generation, updating, and retrieval
 */
class UserProfileService
{
    private UserProfileRepositoryInterface $repository;
    private ProfileAggregationService $aggregationService;
    private OpenAIEmbeddingService $embeddingService;
    private LoggerInterface $logger;

    public function __construct(
        UserProfileRepositoryInterface $repository,
        ProfileAggregationService $aggregationService,
        OpenAIEmbeddingService $embeddingService,
        LoggerInterface $logger
    ) {
        $this->repository = $repository;
        $this->aggregationService = $aggregationService;
        $this->embeddingService = $embeddingService;
        $this->logger = $logger;
    }

    /**
     * Refresh user profile by regenerating embedding from current data
     * 
     * @param User $user The user whose profile to refresh
     * @param ProfileSnapshot|null $forceSnapshot Optional pre-built snapshot (for new users without activity)
     */
    public function refreshProfile(User $user, ?ProfileSnapshot $forceSnapshot = null): ?UserProfile
    {
        try {
            $this->logger->info('Starting profile refresh', [
                'userId' => $user->getId(),
            ]);

            // Step 1: Aggregate user data (or use provided snapshot)
            if ($forceSnapshot !== null) {
                $snapshot = $forceSnapshot;
                $this->logger->info('Using provided snapshot for new user', [
                    'userId' => $user->getId(),
                ]);
            } else {
                $snapshot = $this->aggregationService->aggregateUserData($user);
            }

            // Check if user has any meaningful data
            if ($snapshot->isEmpty()) {
                $this->logger->warning('User has no activity data, skipping profile generation', [
                    'userId' => $user->getId(),
                ]);
                return null;
            }

            // Step 2: Build text representation
            $textRepresentation = $this->aggregationService->buildTextRepresentation($snapshot);

            // Step 3: Generate embedding
            $embedding = $this->generateEmbedding($textRepresentation);

            if (empty($embedding)) {
                $this->logger->error('Failed to generate embedding', [
                    'userId' => $user->getId(),
                ]);
                return null;
            }

            // Step 4: Get metadata
            $metadata = $this->aggregationService->getUserMetadata($user);

            // Step 5: Create or update profile
            $existingProfile = $this->repository->findByUserId($user->getId());

            if ($existingProfile) {
                $existingProfile->updateProfile($embedding, $snapshot, $metadata);
                $profile = $existingProfile;
            } else {
                $profile = new UserProfile(
                    $user->getId(),
                    $embedding,
                    $snapshot,
                    $metadata
                );
            }

            // Step 6: Save to MongoDB
            $this->repository->save($profile);

            $this->logger->info('Profile refreshed successfully', [
                'userId' => $user->getId(),
                'dataPointCount' => $snapshot->getDataPointCount(),
            ]);

            return $profile;
        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh profile', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * Generate embedding vector from text
     * 
     * @return float[] Array of 1536 floats
     */
    public function generateEmbedding(string $text): array
    {
        try {
            if (empty(trim($text))) {
                $this->logger->warning('Empty text provided for embedding generation');
                return [];
            }

            $result = $this->embeddingService->generateEmbedding($text);

            // Validate embedding dimensions
            if (count($result) !== 1536) {
                $this->logger->error('Invalid embedding dimensions', [
                    'expected' => 1536,
                    'actual' => count($result),
                ]);
                return [];
            }

            return $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate embedding', [
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Get user profile, creating if not exists
     */
    public function getOrCreateProfile(User $user): ?UserProfile
    {
        $profile = $this->repository->findByUserId($user->getId());

        if (!$profile) {
            $profile = $this->refreshProfile($user);
        }

        return $profile;
    }

    /**
     * Get user profile (read-only)
     */
    public function getProfile(User $user): ?UserProfile
    {
        return $this->repository->findByUserId($user->getId());
    }

    /**
     * Check if user profile exists
     */
    public function hasProfile(User $user): bool
    {
        return $this->repository->exists($user->getId());
    }

    /**
     * Delete user profile (for GDPR compliance)
     */
    public function deleteProfile(User $user): void
    {
        try {
            $this->repository->delete($user->getId());
            
            $this->logger->info('User profile deleted', [
                'userId' => $user->getId(),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete profile', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get stale profiles that need refresh
     * 
     * @return UserProfile[]
     */
    public function getStaleProfiles(int $daysOld = 30): array
    {
        return $this->repository->findStaleProfiles($daysOld);
    }

    /**
     * Get profile statistics
     */
    public function getStatistics(): array
    {
        return [
            'totalProfiles' => $this->repository->countProfiles(),
        ];
    }
}
