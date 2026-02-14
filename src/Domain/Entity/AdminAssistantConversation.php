<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * AdminAssistantConversation - Represents a chat session between admin and assistant.
 *
 * Part of spec-007: Admin Virtual Assistant
 * Each conversation is isolated per admin user session
 */
#[ORM\Entity]
#[ORM\Table(name: 'admin_assistant_conversations')]
#[ORM\Index(name: 'idx_admin_user', columns: ['admin_user_id'])]
#[ORM\Index(name: 'idx_started_at', columns: ['started_at'])]
class AdminAssistantConversation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'admin_user_id', referencedColumnName: 'id', nullable: false)]
    private User $adminUser;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $startedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    /**
     * @var Collection<int, AdminAssistantMessage>
     */
    #[ORM\OneToMany(
        mappedBy: 'conversation',
        targetEntity: AdminAssistantMessage::class,
        cascade: ['persist', 'remove'],
        orphanRemoval: true
    )]
    #[ORM\OrderBy(['sentAt' => 'ASC'])]
    private Collection $messages;

    /**
     * Conversational context state (JSON):
     * - current_product: string|null (last mentioned product name)
     * - current_user: string|null (last mentioned customer)
     * - current_period: string|null (time period for analytics)
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $contextState = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $sessionId = null;

    public function __construct(User $adminUser, ?string $sessionId = null)
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->adminUser = $adminUser;
        $this->startedAt = new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
        $this->sessionId = $sessionId;
        $this->contextState = [
            'current_product' => null,
            'current_user' => null,
            'current_period' => null,
        ];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getAdminUser(): User
    {
        return $this->adminUser;
    }

    public function getStartedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function end(): void
    {
        $this->endedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, AdminAssistantMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(AdminAssistantMessage $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
        }

        return $this;
    }

    public function getContextState(): ?array
    {
        return $this->contextState;
    }

    public function updateContext(string $key, mixed $value): void
    {
        if (null === $this->contextState) {
            $this->contextState = [];
        }

        $this->contextState[$key] = $value;
    }

    public function getContextValue(string $key): mixed
    {
        return $this->contextState[$key] ?? null;
    }

    public function clearContext(): void
    {
        $this->contextState = [
            'current_product' => null,
            'current_user' => null,
            'current_period' => null,
        ];
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function isActive(): bool
    {
        return null === $this->endedAt;
    }

    public function getMessageCount(): int
    {
        return $this->messages->count();
    }

    public function getLastMessage(): ?AdminAssistantMessage
    {
        if ($this->messages->isEmpty()) {
            return null;
        }

        return $this->messages->last();
    }
}
