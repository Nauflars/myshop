<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\AdminAssistantAction;
use App\Domain\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminAssistantAction>
 */
class AdminAssistantActionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminAssistantAction::class);
    }

    public function save(AdminAssistantAction $action): void
    {
        $this->getEntityManager()->persist($action);
        $this->getEntityManager()->flush();
    }

    /**
     * @return AdminAssistantAction[]
     */
    public function findRecentByUser(User $user, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.adminUser = :user')
            ->setParameter('user', $user)
            ->orderBy('a.performedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AdminAssistantAction[]
     */
    public function findByActionType(string $actionType, int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.actionType = :type')
            ->setParameter('type', $actionType)
            ->orderBy('a.performedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AdminAssistantAction[]
     */
    public function findProductActions(int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.actionType IN (:types)')
            ->setParameter('types', [
                AdminAssistantAction::ACTION_CREATE_PRODUCT,
                AdminAssistantAction::ACTION_UPDATE_PRODUCT,
                AdminAssistantAction::ACTION_DELETE_PRODUCT,
            ])
            ->orderBy('a.performedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AdminAssistantAction[]
     */
    public function findAnalyticsActions(int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.actionType IN (:types)')
            ->setParameter('types', [
                AdminAssistantAction::ACTION_QUERY_SALES,
                AdminAssistantAction::ACTION_QUERY_PRODUCT_STATS,
                AdminAssistantAction::ACTION_QUERY_TOP_PRODUCTS,
                AdminAssistantAction::ACTION_QUERY_USER_STATS,
            ])
            ->orderBy('a.performedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.adminUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByActionType(string $actionType): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.actionType = :type')
            ->setParameter('type', $actionType)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return array<string, int>
     */
    public function countByActionTypeGrouped(): array
    {
        $results = $this->createQueryBuilder('a')
            ->select('a.actionType as type, COUNT(a.id) as count')
            ->groupBy('a.actionType')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $grouped = [];
        foreach ($results as $result) {
            $grouped[$result['type']] = (int) $result['count'];
        }

        return $grouped;
    }

    /**
     * @return AdminAssistantAction[]
     */
    public function findFailedActions(int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.success = false')
            ->orderBy('a.performedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get actions within a date range.
     *
     * @return AdminAssistantAction[]
     */
    public function findByDateRange(\DateTimeImmutable $start, \DateTimeImmutable $end, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.performedAt >= :start')
            ->andWhere('a.performedAt <= :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('a.performedAt', 'DESC');

        if (null !== $user) {
            $qb->andWhere('a.adminUser = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Get action statistics summary.
     *
     * @return array{total: int, successful: int, failed: int, productActions: int, analyticsActions: int}
     */
    public function getStatisticsSummary(?User $user = null): array
    {
        $qb = $this->createQueryBuilder('a');

        if (null !== $user) {
            $qb->where('a.adminUser = :user')
                ->setParameter('user', $user);
        }

        $total = (int) $qb->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $qb2 = clone $qb;
        $successful = (int) $qb2->andWhere('a.success = true')
            ->getQuery()
            ->getSingleScalarResult();

        $qb3 = clone $qb;
        $productActions = (int) $qb3->andWhere('a.actionType IN (:types)')
            ->setParameter('types', [
                AdminAssistantAction::ACTION_CREATE_PRODUCT,
                AdminAssistantAction::ACTION_UPDATE_PRODUCT,
                AdminAssistantAction::ACTION_DELETE_PRODUCT,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        $qb4 = clone $qb;
        $analyticsActions = (int) $qb4->andWhere('a.actionType IN (:types)')
            ->setParameter('types', [
                AdminAssistantAction::ACTION_QUERY_SALES,
                AdminAssistantAction::ACTION_QUERY_PRODUCT_STATS,
                AdminAssistantAction::ACTION_QUERY_TOP_PRODUCTS,
                AdminAssistantAction::ACTION_QUERY_USER_STATS,
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'successful' => $successful,
            'failed' => $total - $successful,
            'productActions' => $productActions,
            'analyticsActions' => $analyticsActions,
        ];
    }
}
