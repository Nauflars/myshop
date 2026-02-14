<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Entity\AdminAssistantConversation;
use App\Domain\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AdminAssistantConversation>
 */
class AdminAssistantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AdminAssistantConversation::class);
    }

    public function save(AdminAssistantConversation $conversation): void
    {
        $this->getEntityManager()->persist($conversation);
        $this->getEntityManager()->flush();
    }

    public function findActiveByUser(User $user): ?AdminAssistantConversation
    {
        return $this->createQueryBuilder('c')
            ->where('c.adminUser = :user')
            ->andWhere('c.endedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('c.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findBySessionId(string $sessionId): ?AdminAssistantConversation
    {
        return $this->createQueryBuilder('c')
            ->where('c.sessionId = :sessionId')
            ->andWhere('c.endedAt IS NULL')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('c.startedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return AdminAssistantConversation[]
     */
    public function findRecentByUser(User $user, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.adminUser = :user')
            ->setParameter('user', $user)
            ->orderBy('c.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return AdminAssistantConversation[]
     */
    public function findActive(int $limit = 50): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.endedAt IS NULL')
            ->orderBy('c.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.adminUser = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countActiveConversations(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.endedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get conversations with message statistics.
     *
     * @return array<array{conversation: AdminAssistantConversation, messageCount: int, lastMessageAt: \DateTimeImmutable|null}>
     */
    public function findWithMessageStats(User $user, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.messages', 'm')
            ->where('c.adminUser = :user')
            ->setParameter('user', $user)
            ->groupBy('c.id')
            ->orderBy('c.startedAt', 'DESC')
            ->setMaxResults($limit);

        $conversations = $qb->getQuery()->getResult();

        $result = [];
        foreach ($conversations as $conversation) {
            $result[] = [
                'conversation' => $conversation,
                'messageCount' => $conversation->getMessageCount(),
                'lastMessageAt' => $conversation->getLastMessage()?->getSentAt(),
            ];
        }

        return $result;
    }
}
