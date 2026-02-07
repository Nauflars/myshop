<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetAdminStats;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * GetAdminStatsTool - AI Tool for retrieving business statistics (Admin only)
 * 
 * Provides comprehensive business metrics including sales, top products, active users,
 * and pending orders. Only accessible to users with ROLE_ADMIN.
 */
#[AsTool(
    'GetAdminStats',
    'Get business statistics (sales, featured products, active users, pending orders). ONLY available to administrators. Use this tool when an admin requests statistics or metrics.'
)]
final class GetAdminStatsTool
{
    public function __construct(
        private readonly GetAdminStats $getAdminStats,
        private readonly Security $security
    ) {
    }

    /**
     * @return array{success: bool, stats: array|null, message: string}
     */
    public function __invoke(): array
    {
        try {
            $user = $this->security->getUser();
            
            if (!$user instanceof User) {
                return [
                    'success' => false,
                    'stats' => null,
                    'message' => 'You must log in to view statistics.',
                ];
            }

            if (!$user->isAdmin()) {
                return [
                    'success' => false,
                    'stats' => null,
                    'message' => 'Only administrators can access this information.',
                ];
            }

            $result = $this->getAdminStats->execute($user);

            if ($result['success']) {
                $stats = $result['stats'];
                
                // Format response in Spanish
                $formattedMessage = sprintf(
                    "📊 **Estadísticas del Negocio**\n\n" .
                    "💰 Ventas del mes: $%.2f\n" .
                    "📈 Ingresos totales: $%.2f\n" .
                    "📦 Valor promedio de orden: $%.2f\n" .
                    "👥 Usuarios activos (30 días): %d\n" .
                    "⏳ Órdenes pendientes: %d\n\n" .
                    "**Top-Selling Products:**\n%s",
                    $stats['monthlySales'],
                    $stats['totalRevenue'],
                    $stats['averageOrderValue'],
                    $stats['activeUsers'],
                    $stats['pendingOrders'],
                    $this->formatTopProducts($stats['topProducts'])
                );

                return [
                    'success' => true,
                    'stats' => $stats,
                    'message' => $formattedMessage,
                ];
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'success' => false,
                'stats' => null,
                'message' => 'Could not retrieve statistics. Please try again.',
            ];
        }
    }

    private function formatTopProducts(array $products): string
    {
        $formatted = [];
        foreach ($products as $idx => $product) {
            $formatted[] = sprintf(
                "%d. %s - $%.2f (%d en stock)",
                $idx + 1,
                $product['name'],
                $product['price'],
                $product['stock']
            );
        }
        
        return implode("\n", $formatted) ?: 'No product data available';
    }
}
