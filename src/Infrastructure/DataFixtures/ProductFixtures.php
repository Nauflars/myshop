<?php

namespace App\Infrastructure\DataFixtures;

use App\Domain\Entity\Product;
use App\Domain\ValueObject\Money;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class ProductFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $products = [
            // Electronics
            ['name' => 'Laptop Pro 15"', 'description' => 'High-performance laptop with 16GB RAM', 'price' => 1299.99, 'stock' => 25, 'category' => 'Electronics'],
            ['name' => 'Wireless Mouse', 'description' => 'Ergonomic wireless mouse with USB receiver', 'price' => 29.99, 'stock' => 100, 'category' => 'Electronics'],
            ['name' => 'Mechanical Keyboard', 'description' => 'RGB backlit mechanical keyboard', 'price' => 89.99, 'stock' => 50, 'category' => 'Electronics'],
            ['name' => 'USB-C Hub', 'description' => '7-in-1 USB-C hub with HDMI and Ethernet', 'price' => 49.99, 'stock' => 75, 'category' => 'Electronics'],
            ['name' => 'Webcam HD', 'description' => '1080p HD webcam with microphone', 'price' => 79.99, 'stock' => 40, 'category' => 'Electronics'],

            // Clothing
            ['name' => 'Cotton T-Shirt', 'description' => '100% cotton comfortable t-shirt', 'price' => 19.99, 'stock' => 200, 'category' => 'Clothing'],
            ['name' => 'Jeans Classic', 'description' => 'Classic fit denim jeans', 'price' => 59.99, 'stock' => 150, 'category' => 'Clothing'],
            ['name' => 'Hoodie Zip-Up', 'description' => 'Warm zip-up hoodie with pockets', 'price' => 44.99, 'stock' => 80, 'category' => 'Clothing'],
            ['name' => 'Running Shoes', 'description' => 'Lightweight running shoes', 'price' => 89.99, 'stock' => 60, 'category' => 'Clothing'],
            ['name' => 'Winter Jacket', 'description' => 'Waterproof winter jacket', 'price' => 129.99, 'stock' => 30, 'category' => 'Clothing'],

            // Books
            ['name' => 'PHP Best Practices', 'description' => 'Modern PHP development guide', 'price' => 39.99, 'stock' => 50, 'category' => 'Books'],
            ['name' => 'Domain-Driven Design', 'description' => 'Eric Evans DDD classic', 'price' => 49.99, 'stock' => 35, 'category' => 'Books'],
            ['name' => 'Clean Code', 'description' => 'A handbook of agile software craftsmanship', 'price' => 44.99, 'stock' => 45, 'category' => 'Books'],
            ['name' => 'Design Patterns', 'description' => 'Elements of reusable object-oriented software', 'price' => 54.99, 'stock' => 30, 'category' => 'Books'],
            ['name' => 'The Pragmatic Programmer', 'description' => 'Your journey to mastery', 'price' => 42.99, 'stock' => 40, 'category' => 'Books'],

            // Home
            ['name' => 'Coffee Maker', 'description' => 'Programmable 12-cup coffee maker', 'price' => 79.99, 'stock' => 45, 'category' => 'Home'],
            ['name' => 'Desk Lamp LED', 'description' => 'Adjustable LED desk lamp', 'price' => 34.99, 'stock' => 70, 'category' => 'Home'],
            ['name' => 'Storage Organizer', 'description' => '5-drawer storage organizer', 'price' => 64.99, 'stock' => 35, 'category' => 'Home'],
            ['name' => 'Wall Clock', 'description' => 'Modern minimalist wall clock', 'price' => 24.99, 'stock' => 90, 'category' => 'Home'],
            ['name' => 'Throw Pillow Set', 'description' => 'Set of 4 decorative throw pillows', 'price' => 39.99, 'stock' => 55, 'category' => 'Home'],
        ];

        foreach ($products as $index => $productData) {
            $product = new Product(
                $productData['name'],
                $productData['description'],
                Money::fromDecimal($productData['price']),
                $productData['stock'],
                $productData['category']
            );

            $manager->persist($product);

            // Create some low-stock scenarios
            if (0 === $index % 5) {
                $product->setStock(5); // Low stock
            }

            $this->addReference("product-{$index}", $product);
        }

        $manager->flush();
    }
}
