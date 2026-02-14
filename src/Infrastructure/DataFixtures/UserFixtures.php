<?php

namespace App\Infrastructure\DataFixtures;

use App\Domain\Entity\User;
use App\Domain\ValueObject\Email;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const ADMIN_USER_REFERENCE = 'admin-user';
    public const SELLER_USER_REFERENCE = 'seller-user';
    public const CUSTOMER_USER_REFERENCE = 'customer-user';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        // Admin User
        $admin = new User(
            'Admin User',
            new Email('admin@myshop.com'),
            'temp',
            'ROLE_ADMIN'
        );
        $admin->setPasswordHash($this->passwordHasher->hashPassword($admin, 'admin123'));
        $manager->persist($admin);
        $this->addReference(self::ADMIN_USER_REFERENCE, $admin);

        // Seller User
        $seller = new User(
            'Seller User',
            new Email('seller@myshop.com'),
            'temp',
            'ROLE_SELLER'
        );
        $seller->setPasswordHash($this->passwordHasher->hashPassword($seller, 'seller123'));
        $manager->persist($seller);
        $this->addReference(self::SELLER_USER_REFERENCE, $seller);

        // Customer Users
        for ($i = 1; $i <= 3; ++$i) {
            $customer = new User(
                "Customer {$i}",
                new Email("customer{$i}@myshop.com"),
                'temp',
                'ROLE_CUSTOMER'
            );
            $customer->setPasswordHash($this->passwordHasher->hashPassword($customer, 'customer123'));
            $manager->persist($customer);

            if (1 === $i) {
                $this->addReference(self::CUSTOMER_USER_REFERENCE, $customer);
            }
        }

        $manager->flush();
    }
}
