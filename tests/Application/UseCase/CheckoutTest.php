<?php

namespace App\Tests\Application\UseCase;

use App\Application\UseCase\Checkout;
use App\Domain\Entity\Cart;
use App\Domain\Entity\Order;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

class CheckoutTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        $this->user = new User('Test User', new Email('test@example.com'), 'hash123');
    }

    public function testExecuteCreatesOrderFromCart(): void
    {
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);

        $cart = new Cart($this->user);
        $product = new Product('Test Product', 'Description', new Money(1000, 'USD'), 10, 'Electronics');
        $cart->addProduct($product, 2);

        $cartRepository->expects($this->once())
            ->method('findByUser')
            ->with($this->user)
            ->willReturn($cart);

        $orderRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Order::class));

        $productRepository->expects($this->once())
            ->method('save')
            ->with($product);

        $cartRepository->expects($this->once())
            ->method('save')
            ->with($cart);

        $useCase = new Checkout($cartRepository, $orderRepository, $productRepository);
        $order = $useCase->execute($this->user);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame($this->user, $order->getUser());
        $this->assertCount(0, $cart->getItems()); // Cart should be cleared
    }

    public function testExecuteDecrementsStock(): void
    {
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);

        $cart = new Cart($this->user);
        $product = new Product('Test Product', 'Description', new Money(1000, 'USD'), 10, 'Electronics');
        $cart->addProduct($product, 3);

        $cartRepository->method('findByUser')->willReturn($cart);
        $orderRepository->method('save');
        $cartRepository->method('save');

        $productRepository->expects($this->once())
            ->method('save')
            ->with($this->callback(function (Product $savedProduct) {
                return $savedProduct->getStock() === 7; // 10 - 3
            }));

        $useCase = new Checkout($cartRepository, $orderRepository, $productRepository);
        $useCase->execute($this->user);
    }

    public function testExecuteThrowsExceptionIfCartIsEmpty(): void
    {
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);

        $emptyCart = new Cart($this->user);

        $cartRepository->expects($this->once())
            ->method('findByUser')
            ->willReturn($emptyCart);

        $useCase = new Checkout($cartRepository, $orderRepository, $productRepository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cart is empty');
        
        $useCase->execute($this->user);
    }

    public function testExecuteThrowsExceptionIfCartNotFound(): void
    {
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);

        $cartRepository->expects($this->once())
            ->method('findByUser')
            ->willReturn(null);

        $useCase = new Checkout($cartRepository, $orderRepository, $productRepository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cart is empty');
        
        $useCase->execute($this->user);
    }

    public function testExecuteThrowsExceptionIfInsufficientStock(): void
    {
        $cartRepository = $this->createMock(CartRepositoryInterface::class);
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);

        $cart = new Cart($this->user);
        $product = new Product('Test Product', 'Description', new Money(1000, 'USD'), 3, 'Electronics');
        $cart->addProduct($product, 5); // Request more than available

        $cartRepository->method('findByUser')->willReturn($cart);

        $useCase = new Checkout($cartRepository, $orderRepository, $productRepository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches('/Insufficient stock/');
        
        $useCase->execute($this->user);
    }
}
