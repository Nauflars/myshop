<?php

declare(strict_types=1);

namespace App\Entity;

use App\Domain\Entity\User;
use App\Repository\SearchHistoryRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: SearchHistoryRepository::class)]
#[ORM\Table(name: 'search_history')]
#[ORM\Index(columns: ['user_id'], name: 'idx_search_history_user')]
#[ORM\Index(columns: ['created_at'], name: 'idx_search_history_created')]
class SearchHistory
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private User $user;

    #[ORM\Column(type: Types::STRING, length: 500)]
    private string $query;

    #[ORM\Column(type: Types::STRING, length: 20)]
    private string $mode;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $category = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        User $user,
        string $query,
        string $mode = 'semantic',
        ?string $category = null,
    ) {
        $this->id = Uuid::v4()->toRfc4122();
        $this->user = $user;
        $this->query = $query;
        $this->mode = $mode;
        $this->category = $category;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getQuery(): string
    {
        return $this->query;
    }

    public function getMode(): string
    {
        return $this->mode;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
