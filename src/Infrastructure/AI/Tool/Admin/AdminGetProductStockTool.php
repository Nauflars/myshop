<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool\Admin;

use App\Application\UseCase\Admin\GetProductStock;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'AdminGetProductStockTool',
    'Check the current stock of one or more products. Accepts product UUID or search term by name. Shows stock, status (available/low/out), and threshold. ONLY for ADMIN users.'
)]
final class AdminGetProductStockTool
{
    public function __construct(
        private readonly GetProductStock $getProductStock,
        private readonly Security $security,
    ) {
    }

    /**
     * Get current stock information for a product.
     *
     * @param string|null $productId  UUID del producto (si se conoce)
     * @param string|null $searchTerm Término de búsqueda por nombre (si no se conoce el ID)
     */
    public function __invoke(?string $productId = null, ?string $searchTerm = null): array
    {
        // Verify admin role
        $user = $this->security->getUser();
        if (!$user instanceof User || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return [
                'success' => false,
                'error' => 'Access denied. Only administrators can check inventory.',
            ];
        }

        // Both parameters are empty
        if (null === $productId && null === $searchTerm) {
            return [
                'success' => false,
                'error' => 'You must provide a product ID or search term.',
            ];
        }

        try {
            // If we have a product ID, get that specific product
            if (null !== $productId) {
                $result = $this->getProductStock->execute($productId);

                $statusText = match ($result['status']) {
                    'out_of_stock' => 'OUT OF STOCK',
                    'low_stock' => 'LOW STOCK',
                    'in_stock' => 'IN STOCK',
                };

                $productName = $result['product']['nameEs'] ?? $result['product']['name'];

                return [
                    'success' => true,
                    'product' => $result['product'],
                    'stock' => $result['stock'],
                    'status' => $result['status'],
                    'status_text' => $statusText,
                    'is_low_stock' => $result['is_low_stock'],
                    'threshold' => $result['threshold'],
                    'message' => "Stock for '$productName': {$result['stock']} units ($statusText)",
                ];
            }

            // Otherwise, search by name
            $result = $this->getProductStock->searchByName($searchTerm);

            if (0 === $result['count']) {
                return [
                    'success' => false,
                    'error' => "No products found matching '{$searchTerm}'.",
                ];
            }

            // Format products for assistant response
            $formattedProducts = [];
            foreach ($result['products'] as $product) {
                $statusText = match ($product['status']) {
                    'out_of_stock' => 'OUT OF STOCK',
                    'low_stock' => 'LOW STOCK',
                    'in_stock' => 'IN STOCK',
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
                'message' => "Found {$result['count']} product(s) matching '{$searchTerm}'.",
            ];
        } catch (\InvalidArgumentException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Error checking stock: '.$e->getMessage(),
            ];
        }
    }
}
