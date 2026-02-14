<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Admin conversation context.
 *
 * Tracks conversation state for admin assistant interactions
 * to enable multi-step operations with context retention.
 *
 * Context Attributes:
 * - adminId: Unique admin user identifier
 * - flow: Current admin flow (inventory_management, order_reviews, user_management, analytics)
 * - activeEntities: Array of entity IDs admin is working with (products, orders, users)
 * - timePeriod: Time period for analytics queries (today, this_week, this_month, custom)
 * - pendingActions: Array of pending operations awaiting confirmation
 * - lastTool: Last AI tool executed (GetLowStockProducts, UpdateProductStock, etc.)
 */
class AdminConversationContext extends ConversationContext
{
    private const DEFAULT_TIME_PERIOD = 'today';

    public function __construct(
        string $userId, // adminId
        string $flow,
        ?string $lastTool,
        int $turnCount,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
        private array $activeEntities = [],
        private string $timePeriod = self::DEFAULT_TIME_PERIOD,
        private array $pendingActions = [],
    ) {
        parent::__construct($userId, $flow, $lastTool, $turnCount, $createdAt, $updatedAt);
    }

    public function getAdminId(): string
    {
        return $this->userId;
    }

    public function getActiveEntities(): array
    {
        return $this->activeEntities;
    }

    public function setActiveEntities(array $entities): void
    {
        $this->activeEntities = $entities;
        $this->touch();
    }

    public function addActiveEntity(string $entityType, int $entityId): void
    {
        if (!isset($this->activeEntities[$entityType])) {
            $this->activeEntities[$entityType] = [];
        }

        if (!in_array($entityId, $this->activeEntities[$entityType], true)) {
            $this->activeEntities[$entityType][] = $entityId;
            $this->touch();
        }
    }

    public function clearActiveEntities(?string $entityType = null): void
    {
        if (null === $entityType) {
            $this->activeEntities = [];
        } else {
            unset($this->activeEntities[$entityType]);
        }
        $this->touch();
    }

    public function getTimePeriod(): string
    {
        return $this->timePeriod;
    }

    public function setTimePeriod(string $period): void
    {
        $this->timePeriod = $period;
        $this->touch();
    }

    public function getPendingActions(): array
    {
        return $this->pendingActions;
    }

    public function addPendingAction(string $actionType, array $actionData): void
    {
        $this->pendingActions[] = [
            'type' => $actionType,
            'data' => $actionData,
            'addedAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339),
        ];
        $this->touch();
    }

    public function clearPendingActions(): void
    {
        $this->pendingActions = [];
        $this->touch();
    }

    public function hasPendingAction(string $actionType): bool
    {
        foreach ($this->pendingActions as $action) {
            if ($action['type'] === $actionType) {
                return true;
            }
        }

        return false;
    }

    public function getPendingAction(string $actionType): ?array
    {
        foreach ($this->pendingActions as $action) {
            if ($action['type'] === $actionType) {
                return $action;
            }
        }

        return null;
    }

    public function removePendingAction(string $actionType): void
    {
        $this->pendingActions = array_filter(
            $this->pendingActions,
            fn ($action) => $action['type'] !== $actionType
        );
        $this->pendingActions = array_values($this->pendingActions); // Re-index array
        $this->touch();
    }

    public function toArray(): array
    {
        return [
            'adminId' => $this->userId,
            'flow' => $this->flow,
            'lastTool' => $this->lastTool,
            'turnCount' => $this->turnCount,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::RFC3339),
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::RFC3339),
            'activeEntities' => $this->activeEntities,
            'timePeriod' => $this->timePeriod,
            'pendingActions' => $this->pendingActions,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['adminId'] ?? $data['userId'], // Support both keys for backward compatibility
            flow: $data['flow'],
            lastTool: $data['lastTool'] ?? null,
            turnCount: $data['turnCount'] ?? 1,
            createdAt: new \DateTimeImmutable($data['createdAt']),
            updatedAt: new \DateTimeImmutable($data['updatedAt']),
            activeEntities: $data['activeEntities'] ?? [],
            timePeriod: $data['timePeriod'] ?? self::DEFAULT_TIME_PERIOD,
            pendingActions: $data['pendingActions'] ?? []
        );
    }

    public function toPromptContext(): string
    {
        $context = "Admin Context:\n";
        $context .= "- Admin ID: {$this->userId}\n";
        $context .= "- Current Flow: {$this->flow}\n";
        $context .= "- Turn Count: {$this->turnCount}\n";

        if ($this->lastTool) {
            $context .= "- Last Tool Used: {$this->lastTool}\n";
        }

        if (!empty($this->activeEntities)) {
            $context .= "- Active Entities:\n";
            foreach ($this->activeEntities as $type => $ids) {
                $idList = implode(', ', $ids);
                $context .= "  * {$type}: [{$idList}]\n";
            }
        }

        $context .= "- Time Period: {$this->timePeriod}\n";

        if (!empty($this->pendingActions)) {
            $context .= "- Pending Actions:\n";
            foreach ($this->pendingActions as $action) {
                $context .= "  * {$action['type']} (added at {$action['addedAt']})\n";
            }
        }

        return $context;
    }

    /**
     * Create a fresh context for a new admin conversation.
     */
    public static function createFresh(string $adminId): self
    {
        $now = new \DateTimeImmutable();

        return new self(
            userId: $adminId,
            flow: 'general',
            lastTool: null,
            turnCount: 0,
            createdAt: $now,
            updatedAt: $now,
            activeEntities: [],
            timePeriod: self::DEFAULT_TIME_PERIOD,
            pendingActions: []
        );
    }
}
