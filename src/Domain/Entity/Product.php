<?php

namespace App\Domain\Entity;

use App\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'products')]
#[ORM\Index(name: 'idx_category', columns: ['category'])]
#[ORM\Index(name: 'idx_stock', columns: ['stock'])]
class Product
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(type: 'integer')]
    private int $priceInCents;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(type: 'integer')]
    private int $stock;

    #[ORM\Column(type: 'string', length: 100)]
    private string $category;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $name,
        string $description,
        Money $price,
        int $stock,
        string $category
    ) {
        $this->id = Uuid::v4()->toRfc4122();
        $this->name = $name;
        $this->description = $description;
        $this->priceInCents = $price->getAmountInCents();
        $this->currency = $price->getCurrency();
        $this->stock = $stock;
        $this->category = $category;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
        $this->touch();
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
        $this->touch();
    }

    public function getPrice(): Money
    {
        return new Money($this->priceInCents, $this->currency);
    }

    public function setPrice(Money $price): void
    {
        $this->priceInCents = $price->getAmountInCents();
        $this->currency = $price->getCurrency();
        $this->touch();
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function setStock(int $stock): void
    {
        if ($stock < 0) {
            throw new \InvalidArgumentException('Stock cannot be negative');
        }
        $this->stock = $stock;
        $this->touch();
    }

    public function decrementStock(int $quantity): void
    {
        if ($quantity > $this->stock) {
            throw new \InvalidArgumentException('Insufficient stock');
        }
        $this->stock -= $quantity;
        $this->touch();
    }

    public function incrementStock(int $quantity): void
    {
        $this->stock += $quantity;
        $this->touch();
    }

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    public function isLowStock(int $threshold = 10): bool
    {
        return $this->stock > 0 && $this->stock < $threshold;
    }

    public function getCategory(): string
    {
        return $this->category;
    }

    public function setCategory(string $category): void
    {
        $this->category = $category;
        $this->touch();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
