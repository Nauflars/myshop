<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetProductsName;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool('GetProductsNameTool', 'Buscar y explorar productos por nombre o categoría. Devuelve una lista de productos con sus nombres, descripciones, precios y disponibilidad. NO devuelve IDs internos.')]
final class GetProductsNameTool
{
    public function __construct(
        private readonly GetProductsName $getProductsName
    ) {
    }

    public function __invoke(?string $searchTerm = null, ?string $category = null): array
    {
        try {
            $products = $this->getProductsName->execute($searchTerm, $category);

            // Remove IDs from response to prevent exposure
            $productsWithoutIds = array_map(function($product) {
                unset($product['id']);
                return $product;
            }, $products);

            return [
                'success' => true,
                'data' => $productsWithoutIds,
                'count' => count($productsWithoutIds),
                'message' => $this->formatMessage($productsWithoutIds, $searchTerm, $category),
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'data' => [],
                'count' => 0,
                'message' => 'No se pudieron recuperar los productos. Por favor, intente nuevamente.',
            ];
        }
    }
    

    private function formatMessage(array $products, ?string $searchTerm, ?string $category): string
    {
        $count = count($products);
        
        if ($count === 0) {
            if ($searchTerm && $category) {
                return "No se encontraron productos que coincidan con '{$searchTerm}' en la categoría '{$category}'.";
            } elseif ($searchTerm) {
                return "No se encontraron productos que coincidan con '{$searchTerm}'.";
            } elseif ($category) {
                return "No se encontraron productos en la categoría '{$category}'.";
            }
            return 'No se encontraron productos en el catálogo.';
        }
        
        $suffix = $searchTerm ? " que coinciden con '{$searchTerm}'" : '';
        $suffix .= $category ? " en la categoría '{$category}'" : '';
        
        return "Se encontraron {$count} producto" . ($count > 1 ? 's' : '') . $suffix . '.';
    }
}
