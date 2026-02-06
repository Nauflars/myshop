<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * UnansweredQuestion - Domain Entity
 * 
 * Represents a chatbot question that could not be answered by the AI agent.
 * Used for continuous improvement and identifying missing capabilities.
 * 
 * Spec: 006-unanswered-questions-admin
 */
#[ORM\Entity(repositoryClass: 'App\Infrastructure\Repository\UnansweredQuestionRepository')]
#[ORM\Table(name: 'unanswered_questions')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
#[ORM\Index(columns: ['reason_category'], name: 'idx_reason')]
#[ORM\Index(columns: ['asked_at'], name: 'idx_asked_at')]
class UnansweredQuestion
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $questionText;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $userRole;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $askedAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $conversationId = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $reasonCategory;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adminNotes = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $reviewedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $resolvedAt = null;

    public const REASON_MISSING_TOOL = 'missing_tool';
    public const REASON_UNSUPPORTED_REQUEST = 'unsupported_request';
    public const REASON_TOOL_ERROR = 'tool_error';
    public const REASON_INSUFFICIENT_DATA = 'insufficient_data';

    public const STATUS_NEW = 'new';
    public const STATUS_REVIEWED = 'reviewed';
    public const STATUS_PLANNED = 'planned';
    public const STATUS_RESOLVED = 'resolved';

    public function __construct(
        string $questionText,
        ?User $user,
        string $userRole,
        string $reasonCategory,
        ?string $conversationId = null
    ) {
        $this->questionText = $questionText;
        $this->user = $user;
        $this->userRole = $userRole;
        $this->reasonCategory = $reasonCategory;
        $this->conversationId = $conversationId;
        $this->askedAt = new \DateTime();
        $this->status = self::STATUS_NEW;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuestionText(): string
    {
        return $this->questionText;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function getUserRole(): string
    {
        return $this->userRole;
    }

    public function getAskedAt(): \DateTimeInterface
    {
        return $this->askedAt;
    }

    public function getConversationId(): ?string
    {
        return $this->conversationId;
    }

    public function getReasonCategory(): string
    {
        return $this->reasonCategory;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, [
            self::STATUS_NEW,
            self::STATUS_REVIEWED,
            self::STATUS_PLANNED,
            self::STATUS_RESOLVED
        ])) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }

        $this->status = $status;

        // Auto-set timestamps based on status changes
        if ($status === self::STATUS_REVIEWED && $this->reviewedAt === null) {
            $this->reviewedAt = new \DateTime();
        }
        if ($status === self::STATUS_RESOLVED && $this->resolvedAt === null) {
            $this->resolvedAt = new \DateTime();
        }

        return $this;
    }

    public function getAdminNotes(): ?string
    {
        return $this->adminNotes;
    }

    public function setAdminNotes(?string $adminNotes): self
    {
        $this->adminNotes = $adminNotes;
        return $this;
    }

    public function getReviewedAt(): ?\DateTimeInterface
    {
        return $this->reviewedAt;
    }

    public function getResolvedAt(): ?\DateTimeInterface
    {
        return $this->resolvedAt;
    }

    public function isNew(): bool
    {
        return $this->status === self::STATUS_NEW;
    }

    public function isReviewed(): bool
    {
        return $this->status === self::STATUS_REVIEWED;
    }

    public function isPlanned(): bool
    {
        return $this->status === self::STATUS_PLANNED;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public static function getValidReasons(): array
    {
        return [
            self::REASON_MISSING_TOOL,
            self::REASON_UNSUPPORTED_REQUEST,
            self::REASON_TOOL_ERROR,
            self::REASON_INSUFFICIENT_DATA,
        ];
    }

    public static function getValidStatuses(): array
    {
        return [
            self::STATUS_NEW,
            self::STATUS_REVIEWED,
            self::STATUS_PLANNED,
            self::STATUS_RESOLVED,
        ];
    }
}
