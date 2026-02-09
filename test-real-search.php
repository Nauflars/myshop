<?php

require_once 'vendor/autoload.php';

use App\Entity\SearchHistory;

$kernel = new App\Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

// Get services
$entityManager = $container->get('doctrine')->getManager();
$searchHistoryRepo = $entityManager->getRepository(SearchHistory::class);

// Find testmongo user
$userRepo = $entityManager->getRepository('App\Domain\Entity\User');
$user = $userRepo->findOneBy(['email' => 'testmongo@test.com']);

if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "✅ User: {$user->getEmail()}\n\n";

// Count current searches
$count = $searchHistoryRepo->countByUser($user);
echo "📊 Current search history count: $count\n";

// Show recent searches
$recent = $searchHistoryRepo->findRecentByUser($user, 5);
echo "\n📝 Recent searches:\n";
foreach ($recent as $search) {
    echo "  - {$search->getQuery()} ({$search->getMode()}) at {$search->getCreatedAt()->format('Y-m-d H:i:s')}\n";
}

// Simulate clicking search button with query
echo "\n🔍 Simulating search from form: 'gaming headset wireless'\n";

// Create new search (this is what ProductController should do)
$search = new SearchHistory($user, 'gaming headset wireless', 'keyword', null);
$searchHistoryRepo->save($search);

echo "  ✓ Search saved to database\n";

// Trigger profile update (this is what ProductController should do)
$profileUpdateService = $container->get('App\Application\Service\UserProfileUpdateService');
$profileUpdateService->scheduleProfileUpdate($user);

echo "  ✓ Profile update triggered\n";

// Verify saved
$newCount = $searchHistoryRepo->countByUser($user);
echo "\n📊 New search history count: $newCount\n";
echo "✅ Difference: +" . ($newCount - $count) . "\n";

// Wait a moment for async profile update
sleep(2);

// Check MongoDB profile
$mongoRepo = $container->get('App\Domain\Repository\MongoDBUserProfileRepository');
$profile = $mongoRepo->findByUserId((string) $user->getId());

if ($profile) {
    $snapshot = $profile->getSnapshot();
    echo "\n📦 MongoDB Profile:\n";
    echo "  - Recent Searches: " . count($snapshot->getRecentSearches()) . " items\n";
    if (!empty($snapshot->getRecentSearches())) {
        echo "    Latest: " . $snapshot->getRecentSearches()[0] . "\n";
    }
} else {
    echo "\n❌ MongoDB profile not found!\n";
}

echo "\n✅ Test complete!\n";
