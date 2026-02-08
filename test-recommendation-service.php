<?php

require_once __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->bootEnv(__DIR__ . '/.env');

$kernel = new Kernel($_ENV['APP_ENV'] ?? 'dev', (bool)$_ENV['APP_DEBUG'] ?? false);
$kernel->boot();
$container = $kernel->getContainer();

$userId = 'ab715ae6-7f01-4143-8e53-d5a215553d65';

echo "=== Testing RecommendationService ===\n\n";

// Get User entity
$entityManager = $container->get('doctrine.orm.entity_manager');
$userRepo = $entityManager->getRepository('App\Domain\Entity\User');
$user = $userRepo->find($userId);

if (!$user) {
    die("User not found!\n");
}

echo "User: {$user->getEmail()}\n\n";

// Get RecommendationService
$recommendationService = $container->get('App\Application\Service\RecommendationService');

echo "Getting recommendations...\n";
$result = $recommendationService->getRecommendationsForUser($user, 12);

echo "Result class: " . get_class($result) . "\n";
echo "Count: " . $result->count() . "\n";
echo "Is empty: " . ($result->isEmpty() ? 'yes' : 'no') . "\n";
echo "Average score: " . $result->getAverageScore() . "\n\n";

if (!$result->isEmpty()) {
    echo "Products:\n";
    foreach ($result->getProducts() as $index => $product) {
        $score = $result->getScores()[$index] ?? 'N/A';
        echo "  - {$product->getName()} (stock: {$product->getStock()}) [score: {$score}]\n";
    }
} else {
    echo "No products returned!\n";
}

echo "\n=== End Test ===\n";
