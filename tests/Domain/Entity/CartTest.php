<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Entity\Cart;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

class CartTest extends TestCase
{
    private Cart $cart;
    private User $user;
    private Product $product1;
    private Product $product2;

    protected function setUp(): void
    {
        $this->user = new User(
            'Test User',
            new Email('test@example.com'),
            'password123'
        );

        $this->cart = new Cart($this->user);

        $this->product1 = new Product(
            'Product 1',
            'Description 1',
            new Money(1000, 'USD'),
            10,
            'Electronics'
        );

        $this->product2 = new Product(
            'Product 2',
            'Description 2',
            new Money(2000, 'USD'),
            5,
            'Books'
        );
    }

    public function testCartCreation(): void
    {
        $this->assertNotEmpty($this->cart->getId());
        $this->assertEquals($this->user, $this->cart->getUser());
        $this->assertTrue($this->cart->isEmpty());
        $this->assertEquals(0, $this->cart->getItemCount());
    }

    public function testAddProduct(): void
    {
        $this->cart->addProduct($this->product1, 2);

        $this->assertFalse($this->cart->isEmpty());
        $this->assertEquals(1, $this->cart->getItemCount());
        $this->assertEquals(2, $this->cart->getTotalQuantity());
    }

    public function testAddProductMultipleTimes(): void
    {
        $this->cart->addProduct($this->product1, 2);
        $this->cart->addProduct($this->product1, 3);

        $this->assertEquals(1, $this->cart->getItemCount());
        $this->assertEquals(5, $this->cart->getTotalQuantity());
    }

    public function testAddMultipleProducts(): void
    {
        $this->cart->addProduct($this->product1, 2);
        $this->cart->addProduct($this->product2, 1);

        $this->assertEquals(2, $this->cart->getItemCount());
        $this->assertEquals(3, $this->cart->getTotalQuantity());
    }

    public function testCalculateTotal(): void
    {
        $this->cart->addProduct($this->product1, 2); // 2 * 1000 = 2000
        $this->cart->addProduct($this->product2, 1); // 1 * 2000 = 2000

        $total = $this->cart->calculateTotal();
        $this->assertEquals(4000, $total->getAmountInCents());
        $this->assertEquals('USD', $total->getCurrency());
    }

    public function testCalculateTotalForEmptyCart(): void
    {
        $total = $this->cart->calculateTotal();
        $this->assertEquals(0, $total->getAmountInCents());
        $this->assertEquals('USD', $total->getCurrency());
    }

    public function testRemoveItemByProduct(): void
    {
        $this->cart->addProduct($this->product1, 2);
        $this->cart->addProduct($this->product2, 1);

        $this->assertEquals(2, $this->cart->getItemCount());

        $this->cart->removeItemByProduct($this->product1);

        $this->assertEquals(1, $this->cart->getItemCount());
        $this->assertEquals(1, $this->cart->getTotalQuantity());
    }

    public function testFindItemByProduct(): void
    {
        $this->cart->addProduct($this->product1, 2);

        $item = $this->cart->findItemByProduct($this->product1);
        $this->assertNotNull($item);
        $this->assertEquals(2, $item->getQuantity());

        $notFoundItem = $this->cart->findItemByProduct($this->product2);
        $this->assertNull($notFoundItem);
    }

    public function testClear(): void
    {
        $this->cart->addProduct($this->product1, 2);
        $this->cart->addProduct($this->product2, 1);

        $this->assertEquals(2, $this->cart->getItemCount());

        $this->cart->clear();

        $this->assertTrue($this->cart->isEmpty());
        $this->assertEquals(0, $this->cart->getItemCount());
        $this->assertEquals(0, $this->cart->getTotalQuantity());
    }

    public function testUpdatedAtIsSet(): void
    {
        $this->assertInstanceOf(\DateTimeImmutable::class, $this->cart->getUpdatedAt());
    }

    public function testUpdatedAtChangesOnModification(): void
    {
        $originalUpdatedAt = $this->cart->getUpdatedAt();
        sleep(1);
        $this->cart->addProduct($this->product1, 1);
        $this->assertGreaterThan($originalUpdatedAt, $this->cart->getUpdatedAt());
    }
}
