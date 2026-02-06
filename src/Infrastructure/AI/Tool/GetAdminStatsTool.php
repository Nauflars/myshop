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
    'Obtener estadísticas del negocio (ventas, productos destacados, usuarios activos, órdenes pendientes). SOLO disponible para administradores. Usa esta herramienta cuando un admin pida estadísticas o métricas.'
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
                    'message' => 'Debes iniciar sesión para ver estadísticas.',
                ];
            }

            if (!$user->isAdmin()) {
                return [
                    'success' => false,
                    'stats' => null,
                    'message' => 'Solo los administradores pueden acceder a esta información.',
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
                    "**Productos Más Populares:**\n%s",
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
                'message' => 'No pude obtener las estadísticas. Por favor intenta de nuevo.',
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
        
        return implode("\n", $formatted) ?: 'No hay datos de productos';
    }
}
