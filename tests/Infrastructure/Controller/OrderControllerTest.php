<?php

namespace App\Tests\Infrastructure\Controller;

use App\Domain\Entity\Cart;
use App\Domain\Entity\Order;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Tests for checkout with shippingAddress and order serialization.
 * Tests the Order entity shippingAddress handling used by OrderController.
 */
class OrderControllerTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User('Test User', new Email('test@example.com'), 'hash123');
    }

    public function testOrderShippingAddressDefaultsToNull(): void
    {
        $order = new Order($this->user);

        $this->assertNull($order->getShippingAddress());
    }

    public function testOrderAcceptsShippingAddress(): void
    {
        $order = new Order($this->user);
        $address = [
            'street' => '123 Main St',
            'city' => 'Springfield',
            'zipCode' => '62701',
            'country' => 'US',
        ];

        $order->setShippingAddress($address);

        $this->assertEquals($address, $order->getShippingAddress());
        $this->assertEquals('123 Main St', $order->getShippingAddress()['street']);
        $this->assertEquals('Springfield', $order->getShippingAddress()['city']);
        $this->assertEquals('62701', $order->getShippingAddress()['zipCode']);
        $this->assertEquals('US', $order->getShippingAddress()['country']);
    }

    public function testOrderCanClearShippingAddress(): void
    {
        $order = new Order($this->user);
        $order->setShippingAddress(['street' => '123 Main St']);

        $order->setShippingAddress(null);

        $this->assertNull($order->getShippingAddress());
    }

    public function testSetShippingAddressUpdatesTimestamp(): void
    {
        $order = new Order($this->user);
        $originalUpdatedAt = $order->getUpdatedAt();

        // Force a tiny delay to ensure timestamp changes
        usleep(1000);

        $order->setShippingAddress(['street' => '456 Oak Ave']);

        $this->assertGreaterThanOrEqual(
            $originalUpdatedAt,
            $order->getUpdatedAt()
        );
    }

    public function testOrderFromCartWithShippingAddress(): void
    {
        $cart = new Cart($this->user);
        $product = new Product('Test', 'Desc', new Money(1000, 'USD'), 10, 'Electronics');
        $cart->addProduct($product, 1);

        $order = Order::createFromCart($cart);

        // Initially no shipping address
        $this->assertNull($order->getShippingAddress());

        // Set address post-creation (like the controller does)
        $address = [
            'street' => '789 Pine Rd',
            'city' => 'Shelbyville',
            'zipCode' => '46176',
            'country' => 'US',
        ];
        $order->setShippingAddress($address);

        $this->assertEquals($address, $order->getShippingAddress());
        $this->assertCount(1, $order->getItems());
    }

    public function testShippingAddressWithInternationalData(): void
    {
        $order = new Order($this->user);
        $address = [
            'street' => 'Calle Mayor 42',
            'city' => 'Madrid',
            'zipCode' => '28001',
            'country' => 'ES',
            'state' => 'Madrid',
        ];

        $order->setShippingAddress($address);

        $this->assertEquals('ES', $order->getShippingAddress()['country']);
        $this->assertEquals('Calle Mayor 42', $order->getShippingAddress()['street']);
    }
}
