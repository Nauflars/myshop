<?php

require __DIR__.'/vendor/autoload.php';

use App\Infrastructure\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();

// Find user by ID that has the issue
$userHex = 'C15E880AC35941A98FA9EC844543433B';
$userId = hex2bin($userHex);

echo "Looking for user with ID (hex): $userHex\n";

$user = $entityManager->getRepository(App\Domain\Entity\User::class)->find($userId);

if (!$user) {
    echo "User not found!\n";
    exit(1);
}

echo "Found user: " . $user->getEmail() . "\n";
echo "User ID: " . $user->getId() . "\n";
echo "User ID (hex): " . bin2hex($user->getId()) . "\n\n";

// Check for existing cart using the repository method
$cartRepo = $entityManager->getRepository(App\Domain\Entity\Cart::class);
$cart = $cartRepo->findByUser($user);

echo "Cart found using repository: " . ($cart ? 'YES - ID: ' . $cart->getId() : 'NO') . "\n";
if ($cart) {
    echo "Cart items count: " . count($cart->getItems()) . "\n";
}

// Check for existing cart using query builder
$qb = $entityManager->createQueryBuilder();
$carts = $qb->select('c')
    ->from(App\Domain\Entity\Cart::class, 'c')
    ->where('IDENTITY(c.user) = :userId')
    ->setParameter('userId', $user->getId())
    ->getQuery()
    ->getResult();

echo "Carts found for user: " . count($carts) . "\n";

foreach ($carts as $cart) {
    echo "  - Cart ID: " . $cart->getId() . "\n";
    echo "    Items: " . count($cart->getItems()) . "\n";
}

// Check if there are orphaned carts
$allCarts = $entityManager->createQuery(
    'SELECT c FROM App\Domain\Entity\Cart c WHERE c.user = :userId'
)->setParameter('userId', $userId)->getResult();

echo "\nAll carts for this user ID: " . count($allCarts) . "\n";
