<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool\Admin;

use App\Application\UseCase\Admin\GetLowStockProducts;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'AdminGetLowStockProductsTool',
    'Get list of low-stock products. Allows specifying a custom threshold (default: 10 units). Useful for identifying products that need restocking. ONLY for ADMIN users.'
)]
final class AdminGetLowStockProductsTool
{
    public function __construct(
        private readonly GetLowStockProducts $getLowStockProducts,
        private readonly Security $security
    ) {
    }

    /**
     * Get products with low stock levels
     *
     * @param int|null $threshold Umbral de stock bajo (por defecto: 10). Productos con stock menor a este valor se consideran bajos
     */
    public function __invoke(?int $threshold = null): array
    {
        // Verify admin role
        $user = $this->security->getUser();
        if (!$user instanceof User || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return [
                'success' => false,
                'error' => 'Acceso denegado. Solo administradores pueden consultar el inventario.',
            ];
        }

        try {
            $result = $this->getLowStockProducts->execute($threshold);

            if ($result['count'] === 0) {
                return [
                    'success' => true,
                    'products' => [],
                    'count' => 0,
                    'threshold' => $result['threshold'],
                    'message' => "No hay productos con stock bajo (menos de {$result['threshold']} unidades).",
                ];
            }

            // Format products for assistant response
            $formattedProducts = [];
            foreach ($result['products'] as $product) {
                $formattedProducts[] = [
                    'id' => $product['id'],
                    'name' => $product['nameEs'] ?? $product['name'],
                    'category' => $product['category'],
                    'stock' => $product['stock'],
                    'status' => 'low_stock',
                ];
            }

            return [
                'success' => true,
                'products' => $formattedProducts,
                'count' => $result['count'],
                'threshold' => $result['threshold'],
                'message' => "Se encontraron {$result['count']} producto(s) con stock bajo (menos de {$result['threshold']} unidades).",
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al consultar productos con stock bajo: ' . $e->getMessage(),
            ];
        }
    }
}
