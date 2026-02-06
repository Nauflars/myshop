<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\UnansweredQuestion;
use App\Domain\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * UnansweredQuestionRepository
 * 
 * Infrastructure repository for querying unanswered questions.
 * Spec: 006-unanswered-questions-admin
 */
class UnansweredQuestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UnansweredQuestion::class);
    }

    public function save(UnansweredQuestion $question): void
    {
        $this->getEntityManager()->persist($question);
        $this->getEntityManager()->flush();
    }

    /**
     * Find all questions with optional filtering and pagination
     * 
     * @param array{status?: string, reason?: string, user?: User, limit?: int, offset?: int} $criteria
     * @return UnansweredQuestion[]
     */
    public function findWithFilters(array $criteria = []): array
    {
        $qb = $this->createQueryBuilder('q')
            ->orderBy('q.askedAt', 'DESC');

        if (isset($criteria['status'])) {
            $qb->andWhere('q.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (isset($criteria['reason'])) {
            $qb->andWhere('q.reasonCategory = :reason')
               ->setParameter('reason', $criteria['reason']);
        }

        if (isset($criteria['user'])) {
            $qb->andWhere('q.user = :user')
               ->setParameter('user', $criteria['user']);
        }

        if (isset($criteria['limit'])) {
            $qb->setMaxResults($criteria['limit']);
        }

        if (isset($criteria['offset'])) {
            $qb->setFirstResult($criteria['offset']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Count questions by status
     * 
     * @return array<string, int>
     */
    public function countByStatus(): array
    {
        $results = $this->createQueryBuilder('q')
            ->select('q.status, COUNT(q.id) as count')
            ->groupBy('q.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['status']] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * Count questions by reason category
     * 
     * @return array<string, int>
     */
    public function countByReason(): array
    {
        $results = $this->createQueryBuilder('q')
            ->select('q.reasonCategory, COUNT(q.id) as count')
            ->groupBy('q.reasonCategory')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($results as $result) {
            $counts[$result['reasonCategory']] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * Get most recent unanswered questions
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('q')
            ->orderBy('q.askedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Count total questions
     */
    public function countTotal(): int
    {
        return (int) $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
