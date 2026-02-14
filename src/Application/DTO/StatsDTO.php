<?php

namespace App\Application\DTO;

final readonly class StatsDTO
{
    public function __construct(
        public int $totalSales,
        public string $totalSalesFormatted,
        public int $productCount,
        public int $userCount,
        public int $orderCount,
        public array $topProducts,
    ) {
    }
}
