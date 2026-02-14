<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool\Admin;

use App\Application\Service\AdminAssistantLogger;
use App\Application\UseCase\Admin\CreateProduct;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;

#[AsTool(
    'AdminCreateProductTool',
    'Create a new product in the catalog. Requires: name, description, price, stock, and category. Assistant must request confirmation before creating the product. ONLY for ADMIN users.'
)]
final class AdminCreateProductTool
{
    public function __construct(
        private readonly CreateProduct $createProduct,
        private readonly AdminAssistantLogger $logger,
        private readonly Security $security,
    ) {
    }

    /**
     * Create a new product.
     *
     * @param string      $name        Nombre del producto (obligatorio)
     * @param string      $description Descripción del producto (obligatorio)
     * @param float       $price       Precio en USD (debe ser positivo)
     * @param int         $stock       Cantidad en inventario (no puede ser negativo)
     * @param string      $category    Categoría del producto (obligatorio)
     * @param string|null $nameEs      Nombre en español (opcional)
     * @param bool        $confirmed   Confirmación explícita del administrador (requerida)
     */
    public function __invoke(
        string $name,
        string $description,
        float $price,
        int $stock,
        string $category,
        ?string $nameEs = null,
        bool $confirmed = false,
    ): array {
        // Verify admin role
        $user = $this->security->getUser();
        if (!$user instanceof User || !in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return [
                'success' => false,
                'error' => 'Acceso denegado. Solo administradores pueden crear productos.',
            ];
        }

        // Check if confirmation is required
        if (!$confirmed) {
            $priceFormatted = number_format($price, 2);

            return [
                'success' => false,
                'requires_confirmation' => true,
                'message' => "Resumen del producto a crear:\n".
                    "• Nombre: {$name}\n".
                    "• Descripción: {$description}\n".
                    "• Precio: \${$priceFormatted} USD\n".
                    "• Stock: {$stock} unidades\n".
                    "• Categoría: {$category}\n".
                    ($nameEs ? "• Nombre en español: {$nameEs}\n" : '').
                    "\n¿Confirmas la creación de este producto? Responde 'sí', 'confirmar' o 'adelante'.",
            ];
        }

        try {
            // Prepare data
            $data = [
                'name' => $name,
                'description' => $description,
                'price' => $price,
                'stock' => $stock,
                'category' => $category,
            ];

            if (null !== $nameEs) {
                $data['nameEs'] = $nameEs;
            }

            // Check for missing fields
            $missingFields = CreateProduct::getMissingFields($data);
            if (!empty($missingFields)) {
                $fieldNames = array_map(
                    fn ($field) => CreateProduct::getFieldNameInSpanish($field),
                    $missingFields
                );

                return [
                    'success' => false,
                    'missing_fields' => $missingFields,
                    'message' => 'Missing required fields: '.implode(', ', $fieldNames).'. Please provide them.',
                ];
            }

            // Create product
            $product = $this->createProduct->execute($data);

            // Log action
            $this->logger->logProductCreation(
                $user,
                $product->getId(),
                $data
            );

            return [
                'success' => true,
                'message' => "✓ Producto '{$name}' creado exitosamente",
                'product_id' => $product->getId(),
                'product_name' => $product->getName(),
                'price' => $product->getPrice(),
                'stock' => $product->getStock(),
            ];
        } catch (\InvalidArgumentException $e) {
            // Log failed action
            $this->logger->logFailedAction(
                $user,
                'create_product',
                $e->getMessage(),
                ['name' => $name, 'price' => $price]
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
                'create_product',
                $e->getMessage(),
                ['name' => $name]
            );

            return [
                'success' => false,
                'error' => 'Error inesperado al crear el producto',
                'message' => 'An error occurred while creating the product. Please try again.',
            ];
        }
    }
}
