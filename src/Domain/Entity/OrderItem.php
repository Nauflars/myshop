<?php

namespace App\Domain\Entity;

use App\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'order_items')]
class OrderItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Order::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private Order $order;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column(type: 'string', length: 255)]
    private string $productName;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'integer')]
    private int $priceInCents;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    public function __construct(Order $order, Product $product, int $quantity, Money $price)
    {
        $this->id = Uuid::v4()->toRfc4122();
        $this->order = $order;
        $this->product = $product;
        $this->productName = $product->getName();
        $this->quantity = $quantity;
        $this->priceInCents = $price->getAmountInCents();
        $this->currency = $price->getCurrency();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getProductName(): string
    {
        return $this->productName;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getPrice(): Money
    {
        return new Money($this->priceInCents, $this->currency);
    }

    public function getSubtotal(): Money
    {
        return $this->getPrice()->multiply($this->quantity);
    }
}
