<?php

declare(strict_types=1);

namespace App\Application\UseCase\Admin;

use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use App\Domain\ValueObject\Money;

/**
 * CreateProduct - Admin use case to create new products.
 *
 * Part of spec-007: Admin Virtual Assistant
 * Validates data and creates product entities
 */
class CreateProduct
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * @throws \InvalidArgumentException if validation fails
     */
    public function execute(array $data): Product
    {
        // Validate required fields
        $this->validateData($data);

        // Extract and validate data
        $name = trim($data['name']);
        $description = trim($data['description']);
        $priceValue = (float) $data['price'];
        $stock = (int) $data['stock'];
        $category = trim($data['category']);

        // Create Money value object
        $price = new Money($priceValue, 'USD');

        // Create product
        $product = new Product($name, $description, $price, $stock, $category);

        // Set Spanish name if provided
        if (isset($data['nameEs']) && !empty(trim($data['nameEs']))) {
            $product->setNameEs(trim($data['nameEs']));
        }

        // Save product
        $this->productRepository->save($product);

        return $product;
    }

    private function validateData(array $data): void
    {
        $errors = [];

        // Validate name
        if (empty($data['name']) || '' === trim($data['name'])) {
            $errors[] = 'El nombre del producto es obligatorio';
        } elseif (mb_strlen(trim($data['name'])) > 255) {
            $errors[] = 'El nombre no puede exceder 255 caracteres';
        }

        // Validate description
        if (empty($data['description']) || '' === trim($data['description'])) {
            $errors[] = 'La descripción del producto es obligatoria';
        }

        // Validate price
        if (!isset($data['price'])) {
            $errors[] = 'El precio es obligatorio';
        } else {
            $price = (float) $data['price'];
            if ($price <= 0) {
                $errors[] = 'El precio debe ser mayor que cero';
            }
            if ($price > 1000000) {
                $errors[] = 'El precio no puede exceder 1,000,000';
            }
        }

        // Validate stock
        if (!isset($data['stock'])) {
            $errors[] = 'El stock es obligatorio';
        } else {
            $stock = (int) $data['stock'];
            if ($stock < 0) {
                $errors[] = 'El stock no puede ser negativo';
            }
            if ($stock > 1000000) {
                $errors[] = 'El stock no puede exceder 1,000,000 unidades';
            }
        }

        // Validate category
        if (empty($data['category']) || '' === trim($data['category'])) {
            $errors[] = 'La categoría es obligatoria';
        }

        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode('. ', $errors));
        }
    }

    /**
     * Check if required fields are present.
     *
     * @return array List of missing required field names
     */
    public static function getMissingFields(array $data): array
    {
        $required = ['name', 'description', 'price', 'stock', 'category'];
        $missing = [];

        foreach ($required as $field) {
            if (!isset($data[$field]) || (is_string($data[$field]) && '' === trim($data[$field]))) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * Get field name in Spanish for user-friendly messages.
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
