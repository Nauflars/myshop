<?php

declare(strict_types=1);

namespace App\Application\UseCase\Admin;

use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Money;

/**
 * UpdateProduct - Admin use case to update existing products
 * 
 * Part of spec-007: Admin Virtual Assistant
 * Validates updates and applies changes to products
 */
class UpdateProduct
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }

    /**
     * Find products by name (supports partial matching)
     *
     * @return Product[]
     */
    public function findProductsByName(string $name): array
    {
        return $this->productRepository->findByName($name);
    }

    /**
     * Update product with provided changes
     *
     * @param array $changes ['field' => 'value', ...]
     * @throws \InvalidArgumentException if validation fails
     */
    public function execute(Product $product, array $changes): Product
    {
        $this->validateChanges($changes);

        foreach ($changes as $field => $value) {
            match ($field) {
                'price' => $this->updatePrice($product, (float) $value),
                'stock' => $this->updateStock($product, (int) $value),
                'description' => $this->updateDescription($product, (string) $value),
                'category' => $this->updateCategory($product, (string) $value),
                'name' => $this->updateName($product, (string) $value),
                'nameEs' => $this->updateNameEs($product, (string) $value),
                default => throw new \InvalidArgumentException("Campo desconocido: {$field}"),
            };
        }

        $this->productRepository->save($product);

        return $product;
    }

    private function updatePrice(Product $product, float $newPrice): void
    {
        if ($newPrice <= 0) {
            throw new \InvalidArgumentException('El precio debe ser mayor que cero');
        }
        if ($newPrice > 1000000) {
            throw new \InvalidArgumentException('El precio no puede exceder 1,000,000');
        }

        $product->setPrice(new Money($newPrice, 'USD'));
    }

    private function updateStock(Product $product, int $newStock): void
    {
        if ($newStock < 0) {
            throw new \InvalidArgumentException('El stock no puede ser negativo');
        }
        if ($newStock > 1000000) {
            throw new \InvalidArgumentException('El stock no puede exceder 1,000,000 unidades');
        }

        $product->setStock($newStock);
    }

    private function updateDescription(Product $product, string $newDescription): void
    {
        $description = trim($newDescription);
        if ($description === '') {
            throw new \InvalidArgumentException('La descripción no puede estar vacía');
        }

        $product->setDescription($description);
    }

    private function updateCategory(Product $product, string $newCategory): void
    {
        $category = trim($newCategory);
        if ($category === '') {
            throw new \InvalidArgumentException('La categoría no puede estar vacía');
        }

        $product->setCategory($category);
    }

    private function updateName(Product $product, string $newName): void
    {
        $name = trim($newName);
        if ($name === '') {
            throw new \InvalidArgumentException('El nombre no puede estar vacío');
        }
        if (mb_strlen($name) > 255) {
            throw new \InvalidArgumentException('El nombre no puede exceder 255 caracteres');
        }

        $product->setName($name);
    }

    private function updateNameEs(Product $product, string $newNameEs): void
    {
        $nameEs = trim($newNameEs);
        if (mb_strlen($nameEs) > 255) {
            throw new \InvalidArgumentException('El nombre en español no puede exceder 255 caracteres');
        }

        $product->setNameEs($nameEs);
    }

    private function validateChanges(array $changes): void
    {
        if (empty($changes)) {
            throw new \InvalidArgumentException('No se especificaron cambios');
        }

        $allowedFields = ['price', 'stock', 'description', 'category', 'name', 'nameEs'];
        
        foreach ($changes as $field => $value) {
            if (!in_array($field, $allowedFields, true)) {
                throw new \InvalidArgumentException("Campo no permitido: {$field}");
            }
        }
    }

    /**
     * Get current values for confirmation
     */
    public function getCurrentValues(Product $product, array $fields): array
    {
        $values = [];

        foreach ($fields as $field) {
            $values[$field] = match ($field) {
                'price' => $product->getPrice(),
                'stock' => $product->getStock(),
                'description' => $product->getDescription(),
                'category' => $product->getCategory(),
                'name' => $product->getName(),
                'nameEs' => $product->getDisplayName('es'),
                default => null,
            };
        }

        return $values;
    }

    /**
     * Get field name in Spanish for user-friendly messages
     */
    public static function getFieldNameInSpanish(string $field): string
    {
        return match ($field) {
            'name' => 'nombre',
            'description' => 'descripción',
            'price' => 'precio',
            'stock' => 'stock',
            'category' => 'categoría',
            'nameEs' => 'nombre en español',
            default => $field,
        };
    }
}
