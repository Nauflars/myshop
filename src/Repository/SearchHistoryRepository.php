<?php

declare(strict_types=1);

namespace App\Repository;

use App\Domain\Entity\User;
use App\Entity\SearchHistory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class SearchHistoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SearchHistory::class);
    }

    public function save(SearchHistory $searchHistory): void
    {
        $this->getEntityManager()->persist($searchHistory);
        $this->getEntityManager()->flush();
    }

    public function findRecentByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('sh')
            ->where('sh.user = :user')
            ->setParameter('user', $user)
            ->orderBy('sh.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return $this->createQueryBuilder('sh')
            ->select('COUNT(sh.id)')
            ->where('sh.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
