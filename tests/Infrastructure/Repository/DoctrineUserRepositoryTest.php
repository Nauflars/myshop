<?php

namespace App\Tests\Infrastructure\Repository;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Infrastructure\Repository\DoctrineUserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class DoctrineUserRepositoryTest extends TestCase
{
    public function testSaveCallsPersistAndFlush(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);

        $registry->method('getManagerForClass')->willReturn($entityManager);
        $entityManager->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $user = new User('Test User', new Email('test@example.com'), 'hash123');

        $entityManager->expects($this->once())
            ->method('persist')
            ->with($user);

        $entityManager->expects($this->once())
            ->method('flush');

        $repository = new DoctrineUserRepository($registry);
        $repository->save($user);
    }

    public function testDeleteCallsRemoveAndFlush(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);

        $registry->method('getManagerForClass')->willReturn($entityManager);
        $entityManager->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $user = new User('Test User', new Email('test@example.com'), 'hash123');

        $entityManager->expects($this->once())
            ->method('remove')
            ->with($user);

        $entityManager->expects($this->once())
            ->method('flush');

        $repository = new DoctrineUserRepository($registry);
        $repository->delete($user);
    }
}
