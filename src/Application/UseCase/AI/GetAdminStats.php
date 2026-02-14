<?php

namespace App\Application\UseCase\AI;

use App\Domain\Entity\User;
use App\Domain\Repository\OrderRepositoryInterface;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\Repository\UserRepositoryInterface;

/**
 * GetAdminStats Use Case.
 *
 * Provides business statistics and metrics for administrators only.
 * Returns sales totals, top products, active users, and pending orders.
 */
final class GetAdminStats
{
    public function __construct(
        private readonly OrderRepositoryInterface $orderRepository,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly UserRepositoryInterface $userRepository,
    ) {
    }

    /**
     * @param User $admin The authenticated admin user
     *
     * @return array{success: bool, stats: array|null, message: string}
     */
    public function execute(User $admin): array
    {
        // Verify admin role
        if (!$admin->isAdmin()) {
            return [
                'success' => false,
                'stats' => null,
                'message' => 'Solo los administradores pueden ver estadísticas.',
            ];
        }

        try {
            $stats = [
                'monthlySales' => $this->getMonthlySales(),
                'topProducts' => $this->getTopProducts(5),
                'activeUsers' => $this->getActiveUsersCount(),
                'pendingOrders' => $this->getPendingOrdersCount(),
                'totalRevenue' => $this->getTotalRevenue(),
                'averageOrderValue' => $this->getAverageOrderValue(),
            ];

            return [
                'success' => true,
                'stats' => $stats,
                'message' => 'Estadísticas cargadas correctamente.',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'stats' => null,
                'message' => 'Error al cargar estadísticas: '.$e->getMessage(),
            ];
        }
    }

    private function getMonthlySales(): float
    {
        $orders = $this->orderRepository->findAll();
        $currentMonth = (new \DateTime())->format('Y-m');
        $total = 0.0;

        foreach ($orders as $order) {
            if ($order->getCreatedAt()->format('Y-m') === $currentMonth && 'completed' === $order->getStatus()) {
                $total += $order->getTotalInCents() / 100;
            }
        }

        return $total;
    }

    private function getTopProducts(int $limit): array
    {
        // This is a simplified version - in production, you'd query order_items
        $products = $this->productRepository->findAll();
        $topProducts = [];

        foreach (array_slice($products, 0, $limit) as $product) {
            $topProducts[] = [
                'name' => $product->getName(),
                'price' => $product->getPriceInCents() / 100,
                'currency' => $product->getCurrency(),
                'stock' => $product->getStock(),
            ];
        }

        return $topProducts;
    }

    private function getActiveUsersCount(): int
    {
        // Users who have placed orders in the last 30 days
        $users = $this->userRepository->findAll();
        $activeCount = 0;
        $thirtyDaysAgo = new \DateTime('-30 days');

        foreach ($users as $user) {
            $orders = $this->orderRepository->findAll();
            foreach ($orders as $order) {
                if ($order->getUser()->getId() === $user->getId() && $order->getCreatedAt() >= $thirtyDaysAgo) {
                    ++$activeCount;
                    break;
                }
            }
        }

        return $activeCount;
    }

    private function getPendingOrdersCount(): int
    {
        $orders = $this->orderRepository->findAll();
        $pendingCount = 0;

        foreach ($orders as $order) {
            if ('pending' === $order->getStatus()) {
                ++$pendingCount;
            }
        }

        return $pendingCount;
    }

    private function getTotalRevenue(): float
    {
        $orders = $this->orderRepository->findAll();
        $total = 0.0;

        foreach ($orders as $order) {
            if ('completed' === $order->getStatus()) {
                $total += $order->getTotalInCents() / 100;
            }
        }

        return $total;
    }

    private function getAverageOrderValue(): float
    {
        $orders = $this->orderRepository->findAll();
        $completedOrders = [];

        foreach ($orders as $order) {
            if ('completed' === $order->getStatus()) {
                $completedOrders[] = $order;
            }
        }

        if (0 === count($completedOrders)) {
            return 0.0;
        }

        $total = array_reduce($completedOrders, function ($sum, $order) {
            return $sum + ($order->getTotalInCents() / 100);
        }, 0.0);

        return $total / count($completedOrders);
    }
}
