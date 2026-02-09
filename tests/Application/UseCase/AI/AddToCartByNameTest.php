<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\AI;

use App\Application\UseCase\AI\AddToCartByName;
use App\Domain\Entity\Cart;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\Repository\CartRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for AddToCartByName use case
 */
class AddToCartByNameTest extends TestCase
{
    private CartRepositoryInterface $cartRepository;
    private ProductRepositoryInterface $productRepository;
    private AddToCartByName $useCase;
    private User $user;

    protected function setUp(): void
    {
        $this->cartRepository = $this->createMock(CartRepositoryInterface::class);
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $this->useCase = new AddToCartByName($this->cartRepository, $this->productRepository);
        $this->user = new User('Test', new Email('test@example.com'), 'hash');
    }

    public function testExecuteAddsProductToCart(): void
    {
        $product = new Product('Laptop', 'Gaming laptop', new Money(150000, 'USD'), 5, 'Electronics');
        $cart = new Cart($this->user);
        
        $this->productRepository->expects($this->once())
            ->method('search')
            ->with('Laptop', null, null, null)
            ->willReturn([$product]);
        
        $this->cartRepository->expects($this->once())
            ->method('findByUser')
            ->with($this->user)
            ->willReturn($cart);
        
        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($cart);
        
        $result = $this->useCase->execute($this->user, 'Laptop', 1);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(1, $result['totalItems']);
        $this->assertStringContainsString('Laptop', $result['message']);
    }

    public function testExecuteCreatesNewCartIfNotExists(): void
    {
        $product = new Product('Mouse', 'Gaming mouse', new Money(5000, 'USD'), 10, 'Electronics');
        
        $this->productRepository->expects($this->once())
            ->method('search')
            ->with('Mouse', null, null, null)
            ->willReturn([$product]);
        
        $this->cartRepository->expects($this->once())
            ->method('findByUser')
            ->with($this->user)
            ->willReturn(null);
        
        $this->cartRepository->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(Cart::class));
        
        $result = $this->useCase->execute($this->user, 'Mouse', 2);
        
        $this->assertTrue($result['success']);
        $this->assertEquals(2, $result['totalItems']);
    }

    public function testExecuteFailsWhenProductNotFound(): void
    {
        $this->productRepository->expects($this->once())
            ->method('search')
            ->with('NonExistent', null, null, null)
            ->willReturn([]);
        
        $this->cartRepository->expects($this->never())
            ->method('save');
        
        $result = $this->useCase->execute($this->user, 'NonExistent', 1);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not found', $result['message']);
    }

    public function testExecuteFailsWhenQuantityIsInvalid(): void
    {
        $result = $this->useCase->execute($this->user, 'Laptop', 0);
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('greater than zero', $result['message']);
    }
}
