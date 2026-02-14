<?php

namespace App\Tests\Application\UseCase;

use App\Application\DTO\StatsDTO;
use App\Application\UseCase\GenerateStats;
use App\Domain\Entity\Product;
use App\Domain\Entity\User;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Email;
use App\Domain\ValueObject\Money;
use PHPUnit\Framework\TestCase;

class GenerateStatsTest extends TestCase
{
    public function testExecuteGeneratesStats(): void
    {
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);

        $orderRepository->expects($this->once())
            ->method('calculateTotalSales')
            ->willReturn(50000); // $500.00

        $productRepository->expects($this->once())
            ->method('countAll')
            ->willReturn(25);

        $user1 = new User('User 1', new Email('user1@example.com'), 'hash');
        $user2 = new User('User 2', new Email('user2@example.com'), 'hash');

        $userRepository->expects($this->once())
            ->method('findAll')
            ->willReturn([$user1, $user2]);

        $orderRepository->expects($this->once())
            ->method('countAll')
            ->willReturn(15);

        $topProducts = [
            new Product('Product 1', 'Description 1', new Money(1000, 'USD'), 10, 'Electronics'),
            new Product('Product 2', 'Description 2', new Money(2000, 'USD'), 5, 'Books'),
        ];

        $productRepository->expects($this->once())
            ->method('findTopProducts')
            ->with(5)
            ->willReturn($topProducts);

        $useCase = new GenerateStats($orderRepository, $productRepository, $userRepository);
        $stats = $useCase->execute();

        $this->assertInstanceOf(StatsDTO::class, $stats);
        $this->assertEquals(50000, $stats->totalSales);
        $this->assertEquals('$500.00', $stats->totalSalesFormatted);
        $this->assertEquals(25, $stats->productCount);
        $this->assertEquals(2, $stats->userCount);
        $this->assertEquals(15, $stats->orderCount);
        $this->assertCount(2, $stats->topProducts);
        $this->assertEquals('Product 1', $stats->topProducts[0]['name']);
    }

    public function testExecuteWithZeroSales(): void
    {
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);

        $orderRepository->method('calculateTotalSales')->willReturn(0);
        $productRepository->method('countAll')->willReturn(0);
        $userRepository->method('findAll')->willReturn([]);
        $orderRepository->method('countAll')->willReturn(0);
        $productRepository->method('findTopProducts')->willReturn([]);

        $useCase = new GenerateStats($orderRepository, $productRepository, $userRepository);
        $stats = $useCase->execute();

        $this->assertEquals(0, $stats->totalSales);
        $this->assertEquals('$0.00', $stats->totalSalesFormatted);
        $this->assertEquals(0, $stats->productCount);
        $this->assertEquals(0, $stats->userCount);
        $this->assertEquals(0, $stats->orderCount);
        $this->assertEmpty($stats->topProducts);
    }

    public function testExecuteFormatsTopProductsCorrectly(): void
    {
        $orderRepository = $this->createMock(OrderRepositoryInterface::class);
        $productRepository = $this->createMock(ProductRepositoryInterface::class);
        $userRepository = $this->createMock(UserRepositoryInterface::class);

        $orderRepository->method('calculateTotalSales')->willReturn(0);
        $productRepository->method('countAll')->willReturn(0);
        $userRepository->method('findAll')->willReturn([]);
        $orderRepository->method('countAll')->willReturn(0);

        $product = new Product('Laptop', 'A powerful laptop', new Money(150000, 'USD'), 8, 'Electronics');
        $productRepository->method('findTopProducts')->willReturn([$product]);

        $useCase = new GenerateStats($orderRepository, $productRepository, $userRepository);
        $stats = $useCase->execute();

        $this->assertCount(1, $stats->topProducts);
        $this->assertArrayHasKey('id', $stats->topProducts[0]);
        $this->assertArrayHasKey('name', $stats->topProducts[0]);
        $this->assertArrayHasKey('price', $stats->topProducts[0]);
        $this->assertArrayHasKey('stock', $stats->topProducts[0]);
        $this->assertEquals('Laptop', $stats->topProducts[0]['name']);
        $this->assertEquals('$1500.00', $stats->topProducts[0]['price']);
        $this->assertEquals(8, $stats->topProducts[0]['stock']);
    }
}
