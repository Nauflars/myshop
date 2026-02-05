<?php

namespace App\Tests\Infrastructure\Repository;

use App\Domain\Entity\Order;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Infrastructure\Repository\DoctrineOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class DoctrineOrderRepositoryTest extends TestCase
{
    public function testSaveCallsPersistAndFlush(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);

        $registry->method('getManagerForClass')->willReturn($entityManager);
        $entityManager->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $user = new User('Test', new Email('test@example.com'), 'hash');
        $order = new Order($user);

        $entityManager->expects($this->once())
            ->method('persist')
            ->with($order);

        $entityManager->expects($this->once())
            ->method('flush');

        $repository = new DoctrineOrderRepository($registry);
        $repository->save($order);
    }

    public function testDeleteCallsRemoveAndFlush(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);

        $registry->method('getManagerForClass')->willReturn($entityManager);
        $entityManager->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $user = new User('Test', new Email('test@example.com'), 'hash');
        $order = new Order($user);

        $entityManager->expects($this->once())
            ->method('remove')
            ->with($order);

        $entityManager->expects($this->once())
            ->method('flush');

        $repository = new DoctrineOrderRepository($registry);
        $repository->delete($order);
    }
}
