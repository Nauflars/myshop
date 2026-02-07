<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool\Admin;

use App\Application\Service\AdminAssistantLogger;
use App\Application\UseCase\Admin\DeleteProduct;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'AdminDeleteProductTool',
    'Delete a product from the catalog. Verifies product has no associated orders before deletion. Assistant must request confirmation before deleting. If multiple products match the name, show numbered list for disambiguation. ONLY for ADMIN users.'
)]
final class AdminDeleteProductTool
{
    public function __construct(
        private readonly DeleteProduct $deleteProduct,
        private readonly AdminAssistantLogger $logger,
        private readonly Security $security
    ) {
    }

    /**
     * Delete a product from the catalog
     *
     * @param string $productName Nombre del producto a eliminar
     * @param int|null $disambiguation_index Si hay múltiples productos con el mismo nombre, índice del producto seleccionado (1-based)
     * @param bool $confirmed Confirmación explícita del administrador (requerida)
     */
    public function __invoke(
        string $productName,
        ?int $disambiguation_index = null,
        bool $confirmed = false
    ): array {
        // Verify admin role
        $user = $this->security->getUser();
        if (!$user instanceof User || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return [
                'success' => false,
                'error' => 'Acceso denegado. Solo administradores pueden eliminar productos.',
            ];
        }

        try {
            // Find products by name
            $products = $this->deleteProduct->findProductsByName($productName);

            if (empty($products)) {
                return [
                    'success' => false,
                    'error' => "No se encontró el producto '{$productName}'",
                    'message' => "No product exists with the name '{$productName}'.",
                ];
            }

            // Handle disambiguation
            if (count($products) > 1 && $disambiguation_index === null) {
                $productList = [];
                foreach ($products as $index => $product) {
                    $productList[] = sprintf(
                        "%d. %s (ID: %s, Precio: $%.2f, Stock: %d, Categoría: %s)",
                        $index + 1,
                        $product->getName(),
                        $product->getId(),
                        $product->getPrice(),
                        $product->getStock(),
                        $product->getCategory()
                    );
                }

                return [
                    'success' => false,
                    'requires_disambiguation' => true,
                    'message' => "I found " . count($products) . " products with that name:\n\n" .
                        implode("\n", $productList) .
                        "\n\n¿Cuál de estos productos deseas eliminar? Responde con el número.",
                    'product_count' => count($products),
                ];
            }

            // Select the product
            $selectedProductIndex = $disambiguation_index !== null ? $disambiguation_index - 1 : 0;
            
            if (!isset($products[$selectedProductIndex])) {
                return [
                    'success' => false,
                    'error' => "Índice inválido. Por favor selecciona un número entre 1 y " . count($products),
                ];
            }

            $product = $products[$selectedProductIndex];

            // Check if product can be deleted
            $deleteCheck = $this->deleteProduct->canDelete($product);
            
            if (!$deleteCheck['can_delete']) {
                return [
                    'success' => false,
                    'error' => 'El producto no puede ser eliminado',
                    'message' => "⚠️ No se puede eliminar el producto '{$product->getName()}'. " . 
                        $deleteCheck['reason'],
                    'order_count' => $deleteCheck['order_count'],
                ];
            }

            // Get product details for confirmation
            $productDetails = $this->deleteProduct->getProductDetails($product);

            // Check if confirmation is required
            if (!$confirmed) {
                return [
                    'success' => false,
                    'requires_confirmation' => true,
                    'message' => "⚠️ Vas a eliminar el siguiente producto:\n" .
                        "• Nombre: {$productDetails['name']}\n" .
                        "• Descripción: {$productDetails['description']}\n" .
                        "• Precio: \${$productDetails['price']}\n" .
                        "• Stock: {$productDetails['stock']} unidades\n" .
                        "• Categoría: {$productDetails['category']}\n" .
                        "\n⚠️ Esta acción no se puede deshacer. ¿Estás seguro de que deseas eliminar este producto? Responde 'sí', 'confirmar' o 'adelante'.",
                    'product_details' => $productDetails,
                ];
            }

            // Execute deletion
            $productName = $product->getName();
            $productId = $product->getId();
            
            $this->deleteProduct->execute($product);

            // Log action
            $this->logger->logProductDeletion(
                $user,
                $productId,
                $productName,
                true
            );

            return [
                'success' => true,
                'message' => "✓ Producto '{$productName}' eliminado exitosamente",
                'product_id' => $productId,
                'product_name' => $productName,
            ];

        } catch (\RuntimeException $e) {
            // Business rule error (e.g., product has orders)
            $this->logger->logFailedAction(
                $user,
                'delete_product',
                $e->getMessage(),
                ['product_name' => $productName]
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => "⚠️ {$e->getMessage()}",
            ];
        } catch (\InvalidArgumentException $e) {
            // Validation error
            $this->logger->logFailedAction(
                $user,
                'delete_product',
                $e->getMessage(),
                ['product_name' => $productName]
            );

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => "Validation error: {$e->getMessage()}",
            ];
        } catch (\Exception $e) {
            // Log unexpected error
            $this->logger->logFailedAction(
                $user,
                'delete_product',
                $e->getMessage(),
                ['product_name' => $productName]
            );

            return [
                'success' => false,
                'error' => 'Error inesperado al eliminar el producto',
                'message' => 'An error occurred while deleting the product. Please try again.',
            ];
        }
    }
}
