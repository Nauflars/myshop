<?php

declare(strict_types=1);

namespace App\Tests\Application\UseCase\AI;

use App\Application\UseCase\AI\RemoveProductFromCart;
use App\Domain\Entity\Cart;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RemoveProductFromCart use case
 */
class RemoveProductFromCartTest extends TestCase
{
    private ProductRepositoryInterface $productRepository;
    private RemoveProductFromCart $useCase;
    private User $user;
    private Cart $cart;

    protected function setUp(): void
    {
        $this->productRepository = $this->createMock(ProductRepositoryInterface::class);
        $entityManager = $this->createMock(\Doctrine\ORM\EntityManagerInterface::class);
        $this->useCase = new RemoveProductFromCart($this->productRepository, $entityManager);
        $this->user = new User('Test', new Email('test@example.com'), 'hash');
        $this->cart = new Cart($this->user);
    }

    public function testExecuteRemovesProductFromCart(): void
    {
        $product = new Product('Laptop', 'Gaming laptop', new Money(150000, 'USD'), 5, 'Electronics');
        $this->cart->addProduct($product, 1);
        
        $this->productRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$product]);
        
        $result = $this->useCase->execute($this->cart, 'Laptop');
        
        $this->assertTrue($result['success']);
        $this->assertStringContainsString('eliminó', $result['message']);
        $this->assertTrue($this->cart->isEmpty());
    }

    public function testExecuteFailsWhenProductNotInCart(): void
    {
        $product = new Product('Mouse', 'Gaming mouse', new Money(5000, 'USD'), 10, 'Electronics');
        
        $this->productRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$product]);
        
        $result = $this->useCase->execute($this->cart, 'Mouse');
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('no estaba', $result['message']);
    }

    public function testExecuteFailsWhenProductNotFound(): void
    {
        $this->productRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([]);
        
        $result = $this->useCase->execute($this->cart, 'NonExistent');
        
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('No se encontró', $result['message']);
    }
}
