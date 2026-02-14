<?php

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'conversation_messages')]
#[ORM\Index(name: 'idx_conversation_timestamp', columns: ['conversation_id', 'timestamp'])]
class ConversationMessage
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(name: 'conversation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\Column(type: 'string', length: 20)]
    private string $role;

    #[ORM\Column(type: 'text')]
    private string $content;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $toolCalls = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $timestamp;

    public function __construct(
        Conversation $conversation,
        string $role,
        string $content,
        ?array $toolCalls = null,
    ) {
        $this->id = Uuid::v4()->toRfc4122();
        $this->conversation = $conversation;
        $this->role = $role;
        $this->content = $content;
        $this->toolCalls = $toolCalls;
        $this->timestamp = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function getRole(): string
    {
        return $this->role;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getToolCalls(): ?array
    {
        return $this->toolCalls;
    }

    public function setToolCalls(?array $toolCalls): void
    {
        $this->toolCalls = $toolCalls;
    }

    public function getTimestamp(): \DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function isFromUser(): bool
    {
        return 'user' === $this->role;
    }

    public function isFromAssistant(): bool
    {
        return 'assistant' === $this->role;
    }

    public function isFromSystem(): bool
    {
        return 'system' === $this->role;
    }
}
