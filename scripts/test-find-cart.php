<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();
$cartRepository = $entityManager->getRepository(App\Domain\Entity\Cart::class);
$userRepository = $entityManager->getRepository(App\Domain\Entity\User::class);

// Find the user
$user = $userRepository->findOneBy(['email' => 'naoufal.lars@gmail.com']);

if (!$user) {
    echo "User not found!\n";
    exit(1);
}

echo 'User found: '.$user->getEmail()."\n";
echo 'User ID: '.$user->getId()."\n\n";

// Try method 1: Using repository
echo "=== Method 1: Using repository ===\n";
$cart = $cartRepository->findByUser($user);

if ($cart && is_object($cart) && $cart instanceof App\Domain\Entity\Cart) {
    echo "✓ Cart FOUND!\n";
    echo 'Cart ID: '.$cart->getId()."\n";
} else {
    echo "✗ Cart NOT found or invalid type\n";
    echo 'Type: '.(is_object($cart) ? get_class($cart) : gettype($cart))."\n";
}

// Try method 2: Using EntityManager directly
echo "\n=== Method 2: Using EntityManager directly ===\n";
$qb = $entityManager->createQueryBuilder();
$qb->select('c')
    ->from(App\Domain\Entity\Cart::class, 'c')
    ->where('c.user = :user')
    ->setParameter('user', $user);

$cart2 = $qb->getQuery()->getOneOrNullResult();

if ($cart2 && is_object($cart2) && $cart2 instanceof App\Domain\Entity\Cart) {
    echo "✓ Cart FOUND!\n";
    echo 'Cart ID: '.$cart2->getId()."\n";
    echo 'Items count: '.$cart2->getItems()->count()."\n";
    foreach ($cart2->getItems() as $item) {
        echo '  - '.$item->getProduct()->getName().' x '.$item->getQuantity()."\n";
    }
} else {
    echo "✗ Cart NOT found or invalid type\n";
    echo 'Type: '.(is_object($cart2) ? get_class($cart2) : gettype($cart2))."\n";
}
