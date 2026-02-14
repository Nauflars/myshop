<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Entity\Cart;
use App\Domain\Entity\CartItem;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

class CartItemTest extends TestCase
{
    private Cart $cart;
    private Product $product;

    protected function setUp(): void
    {
        $user = new User('Test User', new Email('test@example.com'), 'hash123');
        $this->cart = new Cart($user);
        $this->product = new Product('Test Product', 'A test product description', new Money(1000, 'USD'), 10, 'Electronics');
    }

    public function testCartItemCreation(): void
    {
        $cartItem = new CartItem($this->cart, $this->product, 2);

        $this->assertNotEmpty($cartItem->getId());
        $this->assertSame($this->cart, $cartItem->getCart());
        $this->assertSame($this->product, $cartItem->getProduct());
        $this->assertEquals(2, $cartItem->getQuantity());
    }

    public function testPriceSnapshot(): void
    {
        $cartItem = new CartItem($this->cart, $this->product, 1);

        $priceSnapshot = $cartItem->getPriceSnapshot();
        $this->assertEquals(1000, $priceSnapshot->getAmountInCents());
        $this->assertEquals('USD', $priceSnapshot->getCurrency());
    }

    public function testGetSubtotal(): void
    {
        $cartItem = new CartItem($this->cart, $this->product, 3);

        $subtotal = $cartItem->getSubtotal();
        $this->assertEquals(3000, $subtotal->getAmountInCents());
        $this->assertEquals('USD', $subtotal->getCurrency());
    }

    public function testSetQuantity(): void
    {
        $cartItem = new CartItem($this->cart, $this->product, 1);

        $cartItem->setQuantity(5);
        $this->assertEquals(5, $cartItem->getQuantity());
    }

    public function testIncrementQuantity(): void
    {
        $cartItem = new CartItem($this->cart, $this->product, 2);

        $cartItem->incrementQuantity(3);
        $this->assertEquals(5, $cartItem->getQuantity());
    }

    public function testDecrementQuantity(): void
    {
        $cartItem = new CartItem($this->cart, $this->product, 5);

        $cartItem->decrementQuantity(2);
        $this->assertEquals(3, $cartItem->getQuantity());
    }

    public function testQuantityCannotBeZero(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than zero');

        new CartItem($this->cart, $this->product, 0);
    }

    public function testQuantityCannotBeNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new CartItem($this->cart, $this->product, -1);
    }

    public function testSetQuantityCannotBeZero(): void
    {
        $cartItem = new CartItem($this->cart, $this->product, 5);

        $this->expectException(\InvalidArgumentException::class);
        $cartItem->setQuantity(0);
    }
}
