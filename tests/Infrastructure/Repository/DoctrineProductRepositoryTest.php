<?php

namespace App\Tests\Infrastructure\Repository;

use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use App\Infrastructure\Repository\DoctrineProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class DoctrineProductRepositoryTest extends TestCase
{
    public function testSaveCallsPersistAndFlush(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);

        $registry->method('getManagerForClass')->willReturn($entityManager);
        $entityManager->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $product = new Product('Test Product', 'A test product', new Money(1000, 'USD'), 10, 'Electronics');

        $entityManager->expects($this->once())
            ->method('persist')
            ->with($product);

        $entityManager->expects($this->once())
            ->method('flush');

        $repository = new DoctrineProductRepository($registry);
        $repository->save($product);
    }

    public function testDeleteCallsRemoveAndFlush(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);

        $registry->method('getManagerForClass')->willReturn($entityManager);
        $entityManager->method('getClassMetadata')->willReturn($this->createMock(\Doctrine\ORM\Mapping\ClassMetadata::class));

        $product = new Product('Test Product', 'A test product', new Money(1000, 'USD'), 10, 'Electronics');

        $entityManager->expects($this->once())
            ->method('remove')
            ->with($product);

        $entityManager->expects($this->once())
            ->method('flush');

        $repository = new DoctrineProductRepository($registry);
        $repository->delete($product);
    }
}
