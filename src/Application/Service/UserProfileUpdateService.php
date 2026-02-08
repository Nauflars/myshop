<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Entity\User;
use Psr\Log\LoggerInterface;

/**
 * UserProfileUpdateService - Handles automatic profile updates
 * 
 * Updates user profiles automatically when:
 * - User completes a purchase
 * - User performs a search
 */
class UserProfileUpdateService
{
    public function __construct(
        private readonly ProfileAggregationService $profileAggregation,
        private readonly UserProfileService $userProfileService,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Update user profile asynchronously (non-blocking)
     * 
     * This is called after searches and purchases to keep profiles up-to-date
     */
    public function scheduleProfileUpdate(User $user): void
    {
        try {
            // For now, update synchronously (can be moved to async queue later)
            $this->updateProfile($user);
        } catch (\Exception $e) {
            // Log error but don't block the main operation
            $this->logger->error('Failed to schedule profile update', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Immediately update user profile
     */
    public function updateProfile(User $user): void
    {
        try {
            $this->logger->info('Updating user profile automatically', [
                'userId' => $user->getId(),
                'email' => $user->getEmail(),
            ]);

            // Step 1: Aggregate user data (purchases, searches, categories)
            $snapshot = $this->profileAggregation->aggregateUserData($user);

            // Check if user has any activity
            $hasActivity = 
                count($snapshot->getRecentPurchases()) > 0 ||
                count($snapshot->getRecentSearches()) > 0 ||
                count($snapshot->getDominantCategories()) > 0;

            // If no activity, create a basic profile with user name/interests
            if (!$hasActivity) {
                $this->logger->info('User has no recorded activity, creating basic profile', [
                    'userId' => $user->getId(),
                ]);
                
                // Create basic snapshot with user name as initial interest
                $userName = $user->getName() ?? '';
                $initialData = !empty($userName) ? [$userName] : ['new user'];
                $snapshot = new \App\Domain\ValueObject\ProfileSnapshot(
                    recentPurchases: $initialData,
                    recentSearches: [],
                    dominantCategories: []
                );
            }

            // Step 2: Generate or update profile with embedding
            $this->userProfileService->generateOrUpdateProfile($user, $snapshot);

            $this->logger->info('User profile updated successfully', [
                'userId' => $user->getId(),
                'purchases' => count($snapshot->getRecentPurchases()),
                'searches' => count($snapshot->getRecentSearches()),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update user profile', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
