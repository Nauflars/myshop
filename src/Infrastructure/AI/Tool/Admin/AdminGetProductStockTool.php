<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool\Admin;

use App\Application\UseCase\Admin\GetProductStock;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'AdminGetProductStockTool',
    'Consultar el stock actual de uno o más productos. Acepta UUID de producto o término de búsqueda por nombre. Muestra stock, estado (disponible/bajo/agotado) y umbral. SOLO para usuarios ADMIN.'
)]
final class AdminGetProductStockTool
{
    public function __construct(
        private readonly GetProductStock $getProductStock,
        private readonly Security $security
    ) {
    }

    /**
     * Get current stock information for a product
     *
     * @param string|null $productId UUID del producto (si se conoce)
     * @param string|null $searchTerm Término de búsqueda por nombre (si no se conoce el ID)
     */
    public function __invoke(?string $productId = null, ?string $searchTerm = null): array
    {
        // Verify admin role
        $user = $this->security->getUser();
        if (!$user instanceof User || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return [
                'success' => false,
                'error' => 'Acceso denegado. Solo administradores pueden consultar el inventario.',
            ];
        }

        // Both parameters are empty
        if ($productId === null && $searchTerm === null) {
            return [
                'success' => false,
                'error' => 'Debes proporcionar un ID de producto o un término de búsqueda.',
            ];
        }

        try {
            // If we have a product ID, get that specific product
            if ($productId !== null) {
                $result = $this->getProductStock->execute($productId);
                
                $statusText = match ($result['status']) {
                    'out_of_stock' => 'AGOTADO',
                    'low_stock' => 'STOCK BAJO',
                    'in_stock' => 'DISPONIBLE',
                };

                return [
                    'success' => true,
                    'product' => $result['product'],
                    'stock' => $result['stock'],
                    'status' => $result['status'],
                    'status_text' => $statusText,
                    'is_low_stock' => $result['is_low_stock'],
                    'threshold' => $result['threshold'],
                    'message' => "Stock de '{$result['product']['nameEs'] ?? $result['product']['name']}': {$result['stock']} unidades ({$statusText})",
                ];
            }

            // Otherwise, search by name
            $result = $this->getProductStock->searchByName($searchTerm);

            if ($result['count'] === 0) {
                return [
                    'success' => false,
                    'error' => "No se encontraron productos que coincidan con '{$searchTerm}'.",
                ];
            }

            // Format products for assistant response
            $formattedProducts = [];
            foreach ($result['products'] as $product) {
                $statusText = match ($product['status']) {
                    'out_of_stock' => 'AGOTADO',
                    'low_stock' => 'STOCK BAJO',
                    'in_stock' => 'DISPONIBLE',
                };

                $formattedProducts[] = [
                    'id' => $product['id'],
                    'name' => $product['nameEs'] ?? $product['name'],
                    'category' => $product['category'],
                    'stock' => $product['stock'],
                    'status' => $product['status'],
                    'status_text' => $statusText,
                    'is_low_stock' => $product['is_low_stock'],
                ];
            }

            return [
                'success' => true,
                'products' => $formattedProducts,
                'count' => $result['count'],
                'search_term' => $searchTerm,
                'message' => "Se encontraron {$result['count']} producto(s) que coinciden con '{$searchTerm}'.",
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error al consultar stock: ' . $e->getMessage(),
            ];
        }
    }
}
