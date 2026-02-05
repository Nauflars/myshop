<?php

namespace App\Domain\Repository;

use App\Domain\Entity\Cart;
use App\Domain\Entity\User;

interface CartRepositoryInterface
{
    public function save(Cart $cart): void;

    public function findById(string $id): ?Cart;

    public function findByUser(User $user): ?Cart;

    public function delete(Cart $cart): void;
}
