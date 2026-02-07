<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Abstract base class for conversation context
 * 
 * Represents the contextual information stored between conversation messages
 * to enable natural follow-up questions and multi-step interactions.
 */
abstract class ConversationContext
{
    public function __construct(
        protected string $userId,
        protected string $flow,
        protected ?string $lastTool,
        protected int $turnCount,
        protected DateTimeImmutable $createdAt,
        protected DateTimeImmutable $updatedAt
    ) {
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function getFlow(): string
    {
        return $this->flow;
    }

    public function setFlow(string $flow): void
    {
        $this->flow = $flow;
        $this->touch();
    }

    public function getLastTool(): ?string
    {
        return $this->lastTool;
    }

    public function setLastTool(?string $lastTool): void
    {
        $this->lastTool = $lastTool;
        $this->touch();
    }

    public function getTurnCount(): int
    {
        return $this->turnCount;
    }

    public function incrementTurnCount(): void
    {
        $this->turnCount++;
        $this->touch();
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Update the updatedAt timestamp to current time
     */
    protected function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Convert context to array for storage
     */
    abstract public function toArray(): array;

    /**
     * Create context instance from stored array
     */
    abstract public static function fromArray(array $data): self;

    /**
     * Get a human-readable summary for AI prompt injection
     */
    abstract public function toPromptContext(): string;
}
