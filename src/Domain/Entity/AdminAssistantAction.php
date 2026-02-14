<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * AdminAssistantAction - Audit log of actions performed via admin assistant.
 *
 * Part of spec-007: Admin Virtual Assistant
 * Tracks all administrative actions for compliance and security auditing
 */
#[ORM\Entity]
#[ORM\Table(name: 'admin_assistant_actions')]
#[ORM\Index(name: 'idx_admin_user', columns: ['admin_user_id'])]
#[ORM\Index(name: 'idx_action_type', columns: ['action_type'])]
#[ORM\Index(name: 'idx_performed_at', columns: ['performed_at'])]
class AdminAssistantAction
{
    // Product management actions
    public const ACTION_CREATE_PRODUCT = 'create_product';
    public const ACTION_UPDATE_PRODUCT = 'update_product';
    public const ACTION_DELETE_PRODUCT = 'delete_product';

    // Inventory management actions (spec-008 US2)
    public const ACTION_UPDATE_STOCK = 'update_stock';
    public const ACTION_QUERY_LOW_STOCK = 'query_low_stock';
    public const ACTION_QUERY_STOCK = 'query_stock';

    // Pricing management actions (spec-008)
    public const ACTION_UPDATE_PRICE = 'update_price';
    public const ACTION_QUERY_PRICE_HISTORY = 'query_price_history';

    // Analytics query actions
    public const ACTION_QUERY_SALES = 'query_sales';
    public const ACTION_QUERY_PRODUCT_STATS = 'query_product_stats';
    public const ACTION_QUERY_TOP_PRODUCTS = 'query_top_products';
    public const ACTION_QUERY_USER_STATS = 'query_user_stats';

    // Other actions
    public const ACTION_SEARCH_PRODUCT = 'search_product';
    public const ACTION_VIEW_CONVERSATION = 'view_conversation';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'admin_user_id', referencedColumnName: 'id', nullable: false)]
    private User $adminUser;

    #[ORM\Column(type: 'string', length: 50)]
    private string $actionType;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $performedAt;

    /**
     * Affected entity IDs (JSON array):
     * ["product_id" => "uuid", "order_id" => "uuid", ...]
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $affectedEntities = null;

    /**
     * Action parameters (JSON):
     * Input data used for the action (sanitized, no sensitive data)
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $actionParameters = null;

    /**
     * Action result (JSON):
     * Summary of what was accomplished
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $actionResult = null;

    #[ORM\Column(type: 'boolean')]
    private bool $success = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\ManyToOne(targetEntity: AdminAssistantConversation::class)]
    #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?AdminAssistantConversation $conversation = null;

    public function __construct(
        User $adminUser,
        string $actionType,
        ?AdminAssistantConversation $conversation = null,
    ) {
        $this->id = Uuid::v4()->toRfc4122();
        $this->adminUser = $adminUser;
        $this->actionType = $actionType;
        $this->performedAt = new \DateTimeImmutable();
        $this->conversation = $conversation;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAdminUser(): User
    {
        return $this->adminUser;
    }

    public function getActionType(): string
    {
        return $this->actionType;
    }

    public function getPerformedAt(): \DateTimeImmutable
    {
        return $this->performedAt;
    }

    public function getAffectedEntities(): ?array
    {
        return $this->affectedEntities;
    }

    public function setAffectedEntities(array $entities): void
    {
        $this->affectedEntities = $entities;
    }

    public function addAffectedEntity(string $type, string $id): void
    {
        if (null === $this->affectedEntities) {
            $this->affectedEntities = [];
        }

        $this->affectedEntities[$type] = $id;
    }

    public function getActionParameters(): ?array
    {
        return $this->actionParameters;
    }

    public function setActionParameters(array $parameters): void
    {
        $this->actionParameters = $parameters;
    }

    public function getActionResult(): ?array
    {
        return $this->actionResult;
    }

    public function setActionResult(array $result): void
    {
        $this->actionResult = $result;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function markAsSuccess(): void
    {
        $this->success = true;
        $this->errorMessage = null;
    }

    public function markAsFailed(string $errorMessage): void
    {
        $this->success = false;
        $this->errorMessage = $errorMessage;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(string $userAgent): void
    {
        // Truncate to fit column length
        $this->userAgent = substr($userAgent, 0, 255);
    }

    public function getConversation(): ?AdminAssistantConversation
    {
        return $this->conversation;
    }

    public function isProductAction(): bool
    {
        return in_array($this->actionType, [
            self::ACTION_CREATE_PRODUCT,
            self::ACTION_UPDATE_PRODUCT,
            self::ACTION_DELETE_PRODUCT,
        ], true);
    }

    public function isAnalyticsAction(): bool
    {
        return in_array($this->actionType, [
            self::ACTION_QUERY_SALES,
            self::ACTION_QUERY_PRODUCT_STATS,
            self::ACTION_QUERY_TOP_PRODUCTS,
            self::ACTION_QUERY_USER_STATS,
        ], true);
    }
}
