<?php

namespace App\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'conversations')]
#[ORM\Index(name: 'idx_user_created', columns: ['user_id', 'created_at'])]
class Conversation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'string', length: 255)]
    private string $title;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: ConversationMessage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['timestamp' => 'ASC'])]
    private Collection $messages;

    public function __construct(User $user, string $title = 'Nueva conversación')
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->user = $user;
        $this->title = $title;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->messages = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return Collection<int, ConversationMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(ConversationMessage $message): void
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $this->touch();
        }
    }

    public function removeMessage(ConversationMessage $message): void
    {
        $this->messages->removeElement($message);
        $this->touch();
    }

    public function getLastMessage(): ?ConversationMessage
    {
        if ($this->messages->isEmpty()) {
            return null;
        }

        return $this->messages->last();
    }

    public function getMessageCount(): int
    {
        return $this->messages->count();
    }

    public function generateTitle(): string
    {
        $firstUserMessage = null;
        foreach ($this->messages as $message) {
            if ('user' === $message->getRole()) {
                $firstUserMessage = $message;
                break;
            }
        }

        if (null === $firstUserMessage) {
            return 'Nueva conversación';
        }

        $content = $firstUserMessage->getContent();
        // Truncate to 50 characters and add ellipsis if needed
        if (mb_strlen($content) > 50) {
            return mb_substr($content, 0, 47).'...';
        }

        return $content;
    }
}
