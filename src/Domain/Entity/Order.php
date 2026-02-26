<?php

namespace App\Domain\Entity;

use App\Domain\ValueObject\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'orders')]
#[ORM\Index(name: 'idx_order_number', columns: ['order_number'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
class Order
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_CONFIRMED = 'CONFIRMED';
    public const STATUS_SHIPPED = 'SHIPPED';
    public const STATUS_DELIVERED = 'DELIVERED';
    public const STATUS_CANCELLED = 'CANCELLED';

    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $orderNumber;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column(type: 'integer')]
    private int $totalInCents;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $shippingAddress = null;

    public function __construct(User $user, ?string $orderNumber = null)
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->orderNumber = $orderNumber ?? $this->generateOrderNumber();
        $this->user = $user;
        $this->items = new ArrayCollection();
        $this->totalInCents = 0;
        $this->currency = 'USD';
        $this->status = self::STATUS_PENDING;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderNumber(): string
    {
        return $this->orderNumber;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): void
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $this->recalculateTotal();
        }
    }

    public function removeItem(OrderItem $item): void
    {
        $this->items->removeElement($item);
        $this->recalculateTotal();
    }

    public function getTotal(): Money
    {
        return new Money($this->totalInCents, $this->currency);
    }

    private function recalculateTotal(): void
    {
        $total = new Money(0, 'USD');
        foreach ($this->items as $item) {
            $total = $total->add($item->getSubtotal());
        }
        $this->totalInCents = $total->getAmountInCents();
        $this->currency = $total->getCurrency();
        $this->touch();
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $validStatuses = [
            self::STATUS_PENDING,
            self::STATUS_CONFIRMED,
            self::STATUS_SHIPPED,
            self::STATUS_DELIVERED,
            self::STATUS_CANCELLED,
        ];

        if (!in_array($status, $validStatuses, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid status: %s', $status));
        }

        $this->status = $status;
        $this->touch();
    }

    public function confirm(): void
    {
        if (self::STATUS_PENDING !== $this->status) {
            throw new \LogicException('Only pending orders can be confirmed');
        }
        $this->setStatus(self::STATUS_CONFIRMED);
    }

    public function ship(): void
    {
        if (self::STATUS_CONFIRMED !== $this->status) {
            throw new \LogicException('Only confirmed orders can be shipped');
        }
        $this->setStatus(self::STATUS_SHIPPED);
    }

    public function deliver(): void
    {
        if (self::STATUS_SHIPPED !== $this->status) {
            throw new \LogicException('Only shipped orders can be delivered');
        }
        $this->setStatus(self::STATUS_DELIVERED);
    }

    public function cancel(): void
    {
        if (self::STATUS_DELIVERED === $this->status) {
            throw new \LogicException('Delivered orders cannot be cancelled');
        }
        $this->setStatus(self::STATUS_CANCELLED);
    }

    public function isPending(): bool
    {
        return self::STATUS_PENDING === $this->status;
    }

    public function isConfirmed(): bool
    {
        return self::STATUS_CONFIRMED === $this->status;
    }

    public function isShipped(): bool
    {
        return self::STATUS_SHIPPED === $this->status;
    }

    public function isDelivered(): bool
    {
        return self::STATUS_DELIVERED === $this->status;
    }

    public function isCancelled(): bool
    {
        return self::STATUS_CANCELLED === $this->status;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getShippingAddress(): ?array
    {
        return $this->shippingAddress;
    }

    public function setShippingAddress(?array $shippingAddress): void
    {
        $this->shippingAddress = $shippingAddress;
        $this->touch();
    }

    private function touch(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    private function generateOrderNumber(): string
    {
        return 'ORD-'.date('Ymd').'-'.strtoupper(substr(Uuid::v4()->toRfc4122(), 0, 8));
    }

    public static function createFromCart(Cart $cart): self
    {
        if ($cart->isEmpty()) {
            throw new \InvalidArgumentException('Cannot create order from empty cart');
        }

        $order = new self($cart->getUser());

        foreach ($cart->getItems() as $cartItem) {
            $orderItem = new OrderItem(
                $order,
                $cartItem->getProduct(),
                $cartItem->getQuantity(),
                $cartItem->getPriceSnapshot()
            );
            $order->addItem($orderItem);
        }

        return $order;
    }
}
