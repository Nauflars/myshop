<?php

namespace App\Tests\Application\UseCase;

use App\Application\UseCase\AddProductToCart;
use App\Domain\Entity\Cart;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

class AddProductToCartTest extends TestCase
{
    private AddProductToCart $useCase;
    private CartRepositoryInterface $cartRepository;
    private ProductRepositoryInterface $productRepository;
    private User $user;
    private Product $product;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        
        $this->useCase = new AddProductToCart(
            $this->cartRepository,
            $this->productRepository
        );

        $this->user = new User(
            'Test User',
            new Email('test@example.com'),
            'password123'
        );

        $this->product = new Product(
            'Test Product',
            'Test Description',
            new Money(1999, 'USD'),
            10,
            'Electronics'
        );
    }

    public function testExecuteAddsProductToExistingCart(): void
    {
        $cart = new Cart($this->user);
        
        $this->cartRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with($this->user)
            ->willReturn($cart);
        
        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->product->getId())
            ->willReturn($this->product);
        
        $this->cartRepository
            ->expects($this->once())
            ->method('save')
            ->with($cart);

        $result = $this->useCase->execute($this->user, $this->product->getId(), 2);

        $this->assertSame($cart, $result);
        $this->assertEquals(1, $result->getItemCount());
        $this->assertEquals(2, $result->getTotalQuantity());
    }

    public function testExecuteCreatesNewCartIfNotExists(): void
    {
        $this->cartRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with($this->user)
            ->willReturn(null);
        
        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->with($this->product->getId())
            ->willReturn($this->product);
        
        $this->cartRepository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Cart::class));

        $result = $this->useCase->execute($this->user, $this->product->getId(), 1);

        $this->assertInstanceOf(Cart::class, $result);
        $this->assertEquals($this->user, $result->getUser());
        $this->assertEquals(1, $result->getItemCount());
    }

    public function testExecuteThrowsExceptionForInvalidQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than zero');

        $this->useCase->execute($this->user, $this->product->getId(), 0);
    }

    public function testExecuteThrowsExceptionForNegativeQuantity(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than zero');

        $this->useCase->execute($this->user, $this->product->getId(), -5);
    }

    public function testExecuteThrowsExceptionForNonExistentProduct(): void
    {
        $this->cartRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with($this->user)
            ->willReturn(new Cart($this->user));
        
        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn(null);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product not found');

        $this->useCase->execute($this->user, 'invalid-id', 1);
    }

    public function testExecuteThrowsExceptionForOutOfStockProduct(): void
    {
        $outOfStockProduct = new Product(
            'Out of Stock Product',
            'Description',
            new Money(1999, 'USD'),
            0,
            'Electronics'
        );

        $this->cartRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with($this->user)
            ->willReturn(new Cart($this->user));
        
        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($outOfStockProduct);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Product is out of stock');

        $this->useCase->execute($this->user, $outOfStockProduct->getId(), 1);
    }

    public function testExecuteThrowsExceptionForInsufficientStock(): void
    {
        $this->cartRepository
            ->expects($this->once())
            ->method('findByUser')
            ->with($this->user)
            ->willReturn(new Cart($this->user));
        
        $this->productRepository
            ->expects($this->once())
            ->method('findById')
            ->willReturn($this->product);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Insufficient stock. Available: 10');

        $this->useCase->execute($this->user, $this->product->getId(), 20);
    }
}
