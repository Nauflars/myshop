<?php

namespace App\Domain\Entity;

use App\Domain\ValueObject\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'cart_items')]
class CartItem
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Cart::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false)]
    private Cart $cart;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Product $product;

    #[ORM\Column(type: 'integer')]
    private int $quantity;

    #[ORM\Column(type: 'integer')]
    private int $priceSnapshotInCents;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency;

    public function __construct(Cart $cart, Product $product, int $quantity)
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero');
        }

        $this->id = Uuid::v4()->toRfc4122();
        $this->cart = $cart;
        $this->product = $product;
        $this->quantity = $quantity;
        
        // Snapshot the price at the time of adding to cart
        $price = $product->getPrice();
        $this->priceSnapshotInCents = $price->getAmountInCents();
        $this->currency = $price->getCurrency();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCart(): Cart
    {
        return $this->cart;
    }

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than zero');
        }
        $this->quantity = $quantity;
    }

    public function getPriceSnapshot(): Money
    {
        return new Money($this->priceSnapshotInCents, $this->currency);
    }

    public function getSubtotal(): Money
    {
        return $this->getPriceSnapshot()->multiply($this->quantity);
    }

    public function incrementQuantity(int $amount = 1): void
    {
        $this->quantity += $amount;
    }

    public function decrementQuantity(int $amount = 1): void
    {
        $newQuantity = $this->quantity - $amount;
        if ($newQuantity < 0) {
            throw new \InvalidArgumentException('Quantity cannot be negative');
        }
        $this->quantity = $newQuantity;
    }
}
