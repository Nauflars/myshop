<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * AdminAssistantMessage - Individual message within admin assistant conversation.
 *
 * Part of spec-007: Admin Virtual Assistant
 * Stores messages from both admin user and AI assistant
 */
#[ORM\Entity]
#[ORM\Table(name: 'admin_assistant_messages')]
#[ORM\Index(name: 'idx_conversation', columns: ['conversation_id'])]
#[ORM\Index(name: 'idx_sent_at', columns: ['sent_at'])]
class AdminAssistantMessage
{
    public const SENDER_ADMIN = 'admin';
    public const SENDER_ASSISTANT = 'assistant';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: AdminAssistantConversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private AdminAssistantConversation $conversation;

    #[ORM\Column(type: 'string', length: 20)]
    private string $sender;

    #[ORM\Column(type: 'text')]
    private string $messageText;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $sentAt;

    /**
     * Associated tool invocations (JSON array):
     * [
     *   {"tool": "AdminCreateProductTool", "parameters": {...}, "result": {...}},
     *   ...
     * ]
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $toolInvocations = null;

    /**
     * @var array|null Error information if message processing failed
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $errorInfo = null;

    public function __construct(
        AdminAssistantConversation $conversation,
        string $sender,
        string $messageText,
    ) {
        if (!in_array($sender, [self::SENDER_ADMIN, self::SENDER_ASSISTANT], true)) {
            throw new \InvalidArgumentException(sprintf('Invalid sender "%s". Must be "%s" or "%s"', $sender, self::SENDER_ADMIN, self::SENDER_ASSISTANT));
        }

        $this->id = Uuid::v4()->toRfc4122();
        $this->conversation = $conversation;
        $this->sender = $sender;
        $this->messageText = $messageText;
        $this->sentAt = new \DateTimeImmutable();

        $conversation->addMessage($this);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getConversation(): AdminAssistantConversation
    {
        return $this->conversation;
    }

    public function getSender(): string
    {
        return $this->sender;
    }

    public function isFromAdmin(): bool
    {
        return self::SENDER_ADMIN === $this->sender;
    }

    public function isFromAssistant(): bool
    {
        return self::SENDER_ASSISTANT === $this->sender;
    }

    public function getMessageText(): string
    {
        return $this->messageText;
    }

    public function getSentAt(): \DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function getToolInvocations(): ?array
    {
        return $this->toolInvocations;
    }

    public function addToolInvocation(string $toolName, array $parameters, mixed $result): void
    {
        if (null === $this->toolInvocations) {
            $this->toolInvocations = [];
        }

        $this->toolInvocations[] = [
            'tool' => $toolName,
            'parameters' => $parameters,
            'result' => $result,
            'invoked_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    public function hasToolInvocations(): bool
    {
        return !empty($this->toolInvocations);
    }

    public function getErrorInfo(): ?array
    {
        return $this->errorInfo;
    }

    public function setErrorInfo(string $errorType, string $errorMessage, ?array $context = null): void
    {
        $this->errorInfo = [
            'type' => $errorType,
            'message' => $errorMessage,
            'context' => $context,
            'occurred_at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
    }

    public function hasError(): bool
    {
        return null !== $this->errorInfo;
    }
}
