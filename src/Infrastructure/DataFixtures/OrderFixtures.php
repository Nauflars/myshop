<?php

namespace App\Infrastructure\DataFixtures;

use App\Domain\Entity\Order;
use App\Domain\Entity\OrderItem;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OrderFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $customer = $this->getReference(UserFixtures::CUSTOMER_USER_REFERENCE, \App\Domain\Entity\User::class);

        // Create 5 sample orders with different statuses
        $statuses = [
            Order::STATUS_PENDING,
            Order::STATUS_CONFIRMED,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            Order::STATUS_PENDING,
        ];

        foreach ($statuses as $index => $status) {
            $order = new Order($customer);
            
            // Add 2-3 items to each order
            $itemCount = rand(2, 3);
            for ($i = 0; $i < $itemCount; $i++) {
                $product = $this->getReference("product-" . (($index * 3) + $i), \App\Domain\Entity\Product::class);
                $orderItem = new OrderItem(
                    $order,
                    $product,
                    rand(1, 3),
                    $product->getPrice()
                );
                $order->addItem($orderItem);
            }

            $order->setStatus($status);
            $manager->persist($order);
        }

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
