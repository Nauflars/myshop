<?php

namespace App\Domain\Entity;

use App\Domain\ValueObject\ProfileSnapshot;

/**
 * User Profile for personalized recommendations
 * 
 * Stores the user's embedding vector (1536 dimensions) generated from
 * purchase history, search queries, and browsing behavior.
 */
class UserProfile
{
    private string $userId;
    private array $embeddingVector;
    private ProfileSnapshot $dataSnapshot;
    private array $metadata;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;
    private ?\DateTimeImmutable $lastActivityDate;

    public function __construct(
        string $userId,
        array $embeddingVector,
        ProfileSnapshot $dataSnapshot,
        array $metadata = []
    ) {
        $this->validateEmbeddingVector($embeddingVector);
        
        $this->userId = $userId;
        $this->embeddingVector = $embeddingVector;
        $this->dataSnapshot = $dataSnapshot;
        $this->metadata = $metadata;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        
        // Don't set lastActivityDate from metadata here - it will be set by reflection in fromArray
        $this->lastActivityDate = null;
    }

    private function validateEmbeddingVector(array $vector): void
    {
        if (count($vector) !== 1536) {
            throw new \InvalidArgumentException(
                sprintf('Embedding vector must have exactly 1536 dimensions, got %d', count($vector))
            );
        }

        foreach ($vector as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new \InvalidArgumentException('Embedding vector values must be numeric');
            }
        }
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getEmbeddingVector(): array
    {
        return $this->embeddingVector;
    }

    public function getDataSnapshot(): ProfileSnapshot
    {
        return $this->dataSnapshot;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getLastActivityDate(): ?\DateTimeImmutable
    {
        return $this->lastActivityDate;
    }

    /**
     * Update profile with new embedding and data
     */
    public function updateProfile(
        array $embeddingVector,
        ProfileSnapshot $dataSnapshot,
        array $metadata = []
    ): void {
        $this->validateEmbeddingVector($embeddingVector);
        
        $this->embeddingVector = $embeddingVector;
        $this->dataSnapshot = $dataSnapshot;
        $this->metadata = array_merge($this->metadata, $metadata);
        $this->updatedAt = new \DateTimeImmutable();
        
        if (isset($metadata['lastActivityDate'])) {
            $this->lastActivityDate = $metadata['lastActivityDate'];
        }
    }

    /**
     * Check if profile is stale (not updated in X days)
     */
    public function isStale(int $days = 30): bool
    {
        $threshold = new \DateTimeImmutable("-{$days} days");
        return $this->updatedAt < $threshold;
    }

    /**
     * Convert to array for MongoDB storage
     */
    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'embeddingVector' => $this->embeddingVector,
            'dataSnapshot' => [
                'recentPurchases' => $this->dataSnapshot->getRecentPurchases(),
                'recentSearches' => $this->dataSnapshot->getRecentSearches(),
                'dominantCategories' => $this->dataSnapshot->getDominantCategories(),
            ],
            'metadata' => $this->metadata,
            'createdAt' => $this->createdAt->format('c'),
            'updatedAt' => $this->updatedAt->format('c'),
            'lastActivityDate' => $this->lastActivityDate?->format('c'),
        ];
    }

    /**
     * Create from MongoDB document
     */
    public static function fromArray(array $data): self
    {
        // Convert BSON arrays to PHP arrays
        $recentPurchases = $data['dataSnapshot']['recentPurchases'] ?? [];
        if ($recentPurchases instanceof \MongoDB\Model\BSONArray) {
            $recentPurchases = $recentPurchases->getArrayCopy();
        }

        $recentSearches = $data['dataSnapshot']['recentSearches'] ?? [];
        if ($recentSearches instanceof \MongoDB\Model\BSONArray) {
            $recentSearches = $recentSearches->getArrayCopy();
        }

        $dominantCategories = $data['dataSnapshot']['dominantCategories'] ?? [];
        if ($dominantCategories instanceof \MongoDB\Model\BSONArray) {
            $dominantCategories = $dominantCategories->getArrayCopy();
        }

        $embeddingVector = $data['embeddingVector'];
        if ($embeddingVector instanceof \MongoDB\Model\BSONArray) {
            $embeddingVector = $embeddingVector->getArrayCopy();
        }

        $metadata = $data['metadata'] ?? [];
        if ($metadata instanceof \MongoDB\Model\BSONDocument) {
            $metadata = (array) $metadata;
        }

        $snapshot = new ProfileSnapshot(
            $recentPurchases,
            $recentSearches,
            $dominantCategories
        );

        $profile = new self(
            $data['userId'],
            $embeddingVector,
            $snapshot,
            $metadata
        );

        // Restore timestamps
        if (isset($data['createdAt'])) {
            $reflection = new \ReflectionClass($profile);
            $property = $reflection->getProperty('createdAt');
            $property->setAccessible(true);
            
            $createdAt = $data['createdAt'];
            if ($createdAt instanceof \MongoDB\BSON\UTCDateTime) {
                $createdAt = $createdAt->toDateTime()->format('c');
            }
            $property->setValue($profile, new \DateTimeImmutable($createdAt));
        }

        if (isset($data['updatedAt'])) {
            $reflection = new \ReflectionClass($profile);
            $property = $reflection->getProperty('updatedAt');
            $property->setAccessible(true);
            
            $updatedAt = $data['updatedAt'];
            if ($updatedAt instanceof \MongoDB\BSON\UTCDateTime) {
                $updatedAt = $updatedAt->toDateTime()->format('c');
            }
            $property->setValue($profile, new \DateTimeImmutable($updatedAt));
        }

        if (isset($data['lastActivityDate'])) {
            $reflection = new \ReflectionClass($profile);
            $property = $reflection->getProperty('lastActivityDate');
            $property->setAccessible(true);
            
            $lastActivityDate = $data['lastActivityDate'];
            if ($lastActivityDate instanceof \MongoDB\BSON\UTCDateTime) {
                $lastActivityDate = $lastActivityDate->toDateTime()->format('c');
            }
            if ($lastActivityDate !== null) {
                $property->setValue($profile, new \DateTimeImmutable($lastActivityDate));
            }
        }

        return $profile;
    }
}
