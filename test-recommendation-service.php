<?php

require 'vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

// Get services
$em = $container->get('doctrine.orm.entity_manager');
$embeddingRepo = $container->get('App\Domain\Repository\UserEmbeddingRepositoryInterface');
$cache = $container->get('cache.app');
$logger = $container->get('logger');

// Manually create the service
$recommendationService = new \App\Application\Service\RecommendationService(
    $embeddingRepo,
    $em,
    $cache,
    $logger
);

echo "=== TESTING RECOMMENDATION SERVICE ===\n\n";

// Get a user with embedding
$userRepo = $em->getRepository(App\Domain\Entity\User::class);

// Test with the user we know has an embedding
$userId = '48e672e1-48ca-44da-81e9-be7ef2c712fc'; // From previous test
$user = $userRepo->find($userId);

if (!$user) {
    echo "User not found: $userId\n";
    echo "Getting any user...\n";
    $user = $userRepo->findOneBy([]);
    if (!$user) {
        echo "No users in database!\n";
        exit(1);
    }
}

echo "Testing recommendations for user: " . $user->getId() . "\n";
echo "User email: " . $user->getEmail() . "\n\n";

$result = $recommendationService->getRecommendationsForUser($user, 20);

echo "Recommendations generated!\n";
echo "Total products: " . count($result->products) . "\n";
echo "Average score: " . number_format($result->averageScore, 4) . "\n";
echo "Is personalized: " . ($result->isPersonalized ? 'YES' : 'NO (fallback)') . "\n\n";

if (count($result->products) > 0) {
    echo "TOP RECOMMENDATIONS:\n\n";
    foreach ($result->products as $i => $product) {
        $score = $result->scores[$i] ?? 0;
        echo sprintf("%2d. Score: %.4f - %s (ID: %s)\n",
            $i + 1,
            $score,
            $product->getName(),
            $product->getId()
        );
    }
    echo "\n✓ SUCCESS: Recommendations are working!\n";
} else {
    echo "✗ NO RECOMMENDATIONS RETURNED\n";
    echo "Check logs for errors.\n";
}
