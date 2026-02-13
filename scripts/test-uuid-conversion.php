<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool)$_ENV['APP_DEBUG'] ?? false);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== Testing UUID Conversion Fix ===\n\n";

// Test data: UUIDs from MongoDB (with dashes)
$mongoUUIDs = [
    'f2e637f9-c9b6-4246-a25c-923d27572731',
    '5bc6fa75-7a20-4b59-86ac-03e87f2864ce',
    '38560ec8-6aa1-4e9d-9679-6763f2dd1439',
];

echo "1. Converting UUID strings to Uuid objects...\n";
$uuidObjects = array_map(
    fn($id) => \Symfony\Component\Uid\Uuid::fromString($id),
    $mongoUUIDs
);

echo "  ✓ Converted " . count($uuidObjects) . " UUIDs\n";
echo "  - First UUID type: " . get_class($uuidObjects[0]) . "\n\n";

echo "2. Querying MySQL with Uuid objects...\n";
$entityManager = $container->get('doctrine.orm.entity_manager');
$qb = $entityManager->createQueryBuilder();
$qb->select('p')
    ->from('App\Domain\Entity\Product', 'p')
    ->where($qb->expr()->in('p.id', ':productIds'))
    ->andWhere('p.stock > 0')
    ->setParameter('productIds', $uuidObjects);

$products = $qb->getQuery()->getResult();

echo "  ✓ Found " . count($products) . " products\n\n";

if (count($products) > 0) {
    echo "3. Products found:\n";
    foreach ($products as $product) {
        echo "  - {$product->getName()} (stock: {$product->getStock()})\n";
    }
    echo "\n✅ UUID conversion is working correctly!\n";
} else {
    echo "❌ No products found - UUID conversion may have issues\n";
}

echo "\n=== End Test ===\n";
