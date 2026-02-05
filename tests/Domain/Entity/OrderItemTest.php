<?php

namespace App\Tests\Domain\Entity;

use App\Domain\Entity\Order;
use App\Domain\Entity\OrderItem;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

class OrderItemTest extends TestCase
{
    private Order $order;
    private Product $product;

    protected function setUp(): void
    {
        $user = new User('Test User', new Email('test@example.com'), 'hash123');
        $this->order = new Order($user);
        $this->product = new Product('Test Product', 'A test product description', new Money(1500, 'USD'), 10, 'Electronics');
    }

    public function testOrderItemCreation(): void
    {
        $price = new Money(1500, 'USD');
        $orderItem = new OrderItem($this->order, $this->product, 2, $price);
        
        $this->assertNotEmpty($orderItem->getId());
        $this->assertSame($this->order, $orderItem->getOrder());
        $this->assertSame($this->product, $orderItem->getProduct());
        $this->assertEquals('Test Product', $orderItem->getProductName());
        $this->assertEquals(2, $orderItem->getQuantity());
    }

    public function testGetPrice(): void
    {
        $price = new Money(1500, 'USD');
        $orderItem = new OrderItem($this->order, $this->product, 1, $price);
        
        $itemPrice = $orderItem->getPrice();
        $this->assertEquals(1500, $itemPrice->getAmountInCents());
        $this->assertEquals('USD', $itemPrice->getCurrency());
    }

    public function testGetSubtotal(): void
    {
        $price = new Money(1500, 'USD');
        $orderItem = new OrderItem($this->order, $this->product, 3, $price);
        
        $subtotal = $orderItem->getSubtotal();
        $this->assertEquals(4500, $subtotal->getAmountInCents()); // 1500 * 3
        $this->assertEquals('USD', $subtotal->getCurrency());
    }

    public function testProductNameSnapshot(): void
    {
        $price = new Money(1500, 'USD');
        $orderItem = new OrderItem($this->order, $this->product, 1, $price);
        
        // Even if product name changes, order item should keep snapshot
        $this->assertEquals('Test Product', $orderItem->getProductName());
    }

    public function testPriceSnapshot(): void
    {
        // Create order item with different price than current product price
        $snapshotPrice = new Money(1200, 'USD');
        $orderItem = new OrderItem($this->order, $this->product, 1, $snapshotPrice);
        
        // Order item should have snapshot price, not current product price
        $this->assertEquals(1200, $orderItem->getPrice()->getAmountInCents());
        $this->assertNotEquals($this->product->getPrice()->getAmountInCents(), $orderItem->getPrice()->getAmountInCents());
    }
}
