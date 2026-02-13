<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

$conn = $container->get('doctrine')->getConnection();

// Get all carts with user emails
$sql = "SELECT 
    HEX(c.id) as cart_id, 
    HEX(c.user_id) as user_id_hex,
    u.email as user_email
FROM carts c
JOIN users u ON c.user_id = u.id
ORDER BY c.updated_at DESC
LIMIT 5";

$stmt = $conn->prepare($sql);
$result = $stmt->executeQuery();
$carts = $result->fetchAllAssociative();

echo "Carts in database:\n";
foreach ($carts as $cart) {
    echo "Cart: " . $cart['cart_id'] . "\n";
    echo "  User ID (hex): " . $cart['user_id_hex'] . "\n";
    echo "  User Email: " . $cart['user_email'] . "\n\n";
}
