<?php

declare(strict_types=1);

namespace App\Application\UseCase\AI;

use App\Domain\Repository\ProductRepositoryInterface;

/**
 * GetProductImagesByProductId Use Case - Retrieve product images
 * 
 * This use case fetches image URLs for a specific product.
 * Used by AI tools to display product visuals to users.
 * 
 * Note: Current implementation returns placeholder. In production,
 * this would fetch actual image URLs from database or CDN.
 * 
 * Architecture: Application layer (business logic)
 * DDD Role: Use case / application service
 * 
 * @author AI Shopping Assistant Team
 */
class GetProductImagesByProductId
{
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository
    ) {
    }
    
    /**
     * Execute the use case
     *
     * @param string $productId Product UUID
     * @return array{
     *     found: bool,
     *     productId: string,
     *     productName: string|null,
     *     images: array<int, string>
     * } Product image information
     * @throws \InvalidArgumentException If productId is empty
     */
    public function execute(string $productId): array
    {
        if (empty($productId)) {
            throw new \InvalidArgumentException('Product ID cannot be empty');
        }
        
        // Find product by ID
        $product = $this->productRepository->findById($productId);
        
        if (!$product) {
            return [
                'found' => false,
                'productId' => $productId,
                'productName' => null,
                'images' => [],
            ];
        }
        
        // TODO: In production, fetch actual product images from database
        // For now, return placeholder image URLs based on product name/category
        $images = $this->generatePlaceholderImages($product->getName(), $product->getCategory());
        
        return [
            'found' => true,
            'productId' => (string) $product->getId(),
            'productName' => $product->getName(),
            'images' => $images,
        ];
    }
    
    /**
     * Generate placeholder image URLs
     * In production, this method would be replaced with actual image fetching logic
     *
     * @param string $productName
     * @param string $category
     * @return array<int, string> Array of image URLs
     */
    private function generatePlaceholderImages(string $productName, string $category): array
    {
        // Generate placeholder URLs using placehold.co or similar service
        $slug = strtolower(str_replace(' ', '-', $productName));
        
        return [
            "https://placehold.co/600x400/png?text=" . urlencode($productName),
            "https://placehold.co/600x400/cccccc/666666/png?text=" . urlencode($category),
        ];
    }
}
