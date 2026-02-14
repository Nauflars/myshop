<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Product;
use App\Domain\Repository\ProductRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoctrineProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function save(Product $product): void
    {
        $this->getEntityManager()->persist($product);
        $this->getEntityManager()->flush();
    }

    public function findById(string $id): ?Product
    {
        return $this->find($id);
    }

    public function findAll(): array
    {
        return $this->findBy([]);
    }

    public function delete(Product $product): void
    {
        $this->getEntityManager()->remove($product);
        $this->getEntityManager()->flush();
    }

    public function findByCategory(string $category): array
    {
        return $this->findBy(['category' => $category]);
    }

    public function search(
        ?string $query = null,
        ?string $category = null,
        ?int $minPrice = null,
        ?int $maxPrice = null,
    ): array {
        $qb = $this->createQueryBuilder('p');

        if (null !== $query && '' !== $query) {
            $qb->andWhere('p.name LIKE :query OR p.description LIKE :query')
                ->setParameter('query', '%'.$query.'%');
        }

        if (null !== $category && '' !== $category) {
            $qb->andWhere('p.category = :category')
                ->setParameter('category', $category);
        }

        if (null !== $minPrice) {
            $qb->andWhere('p.priceInCents >= :minPrice')
                ->setParameter('minPrice', $minPrice);
        }

        if (null !== $maxPrice) {
            $qb->andWhere('p.priceInCents <= :maxPrice')
                ->setParameter('maxPrice', $maxPrice);
        }

        return $qb->getQuery()->getResult();
    }

    public function findLowStock(int $threshold = 10): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.stock > 0')
            ->andWhere('p.stock < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('p.stock', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findTopProducts(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
