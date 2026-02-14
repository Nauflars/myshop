<?php

namespace App\Infrastructure\DataFixtures;

use App\Domain\Entity\Cart;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class CartFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        // Create cart for customer 1
        $customer = $this->getReference(UserFixtures::CUSTOMER_USER_REFERENCE, \App\Domain\Entity\User::class);
        $cart = new Cart($customer);

        // Add some products to cart
        $product1 = $this->getReference('product-0', \App\Domain\Entity\Product::class);
        $product2 = $this->getReference('product-5', \App\Domain\Entity\Product::class);

        $cart->addProduct($product1, 2);
        $cart->addProduct($product2, 1);

        $manager->persist($cart);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            ProductFixtures::class,
        ];
    }
}
