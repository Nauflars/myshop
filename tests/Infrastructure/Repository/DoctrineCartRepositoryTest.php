<?php

namespace App\Tests\Infrastructure\Repository;

use App\Domain\Entity\Cart;
use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use App\Infrastructure\Repository\DoctrineCartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class DoctrineCartRepositoryTest extends TestCase
{
    public function testSaveCallsPersistAndFlush(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);

        $registry->method('getManagerForClass')->willReturn($entityManager);
        $entityManager->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $user = new User('Test', new Email('test@example.com'), 'hash');
        $cart = new Cart($user);

        $entityManager->expects($this->once())
            ->method('persist')
            ->with($cart);

        $entityManager->expects($this->once())
            ->method('flush');

        $repository = new DoctrineCartRepository($registry);
        $repository->save($cart);
    }

    public function testDeleteCallsRemoveAndFlush(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);

        $registry->method('getManagerForClass')->willReturn($entityManager);
        $entityManager->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $user = new User('Test', new Email('test@example.com'), 'hash');
        $cart = new Cart($user);

        $entityManager->expects($this->once())
            ->method('remove')
            ->with($cart);

        $entityManager->expects($this->once())
            ->method('flush');

        $repository = new DoctrineCartRepository($registry);
        $repository->delete($cart);
    }
}
