<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool\Admin;

use App\Application\Service\AdminAssistantLogger;
use App\Application\UseCase\Admin\UpdateProduct;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'AdminUpdateProductTool',
    'Update an existing product. Updatable fields: price, stock, description, category, name, nameEs. Assistant must request confirmation before updating. If multiple products match the name, show numbered list for disambiguation. ONLY for ADMIN users.'
)]
final class AdminUpdateProductTool
{
    public function __construct(
        private readonly UpdateProduct $updateProduct,
        private readonly AdminAssistantLogger $logger,
        private readonly Security $security
    ) {
    }

    /**
     * Update an existing product
     *
     * @param string $productName Nombre del producto a actualizar
     * @param string $field Campo a actualizar: price, stock, description, category, name, nameEs
     * @param mixed $value Nuevo valor para el campo
     * @param int|null $disambiguation_index Si hay múltiples productos con el mismo nombre, índice del producto seleccionado (1-based)
     * @param bool $confirmed Confirmación explícita del administrador (requerida)
     */
    public function __invoke(
        string $productName,
        string $field,
        mixed $value,
        ?int $disambiguation_index = null,
        bool $confirmed = false
    ): array {
        // Verify admin role
        $user = $this->security->getUser();
        if (!$user instanceof User || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return [
                'success' => false,
                'error' => 'Acceso denegado. Solo administradores pueden actualizar productos.',
            ];
        }

        try {
            // Find products by name
            $products = $this->updateProduct->findProductsByName($productName);

            if (empty($products)) {
                return [
                    'success' => false,
                    'error' => "No se encontró el producto '{$productName}'",
                    'message' => "No product exists with the name '{$productName}'. Do you want to create a new one?",
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
                        "\n\n¿Cuál de estos productos deseas actualizar? Responde con el número.",
                    'product_count' => count($products),
                ];
            }

            // Select the  product
            $selectedProductIndex = $disambiguation_index !== null ? $disambiguation_index - 1 : 0;
            
            if (!isset($products[$selectedProductIndex])) {
                return [
                    'success' => false,
                    'error' => "Índice inválido. Por favor selecciona un número entre 1 y " . count($products),
                ];
            }

            $product = $products[$selectedProductIndex];

            // Get current values for confirmation
            $currentValues = $this->updateProduct->getCurrentValues($product, $field);

            // Check if confirmation is required
            if (!$confirmed) {
                $fieldNameEs = UpdateProduct::getFieldNameInSpanish($field);
                $displayValue = is_numeric($value) && in_array($field, ['price']) 
                    ? '$' . number_format((float)$value, 2) 
                    : $value;

                return [
                    'success' => false,
                    'requires_confirmation' => true,
                    'message' => "Update summary:\n" .
                        "• Producto: {$product->getName()}\n" .
                        "• Campo: {$fieldNameEs}\n" .
                        "• Valor actual: {$currentValues['current_value']}\n" .
                        "• Nuevo valor: {$displayValue}\n" .
                        "\n¿Confirmas esta actualización? Responde 'sí', 'confirmar' o 'adelante'.",
                    'current_value' => $currentValues['current_value'],
                    'new_value' => $displayValue,
                ];
            }

            // Execute update
            $updatedProduct = $this->updateProduct->execute($product->getId(), $field, $value);

            // Log action
            $this->logger->logProductUpdate(
                $user,
                $product->getId(),
                [$field => $value],
                $currentValues
            );

            $fieldNameEs = UpdateProduct::getFieldNameInSpanish($field);
            $displayValue = is_numeric($value) && in_array($field, ['price']) 
                ? '$' . number_format((float)$value, 2) 
                : $value;

            return [
                'success' => true,
                'message' => "✓ Producto '{$product->getName()}' actualizado exitosamente",
                'product_id' => $updatedProduct->getId(),
                'product_name' => $updatedProduct->getName(),
                'updated_field' => $fieldNameEs,
                'new_value' => $displayValue,
                'previous_value' => $currentValues['current_value'],
            ];

        } catch (\InvalidArgumentException $e) {
            // Log failed action
            $this->logger->logFailedAction(
                $user,
                'update_product',
                $e->getMessage(),
                ['product_name' => $productName, 'field' => $field, 'value' => $value]
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
                'update_product',
                $e->getMessage(),
                ['product_name' => $productName, 'field' => $field]
            );

            return [
                'success' => false,
                'error' => 'Error inesperado al actualizar el producto',
                'message' => 'An error occurred while updating the product. Please try again.',
            ];
        }
    }
}
