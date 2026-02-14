<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\ValueObject\EventType;
use App\Repository\UserInteractionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserInteractionRepository::class)]
#[ORM\Table(name: 'user_interactions')]
class UserInteraction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $userId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $eventType;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $occurredAt;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $searchPhrase = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $productId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'string', length: 36, unique: true)]
    private string $messageId;

    public function __construct(
        int $userId,
        string $eventType,
        \DateTimeImmutable $occurredAt,
        ?string $searchPhrase = null,
        ?int $productId = null,
    ) {
        $this->userId = $userId;
        $this->eventType = $eventType;
        $this->occurredAt = $occurredAt;
        $this->searchPhrase = $searchPhrase;
        $this->productId = $productId;
        $this->createdAt = new \DateTimeImmutable();
        $this->messageId = \Symfony\Component\Uid\Uuid::v4()->toRfc4122();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getEventType(): EventType
    {
        return EventType::from($this->eventType);
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    public function getSearchPhrase(): ?string
    {
        return $this->searchPhrase;
    }

    public function getProductId(): ?int
    {
        return $this->productId;
    }

    public function getMetadata(): ?array
    {
        return [];
    }

    public function markAsProcessedToQueue(): void
    {
        // Marker method - can be used to track if event was published
    }
}
