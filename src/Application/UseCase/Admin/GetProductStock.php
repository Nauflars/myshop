<?php

namespace App\Application\UseCase\Admin;

use App\Domain\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Get current stock information for a specific product
 * Part of spec-008 US2 - Inventory Management
 */
class GetProductStock
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private int $defaultLowStockThreshold = 10
    ) {
    }

    /**
     * @param string $productId Product UUID
     * @return array{success: bool, product: array, stock: int, status: string, is_low_stock: bool}
     */
    public function execute(string $productId): array
    {
        $product = $this->entityManager->find(Product::class, $productId);
        
        if (!$product) {
            throw new \InvalidArgumentException("Product not found: $productId");
        }

        $stock = $product->getStock();
        $isLowStock = $product->isLowStock($this->defaultLowStockThreshold);
        
        $status = match (true) {
            $stock === 0 => 'out_of_stock',
            $isLowStock => 'low_stock',
            default => 'in_stock',
        };

        return [
            'success' => true,
            'product' => [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'nameEs' => $product->getNameEs(),
                'category' => $product->getCategory(),
                'price' => $product->getPrice()->getAmountAsDecimal(),
                'currency' => $product->getPrice()->getCurrency(),
            ],
            'stock' => $stock,
            'status' => $status,
            'is_low_stock' => $isLowStock,
            'threshold' => $this->defaultLowStockThreshold,
        ];
    }

    /**
     * Get stock for multiple products by name search
     * 
     * @param string $searchTerm Search term to match product names
     * @return array{products: array, count: int}
     */
    public function searchByName(string $searchTerm): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
           ->from(Product::class, 'p')
           ->where('LOWER(p.name) LIKE LOWER(:term)')
           ->orWhere('LOWER(p.nameEs) LIKE LOWER(:term)')
           ->setParameter('term', '%' . $searchTerm . '%')
           ->orderBy('p.name', 'ASC')
           ->setMaxResults(20);

        $products = $qb->getQuery()->getResult();

        $result = [];
        /** @var Product $product */
        foreach ($products as $product) {
            $stock = $product->getStock();
            $isLowStock = $product->isLowStock($this->defaultLowStockThreshold);
            
            $status = match (true) {
                $stock === 0 => 'out_of_stock',
                $isLowStock => 'low_stock',
                default => 'in_stock',
            };

            $result[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'nameEs' => $product->getNameEs(),
                'category' => $product->getCategory(),
                'stock' => $stock,
                'status' => $status,
                'is_low_stock' => $isLowStock,
            ];
        }

        return [
            'products' => $result,
            'count' => count($result),
            'search_term' => $searchTerm,
        ];
    }
}
