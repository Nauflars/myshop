<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();

// Get all cart items
$cartItems = $entityManager->createQuery(
    'SELECT ci, c, p FROM App\Domain\Entity\CartItem ci
     JOIN ci.cart c
     JOIN ci.product p'
)->getResult();

echo "=== CART ITEMS IN DATABASE ===\n\n";

if (empty($cartItems)) {
    echo "No cart items found in database.\n";
} else {
    foreach ($cartItems as $item) {
        echo 'Cart ID: '.$item->getCart()->getId()."\n";
        echo 'Product ID: '.$item->getProduct()->getId()."\n";
        echo 'Product Name: '.$item->getProduct()->getName()."\n";
        echo 'Quantity: '.$item->getQuantity()."\n";
        echo 'Price Snapshot: '.$item->getPriceSnapshot()->format().' ('.$item->getPriceSnapshot()->getAmountInCents()." cents)\n";
        echo 'Subtotal: '.$item->getSubtotal()->format().' ('.$item->getSubtotal()->getAmountInCents()." cents)\n";
        echo 'Currency: '.$item->getPriceSnapshot()->getCurrency()."\n";
        echo "---\n";
    }
}

echo "\n=== CARTS ===\n\n";

$carts = $entityManager->getRepository(App\Domain\Entity\Cart::class)->findAll();
foreach ($carts as $cart) {
    echo 'Cart ID: '.$cart->getId()."\n";
    echo 'User: '.$cart->getUser()->getEmail()."\n";
    echo 'Items count: '.$cart->getItemCount()."\n";
    echo 'Total: '.$cart->calculateTotal()->format()."\n";
    echo "---\n";
}
