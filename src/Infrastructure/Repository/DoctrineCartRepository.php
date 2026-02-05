<?php

namespace App\Infrastructure\Repository;

use App\Domain\Entity\Cart;
use App\Domain\Entity\User;
use App\Domain\Repository\CartRepositoryInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class DoctrineCartRepository extends ServiceEntityRepository implements CartRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Cart::class);
    }

    public function save(Cart $cart): void
    {
        $this->getEntityManager()->persist($cart);
        $this->getEntityManager()->flush();
    }

    public function findById(string $id): ?Cart
    {
        return $this->find($id);
    }

    public function findByUser(User $user): ?Cart
    {
        return $this->findOneBy(['user' => $user]);
    }

    public function delete(Cart $cart): void
    {
        $this->getEntityManager()->remove($cart);
        $this->getEntityManager()->flush();
    }
}
