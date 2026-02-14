<?php

declare(strict_types=1);

namespace App\Domain\Entity;

/**
 * ProductEmbedding - Vector embedding representation of product for semantic search.
 *
 * Stored in MongoDB for vector similarity search. This is NOT a Doctrine entity.
 * MongoDB document structure managed by MongoDBEmbeddingRepository.
 *
 * Part of spec-010: Semantic Product Search
 */
class ProductEmbedding
{
    public function __construct(
        private string $productId, // Changed from int to string for UUID compatibility
        private array $embedding,
        private string $name,
        private string $description,
        private ?string $category = null,
        private array $metadata = [],
        private ?\DateTimeImmutable $updatedAt = null,
    ) {
        $this->updatedAt = $updatedAt ?? new \DateTimeImmutable();
    }

    public function getProductId(): string // Changed from int to string
    {
        return $this->productId;
    }

    public function getEmbedding(): array
    {
        return $this->embedding;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setEmbedding(array $embedding): void
    {
        $this->embedding = $embedding;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function setCategory(?string $category): void
    {
        $this->category = $category;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function toArray(): array
    {
        return [
            'product_id' => $this->productId,
            'embedding' => $this->embedding,
            'name' => $this->name,
            'description' => $this->description,
            'category' => $this->category,
            'metadata' => $this->metadata,
            'updated_at' => $this->updatedAt->format(\DateTime::ATOM),
        ];
    }

    public static function fromArray(array $data): self
    {
        // MongoDB 2.x returns BSONArray objects instead of PHP arrays
        // Convert BSONArray to regular PHP array if needed
        $embedding = $data['embedding'];
        if ($embedding instanceof \MongoDB\Model\BSONArray) {
            $embedding = iterator_to_array($embedding);
        } elseif (is_object($embedding) && method_exists($embedding, 'getArrayCopy')) {
            $embedding = $embedding->getArrayCopy();
        } elseif (!is_array($embedding)) {
            $embedding = (array) $embedding;
        }

        // Same for metadata
        $metadata = $data['metadata'] ?? [];
        if ($metadata instanceof \MongoDB\Model\BSONArray || $metadata instanceof \MongoDB\Model\BSONDocument) {
            $metadata = iterator_to_array($metadata);
        }

        return new self(
            productId: $data['product_id'],
            embedding: $embedding,
            name: $data['name'],
            description: $data['description'],
            category: $data['category'] ?? null,
            metadata: $metadata,
            updatedAt: isset($data['updated_at'])
                ? new \DateTimeImmutable($data['updated_at'])
                : null
        );
    }

    /**
     * Validate embedding dimensions (should be 1536 for text-embedding-3-small).
     */
    public function isValidEmbedding(): bool
    {
        return 1536 === count($this->embedding);
    }
}
