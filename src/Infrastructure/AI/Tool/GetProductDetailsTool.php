<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetProductDetailsByName;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    'GetProductDetailsTool',
    'Obtener detalles completos de un producto usando su nombre. Usa esta herramienta cuando el usuario solicite información específica sobre un producto.'
)]
final class GetProductDetailsTool
{
    public function __construct(
        private readonly GetProductDetailsByName $getProductDetailsByName
    ) {
    }

    /**
     * @param string $productName Nombre del producto a consultar
     */
    public function __invoke(string $productName): array
    {
        try {
            $product = $this->getProductDetailsByName->execute($productName);

            if ($product === null) {
                return [
                    'success' => false,
                    'product' => null,
                    'message' => "No se encontró el producto '{$productName}'. Verifica el nombre y intenta de nuevo.",
                ];
            }

            return [
                'success' => true,
                'product' => $product,
                'message' => "Detalles del producto '{$productName}' obtenidos correctamente.",
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'product' => null,
                'message' => 'No se pudieron obtener los detalles del producto. Por favor intenta de nuevo.',
            ];
        }
    }
}
