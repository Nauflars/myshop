<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Conversation;
use App\Domain\Entity\User;
use App\Domain\Repository\ConversationRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

class DoctrineConversationRepository implements ConversationRepositoryInterface
{
    private EntityRepository $repository;

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
        $this->repository = $entityManager->getRepository(Conversation::class);
    }

    public function save(Conversation $conversation): void
    {
        $this->entityManager->persist($conversation);
        $this->entityManager->flush();
    }

    public function findById(string $id): ?Conversation
    {
        return $this->repository->find($id);
    }

    public function findByUser(User $user): array
    {
        return $this->repository->findBy(
            ['user' => $user],
            ['updatedAt' => 'DESC']
        );
    }

    public function findActiveForUser(User $user): ?Conversation
    {
        return $this->repository->findOneBy(
            ['user' => $user],
            ['updatedAt' => 'DESC']
        );
    }

    public function delete(Conversation $conversation): void
    {
        $this->entityManager->remove($conversation);
        $this->entityManager->flush();
    }
}
