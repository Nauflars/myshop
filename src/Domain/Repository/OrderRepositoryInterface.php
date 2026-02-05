<?php

namespace App\Domain\Repository;

use App\Domain\Entity\Order;
use App\Domain\Entity\User;

interface OrderRepositoryInterface
{
    public function save(Order $order): void;

    public function findById(string $id): ?Order;

    public function findByOrderNumber(string $orderNumber): ?Order;

    public function findByUser(User $user): array;

    public function findAll(): array;

    public function delete(Order $order): void;

    public function findByStatus(string $status): array;

    public function calculateTotalSales(): int;

    public function countAll(): int;
}
