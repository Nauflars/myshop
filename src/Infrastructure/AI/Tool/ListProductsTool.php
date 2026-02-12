<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\ListProducts;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    'ListProductsTool',
    'List available products with name, description, price, and availability. Use this tool when the user wants to view or search for products.'
)]
final class ListProductsTool
{
    public function __construct(
        private readonly ListProducts $listProducts,
        private readonly LoggerInterface $aiToolsLogger
    ) {
    }

    /**
     * @param string|null $category Filtrar por categoría (opcional)
     * @param bool $availableOnly Mostrar solo productos disponibles (por defecto: true)
     */
    public function __invoke(?string $category = null, bool $availableOnly = true): array
    {
        $this->aiToolsLogger->info('📋 ListProductsTool called', [
            'category' => $category,
            'available_only' => $availableOnly
        ]);
        
        try {
            $products = $this->listProducts->execute($category, $availableOnly);

            if (empty($products)) {
                $message = $category
                    ? "No se encontraron productos en la categoría '{$category}'."
                    : 'No hay productos disponibles en este momento.';

                return [
                    'success' => true,
                    'products' => [],
                    'count' => 0,
                    'message' => $message,
                ];
            }

            $message = sprintf(
                'Se encontraron %d producto(s)%s.',
                count($products),
                $category ? " en la categoría '{$category}'" : ''
            );

            $this->aiToolsLogger->info('✅ Products listed', [
                'count' => count($products),
                'category' => $category
            ]);
            
            return [
                'success' => true,
                'products' => $products,
                'count' => count($products),
                'message' => $message,
            ];
        } catch (\Exception $e) {
            $this->aiToolsLogger->error('❌ ListProductsTool failed', [
                'error' => $e->getMessage()
            ]);
            return [
                'success' => false,
                'products' => [],
                'count' => 0,
                'message' => 'No se pudieron obtener los productos. Por favor intenta de nuevo.',
            ];
        }
    }
}
