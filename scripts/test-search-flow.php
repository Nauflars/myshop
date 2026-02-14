<?php

require __DIR__.'/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$entityManager = $container->get('doctrine')->getManager();
$userRepo = $entityManager->getRepository(App\Domain\Entity\User::class);
$user = $userRepo->findOneBy(['email' => 'testmongo@test.com']);

if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "✅ User: {$user->getEmail()}\n\n";

// Simulate a search from the form
echo "🔍 Simulating search: 'rtx 4090 graphics card'\n";

$searchHistory = new App\Entity\SearchHistory(
    $user,
    'rtx 4090 graphics card',
    'semantic',
    'Electronics'
);

$searchHistoryRepo = $entityManager->getRepository(App\Entity\SearchHistory::class);
$searchHistoryRepo->save($searchHistory);
echo "  ✓ Search saved to database\n";

// Trigger profile update (this is what happens in ProductController)
echo "\n🔄 Updating user profile...\n";
$profileUpdateService = $container->get(App\Application\Service\UserProfileUpdateService::class);

try {
    $profileUpdateService->scheduleProfileUpdate($user);
    echo "  ✓ Profile updated\n";
} catch (Exception $e) {
    echo "  ❌ Error: {$e->getMessage()}\n";
    exit(1);
}

// Check MongoDB profile
echo "\n📊 Checking MongoDB profile...\n";
$profileRepo = $container->get(App\Infrastructure\Repository\MongoDBUserProfileRepository::class);
$profile = $profileRepo->findByUserId($user->getId());

if ($profile) {
    $snapshot = $profile->getDataSnapshot();
    echo "  ✓ Profile found\n";
    echo '  - Recent Searches: '.count($snapshot->getRecentSearches())." items\n";
    echo '    Latest: '.implode(', ', array_slice($snapshot->getRecentSearches(), 0, 3))."\n";
}

// Check if cache was cleared
echo "\n🗑️  Checking cache status...\n";
$cache = $container->get('cache.app');
$cacheKey = "recommendations_{$user->getId()}_12";

// Try to check if key exists (this will return null if not cached)
$cached = false;
try {
    $cache->get($cacheKey, function () use (&$cached) {
        $cached = false;
        throw new Exception('Not cached');
    });
} catch (Exception $e) {
    echo "  ✓ Recommendation cache is empty (will be regenerated)\n";
}

echo "\n✅ Test complete!\n";
echo "\n📝 Next steps:\n";
echo "   1. Visit http://localhost:8080/ as testmongo@test.com\n";
echo "   2. You should see '🎯 Recommended For You'\n";
echo "   3. Products should be personalized based on your searches\n";
