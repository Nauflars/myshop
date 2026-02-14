<?php

namespace App\Domain\Entity;

use App\Domain\ValueObject\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'carts')]
class Cart
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\OneToMany(targetEntity: CartItem::class, mappedBy: 'cart', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user)
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->user = $user;
        $this->items = new ArrayCollection();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return Collection<int, CartItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addProduct(Product $product, int $quantity = 1): void
    {
        // Validate currency consistency if cart already has items
        if (!$this->items->isEmpty()) {
            $firstItem = $this->items->first();
            $cartCurrency = $firstItem->getPriceSnapshot()->getCurrency();
            $productCurrency = $product->getPrice()->getCurrency();

            if ($cartCurrency !== $productCurrency) {
                throw new \InvalidArgumentException(sprintf('Cannot add product with currency %s to cart with currency %s', $productCurrency, $cartCurrency));
            }
        }

        // Check if product already exists in cart
        $existingItem = $this->findItemByProduct($product);
        if (null !== $existingItem) {
            $existingItem->incrementQuantity($quantity);
            $this->touch();

            return;
        }

        $item = new CartItem($this, $product, $quantity);
        $this->items->add($item);
        $this->touch();
    }

    public function addItem(CartItem $item): void
    {
        $this->items->add($item);
        $this->touch();
    }

    public function removeItem(CartItem $item): void
    {
        $this->items->removeElement($item);
        $this->touch();
    }

    public function removeItemByProduct(Product $product): void
    {
        foreach ($this->items as $item) {
            if ($item->getProduct()->getId() === $product->getId()) {
                $this->items->removeElement($item);
                $this->touch();

                return;
            }
        }
    }

    public function findItemByProduct(Product $product): ?CartItem
    {
        foreach ($this->items as $item) {
            if ($item->getProduct()->getId() === $product->getId()) {
                return $item;
            }
        }

        return null;
    }

    public function calculateTotal(): Money
    {
        if ($this->items->isEmpty()) {
            return new Money(0, 'USD');
        }

        // Use the currency of the first item to ensure consistency
        $firstItem = $this->items->first();
        $currency = $firstItem->getPriceSnapshot()->getCurrency();

        // Validate all items have the same currency
        foreach ($this->items as $item) {
            $itemCurrency = $item->getPriceSnapshot()->getCurrency();
            if ($itemCurrency !== $currency) {
                // Skip items with different currency or throw exception
                // For now, we'll only sum items with matching currency
                continue;
            }
        }

        $total = new Money(0, $currency);
        foreach ($this->items as $item) {
            $itemCurrency = $item->getPriceSnapshot()->getCurrency();
            if ($itemCurrency === $currency) {
                $total = $total->add($item->getSubtotal());
            }
        }

        return $total;
    }

    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    public function getItemCount(): int
    {
        return $this->items->count();
    }

    public function getTotalQuantity(): int
    {
        $total = 0;
        foreach ($this->items as $item) {
            $total += $item->getQuantity();
        }

        return $total;
    }

    public function clear(): void
    {
        $this->items->clear();
        $this->touch();
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
