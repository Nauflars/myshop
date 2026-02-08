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

echo "=== Testing Profile Retrieval ===\n\n";

// Get repository
$profileRepo = $container->get('App\Infrastructure\Repository\MongoDBUserProfileRepository');

echo "1. Testing findByUserId()...\n";
try {
    $profile = $profileRepo->findByUserId($userId);
    if ($profile) {
        echo "   ✓ Profile found!\n";
        echo "   - User ID: " . $profile->getUserId() . "\n";
        echo "   - Embedding length: " . count($profile->getEmbeddingVector()) . "\n";
        echo "   - Recent purchases: " . count($profile->getDataSnapshot()->getRecentPurchases()) . "\n";
    } else {
        echo "   ✗ Profile NOT found (returned null)\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
    echo "   Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n2. Testing findSimilarProducts()...\n";
try {
    if ($profile) {
        $embedding = $profile->getEmbeddingVector();
        echo "   - Query embedding length: " . count($embedding) . "\n";
        
        $results = $profileRepo->findSimilarProducts($embedding, 5);
        echo "   - Results count: " . count($results) . "\n";
        
        if (count($results) > 0) {
            echo "   ✓ Top 3 results:\n";
            foreach (array_slice($results, 0, 3) as $result) {
                echo "     - Product {$result['productId']}: score {$result['score']}\n";
            }
        } else {
            echo "   ✗ No results returned\n";
        }
    } else {
        echo "   Skipped (no profile)\n";
    }
} catch (\Exception $e) {
    echo "   ✗ Exception: " . $e->getMessage() . "\n";
}

echo "\n=== End Test ===\n";
