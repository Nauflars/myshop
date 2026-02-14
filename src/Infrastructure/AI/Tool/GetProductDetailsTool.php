<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool;

use App\Application\UseCase\AI\GetProductDetailsByName;
use Psr\Log\LoggerInterface;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[AsTool(
    'GetProductDetailsTool',
    'Get complete product details using its name. Use this tool when the user requests specific information about a product.'
)]
final class GetProductDetailsTool
{
    public function __construct(
        private readonly GetProductDetailsByName $getProductDetailsByName,
        private readonly LoggerInterface $aiToolsLogger,
    ) {
    }

    /**
     * @param string $productName Nombre del producto a consultar
     */
    public function __invoke(string $productName): array
    {
        $this->aiToolsLogger->info('🔍 GetProductDetailsTool called', [
            'product_name' => $productName,
        ]);

        try {
            $product = $this->getProductDetailsByName->execute($productName);

            if (null === $product) {
                $this->aiToolsLogger->warning('⚠️ Product not found', [
                    'product_name' => $productName,
                ]);

                return [
                    'success' => false,
                    'product' => null,
                    'message' => "Product '{$productName}' not found. Please verify the name and try again.",
                ];
            }

            $this->aiToolsLogger->info('✅ Product details retrieved', [
                'product_name' => $productName,
            ]);

            return [
                'success' => true,
                'product' => $product,
                'message' => "Detalles del producto '{$productName}' obtenidos correctamente.",
            ];
        } catch (\Exception $e) {
            $this->aiToolsLogger->error('❌ GetProductDetailsTool failed', [
                'error' => $e->getMessage(),
                'product_name' => $productName,
            ]);

            return [
                'success' => false,
                'product' => null,
                'message' => 'No se pudieron obtener los detalles del producto. Por favor intenta de nuevo.',
            ];
        }
    }
}
