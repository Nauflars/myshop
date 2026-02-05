<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Entity\Cart;
use App\Domain\Entity\CartItem;
use App\Domain\Entity\Order;
use App\Domain\Entity\OrderItem;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User('Test User', new Email('test@example.com'), 'hash123');
    }

    public function testOrderCreation(): void
    {
        $order = new Order($this->user);
        
        $this->assertNotEmpty($order->getId());
        $this->assertNotEmpty($order->getOrderNumber());
        $this->assertSame($this->user, $order->getUser());
        $this->assertEquals(Order::STATUS_PENDING, $order->getStatus());
        $this->assertCount(0, $order->getItems());
        $this->assertEquals(0, $order->getTotal()->getAmountInCents());
    }

    public function testOrderCreationWithCustomOrderNumber(): void
    {
        $order = new Order($this->user, 'ORD-12345');
        
        $this->assertEquals('ORD-12345', $order->getOrderNumber());
    }

    public function testAddItem(): void
    {
        $order = new Order($this->user);
        $product = new Product('Test Product', 'Description', new Money(1000, 'USD'), 10, 'Electronics');
        $orderItem = new OrderItem($order, $product, 2, $product->getPrice());
        
        $order->addItem($orderItem);
        
        $this->assertCount(1, $order->getItems());
        $this->assertEquals(2000, $order->getTotal()->getAmountInCents());
    }

    public function testRemoveItem(): void
    {
        $order = new Order($this->user);
        $product = new Product('Test Product', 'Description', new Money(1000, 'USD'), 10, 'Electronics');
        $orderItem = new OrderItem($order, $product, 2, $product->getPrice());
        
        $order->addItem($orderItem);
        $order->removeItem($orderItem);
        
        $this->assertCount(0, $order->getItems());
        $this->assertEquals(0, $order->getTotal()->getAmountInCents());
    }

    public function testCreateFromCart(): void
    {
        $cart = new Cart($this->user);
        $product1 = new Product('Product 1', 'Description 1', new Money(1000, 'USD'), 10, 'Electronics');
        $product2 = new Product('Product 2', 'Description 2', new Money(2000, 'USD'), 5, 'Books');
        
        $cart->addProduct($product1, 2);
        $cart->addProduct($product2, 1);
        
        $order = Order::createFromCart($cart);
        
        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame($this->user, $order->getUser());
        $this->assertCount(2, $order->getItems());
        $this->assertEquals(4000, $order->getTotal()->getAmountInCents()); // 2*1000 + 1*2000
    }

    public function testConfirm(): void
    {
        $order = new Order($this->user);
        
        $order->confirm();
        $this->assertEquals(Order::STATUS_CONFIRMED, $order->getStatus());
    }

    public function testShip(): void
    {
        $order = new Order($this->user);
        $order->confirm();
        
        $order->ship();
        $this->assertEquals(Order::STATUS_SHIPPED, $order->getStatus());
    }

    public function testDeliver(): void
    {
        $order = new Order($this->user);
        $order->confirm();
        $order->ship();
        
        $order->deliver();
        $this->assertEquals(Order::STATUS_DELIVERED, $order->getStatus());
    }

    public function testCancel(): void
    {
        $order = new Order($this->user);
        
        $order->cancel();
        $this->assertEquals(Order::STATUS_CANCELLED, $order->getStatus());
    }

    public function testIsPending(): void
    {
        $order = new Order($this->user);
        $this->assertTrue($order->isPending());
        
        $order->confirm();
        $this->assertFalse($order->isPending());
    }

    public function testIsConfirmed(): void
    {
        $order = new Order($this->user);
        $order->confirm();
        
        $this->assertTrue($order->isConfirmed());
    }

    public function testIsCancelled(): void
    {
        $order = new Order($this->user);
        $order->cancel();
        
        $this->assertTrue($order->isCancelled());
    }

    public function testGetCreatedAt(): void
    {
        $order = new Order($this->user);
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->getCreatedAt());
    }

    public function testGetUpdatedAt(): void
    {
        $order = new Order($this->user);
        
        $this->assertInstanceOf(\DateTimeImmutable::class, $order->getUpdatedAt());
    }
}
