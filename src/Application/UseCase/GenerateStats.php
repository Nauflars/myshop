<?php

namespace App\Application\UseCase;

use App\Application\DTO\StatsDTO;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;
use App\Domain\ValueObject\Money;

final class GenerateStats
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function execute(): StatsDTO
    {
        $totalSalesInCents = $this->orderRepository->calculateTotalSales();
        $totalSales = new Money($totalSalesInCents, 'USD');

        $productCount = $this->productRepository->countAll();
        $userCount = count($this->userRepository->findAll());
        $orderCount = $this->orderRepository->countAll();

        $topProducts = $this->productRepository->findTopProducts(5);

        return new StatsDTO(
            totalSales: $totalSalesInCents,
            totalSalesFormatted: $totalSales->format(),
            productCount: $productCount,
            userCount: $userCount,
            orderCount: $orderCount,
            topProducts: array_map(
                fn($product) => [
                    'id' => $product->getId(),
                    'name' => $product->getName(),
                    'price' => $product->getPrice()->format(),
                    'stock' => $product->getStock(),
                ],
                $topProducts
            )
        );
    }
}
