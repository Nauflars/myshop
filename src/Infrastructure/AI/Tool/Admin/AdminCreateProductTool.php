<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Tool\Admin;

use App\Application\Service\AdminAssistantLogger;
use App\Application\UseCase\Admin\CreateProduct;
use App\Domain\Entity\User;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

#[AsTool(
    'AdminCreateProductTool',
    'Crear un nuevo producto en el catálogo. Requiere: nombre, descripción, precio, stock y categoría. El asistente debe pedir confirmación antes de crear el producto. SOLO para usuarios ADMIN.'
)]
final class AdminCreateProductTool
{
    public function __construct(
        private readonly CreateProduct $createProduct,
        private readonly AdminAssistantLogger $logger,
        private readonly Security $security
    ) {
    }

    /**
     * Create a new product
     *
     * @param string $name Nombre del producto (obligatorio)
     * @param string $description Descripción del producto (obligatorio)
     * @param float $price Precio en USD (debe ser positivo)
     * @param int $stock Cantidad en inventario (no puede ser negativo)
     * @param string $category Categoría del producto (obligatorio)
     * @param string|null $nameEs Nombre en español (opcional)
     * @param bool $confirmed Confirmación explícita del administrador (requerida)
     */
    public function __invoke(
        string $name,
        string $description,
        float $price,
        int $stock,
        string $category,
        ?string $nameEs = null,
        bool $confirmed = false
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
                'message' => "Resumen del producto a crear:\n" .
                    "• Nombre: {$name}\n" .
                    "• Descripción: {$description}\n" .
                    "• Precio: \${$priceFormatted} USD\n" .
                    "• Stock: {$stock} unidades\n" .
                    "• Categoría: {$category}\n" .
                    ($nameEs ? "• Nombre en español: {$nameEs}\n" : "") .
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

            if ($nameEs !== null) {
                $data['nameEs'] = $nameEs;
            }

            // Check for missing fields
            $missingFields = CreateProduct::getMissingFields($data);
            if (!empty($missingFields)) {
                $fieldNames = array_map(
                    fn($field) => CreateProduct::getFieldNameInSpanish($field),
                    $missingFields
                );
                
                return [
                    'success' => false,
                    'missing_fields' => $missingFields,
                    'message' => 'Faltan campos obligatorios: ' . implode(', ', $fieldNames) . '. Por favor proporciónalos.',
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
                'message' => "Error de validación: {$e->getMessage()}",
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
                'message' => 'Ocurrió un error al crear el producto. Por favor intenta de nuevo.',
            ];
        }
    }
}
