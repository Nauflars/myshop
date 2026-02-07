<?php

namespace App\Application\UseCase\Admin;

use App\Domain\Entity\Product;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Get products with low stock levels
 * Part of spec-008 US2 - Inventory Management
 */
class GetLowStockProducts
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private int $defaultThreshold = 10
    ) {
    }

    /**
     * @return array{products: array, threshold: int, count: int}
     */
    public function execute(?int $threshold = null): array
    {
        $threshold = $threshold ?? $this->defaultThreshold;
        
        if ($threshold < 0) {
            throw new \InvalidArgumentException('Threshold cannot be negative');
        }

        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('p')
           ->from(Product::class, 'p')
           ->where('p.stock > 0')
           ->andWhere('p.stock < :threshold')
           ->setParameter('threshold', $threshold)
           ->orderBy('p.stock', 'ASC')
           ->addOrderBy('p.name', 'ASC');

        $products = $qb->getQuery()->getResult();

        $result = [];
        /** @var Product $product */
        foreach ($products as $product) {
            $result[] = [
                'id' => $product->getId(),
                'name' => $product->getName(),
                'nameEs' => $product->getNameEs(),
                'category' => $product->getCategory(),
                'stock' => $product->getStock(),
                'price' => $product->getPrice()->getAmountAsDecimal(),
                'currency' => $product->getPrice()->getCurrency(),
            ];
        }

        return [
            'products' => $result,
            'threshold' => $threshold,
            'count' => count($result),
        ];
    }
}
