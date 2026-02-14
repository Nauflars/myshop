<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserInteractionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM_Entity(repositoryClass: UserInteractionRepository::class)]
#[ORM\Table(name: 'user_interactions')]
class UserInteraction
{
    #[ORM\Id]
    #[ORM_GeneratedValue]
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

    #[ORM_Column(type: 'integer', nullable: true)]
    private ?int $productId = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM_Column(type: 'string', length: 36, unique: true)]
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

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getEventType(): string
    {
        return $this->eventType;
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
}
