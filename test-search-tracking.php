<?php

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env');

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

// Get services
$entityManager = $container->get('doctrine')->getManager();
$searchHistoryRepo = $entityManager->getRepository(\App\Entity\SearchHistory::class);
$profileRepo = $container->get(\App\Infrastructure\Repository\MongoDBUserProfileRepository::class);

// Find the test user
$userRepo = $entityManager->getRepository(\App\Domain\Entity\User::class);
$user = $userRepo->findOneBy(['email' => 'testmongo@test.com']);

if (!$user) {
    echo "❌ User testmongo@test.com not found\n";
    exit(1);
}

echo "✅ User found: {$user->getEmail()} (ID: {$user->getId()})\n\n";

// 1. Create some search history entries
echo "📝 Creating search history entries...\n";

$searches = [
    ['query' => 'gaming laptop high performance', 'mode' => 'semantic'],
    ['query' => 'wireless gaming mouse RGB', 'mode' => 'semantic'],
    ['query' => 'mechanical keyboard for gaming', 'mode' => 'keyword'],
    ['query' => 'USB headset microphone', 'mode' => 'semantic'],
];

foreach ($searches as $searchData) {
    $searchHistory = new \App\Entity\SearchHistory(
        $user,
        $searchData['query'],
        $searchData['mode']
    );
    $searchHistoryRepo->save($searchHistory);
    echo "  ✓ Saved: {$searchData['query']} ({$searchData['mode']})\n";
}

// 2. Check search history count
$count = $searchHistoryRepo->countByUser($user);
echo "\n📊 Total searches for user: {$count}\n";

// 3. List recent searches
echo "\n📋 Recent searches:\n";
$recentSearches = $searchHistoryRepo->findRecentByUser($user, 10);
foreach ($recentSearches as $search) {
    echo "  - {$search->getQuery()} ({$search->getMode()}) at {$search->getCreatedAt()->format('Y-m-d H:i:s')}\n";
}

// 4. Check MongoDB profile
echo "\n🔍 Checking MongoDB profile...\n";
$profile = $profileRepo->findByUserId($user->getId());

if (!$profile) {
    echo "  ⚠️  Profile not found in MongoDB (this is OK, it will be created on next search)\n";
} else {
    echo "  ✓ Profile found\n";
    $createdAt = $profile->getCreatedAt();
    $updatedAt = $profile->getUpdatedAt();
    echo "  📅 Created: " . ($createdAt instanceof \DateTimeInterface ? $createdAt->format('Y-m-d H:i:s') : $createdAt) . "\n";
    echo "  📅 Updated: " . ($updatedAt instanceof \DateTimeInterface ? $updatedAt->format('Y-m-d H:i:s') : $updatedAt) . "\n";

    $snapshot = $profile->getDataSnapshot();
    echo "\n📦 Data Snapshot:\n";
    echo "  - Recent Purchases: " . count($snapshot->getRecentPurchases()) . " items\n";
    if (count($snapshot->getRecentPurchases()) > 0) {
        echo "    " . implode(", ", array_slice($snapshot->getRecentPurchases(), 0, 3)) . "\n";
    }
    echo "  - Recent Searches: " . count($snapshot->getRecentSearches()) . " items\n";
    if (count($snapshot->getRecentSearches()) > 0) {
        echo "    " . implode(", ", array_slice($snapshot->getRecentSearches(), 0, 5)) . "\n";
    }
    echo "  - Dominant Categories: " . count($snapshot->getDominantCategories()) . " items\n";
    if (count($snapshot->getDominantCategories()) > 0) {
        echo "    " . implode(", ", $snapshot->getDominantCategories()) . "\n";
    }
    echo "  - Embedding Vector: " . count($profile->getEmbeddingVector()) . " dimensions\n";
}

// 5. Clear recommendation cache
echo "\n🗑️  Clearing recommendation cache...\n";
$cache = $container->get('cache.app');
$cache->delete("recommendations_{$user->getId()}_12");
$cache->delete("user_searches_{$user->getId()}");
echo "  ✓ Cache cleared\n";

echo "\n✅ Search history created! Now:\n";
echo "   1. Go to http://localhost:8080/products and make a search as testmongo@test.com\n";
echo "   2. This will trigger profile update automatically\n";
echo "   3. Then visit http://localhost:8080/ to see personalized recommendations\n";

