<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Repository\ProductRepositoryInterface;

/**
 * GetProductImagesByProductName Use Case - Retrieve product images by name.
 *
 * This use case fetches image URLs for a specific product by name.
 * Performs case-insensitive search and ensures exact match.
 * Used by AI tools to display product visuals without exposing internal IDs.
 *
 * Note: Current implementation returns placeholder. In production,
 * this would fetch actual image URLs from database or CDN.
 *
 * Architecture: Application layer (business logic)
 * DDD Role: Use case / application service
 *
 * @author AI Shopping Assistant Team
 */
class GetProductImagesByProductName
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
    ) {
    }

    /**
     * Execute the use case.
     *
     * @param string $productName Product name
     *
     * @return array{
     *     found: bool,
     *     productName: string,
     *     images: array<int, string>
     * } Product image information
     *
     * @throws \InvalidArgumentException If productName is empty
     */
    public function execute(string $productName): array
    {
        if (empty(trim($productName))) {
            throw new \InvalidArgumentException('Product name cannot be empty');
        }

        // Search for product by name
        $products = $this->productRepository->search(trim($productName), null, null, null);

        // Find exact case-insensitive match
        $product = null;
        foreach ($products as $p) {
            if (0 === strcasecmp($p->getName(), trim($productName))) {
                $product = $p;
                break;
            }
        }

        if (!$product) {
            return [
                'found' => false,
                'productName' => $productName,
                'images' => [],
            ];
        }

        // TODO: In production, fetch actual product images from database
        // For now, return placeholder image URLs based on product name/category
        $images = $this->generatePlaceholderImages($product->getName(), $product->getCategory());

        return [
            'found' => true,
            'productName' => $product->getName(),
            'images' => $images,
        ];
    }

    /**
     * Generate placeholder image URLs
     * In production, this method would be replaced with actual image fetching logic.
     *
     * @return array<int, string> Array of image URLs
     */
    private function generatePlaceholderImages(string $productName, string $category): array
    {
        // Generate placeholder URLs using placehold.co or similar service
        $slug = strtolower(str_replace(' ', '-', $productName));

        return [
            'https://placehold.co/600x400/png?text='.urlencode($productName),
            'https://placehold.co/600x400/cccccc/666666/png?text='.urlencode($category),
        ];
    }
}
