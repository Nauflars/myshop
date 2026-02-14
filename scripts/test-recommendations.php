<?php

require 'vendor/autoload.php';

$client = new MongoDB\Client('mongodb://root:rootpassword@mongodb:27017');
$database = $client->selectDatabase('myshop');
$collection = $database->selectCollection('user_embeddings');

echo "=== USER EMBEDDINGS IN MONGODB ===\n\n";

$cursor = $collection->find([], ['sort' => ['updated_at' => -1]]);
$count = 0;

foreach ($cursor as $doc) {
    ++$count;
    echo sprintf("User ID: %s\n", $doc['user_id']);
    echo sprintf("  Version: %d\n", $doc['version']);
    echo sprintf("  Vector dimensions: %d\n", count($doc['vector']));
    echo sprintf("  Created: %s\n", $doc['created_at']->toDateTime()->format('Y-m-d H:i:s'));
    echo sprintf("  Updated: %s\n", $doc['updated_at']->toDateTime()->format('Y-m-d H:i:s'));
    echo "\n";
}

echo "Total user embeddings: $count\n";

// Check similarity between user vectors (if multiple exist)
if ($count > 1) {
    echo "\n=== TESTING SIMILARITY SEARCH ===\n\n";

    // Get first user's vector
    $firstUser = $collection->findOne([], ['sort' => ['updated_at' => -1]]);
    $userVector = $firstUser['vector'];

    echo 'Testing recommendations for user: '.$firstUser['user_id']."\n";
    echo "Minimum similarity threshold: 0.35\n\n";

    // Test vector search (simulating what RecommendationService does)
    $productCollection = $database->selectCollection('product_embeddings');

    $pipeline = [
        [
            '$vectorSearch' => [
                'index' => 'product_embeddings_vector_index',
                'path' => 'vector',
                'queryVector' => $userVector,
                'numCandidates' => 100,
                'limit' => 20,
            ],
        ],
        [
            '$project' => [
                'product_id' => 1,
                'name' => 1,
                'score' => ['$meta' => 'vectorSearchScore'],
            ],
        ],
    ];

    try {
        $results = $productCollection->aggregate($pipeline)->toArray();

        echo 'Products found: '.count($results)."\n\n";

        foreach ($results as $result) {
            $score = $result['score'] ?? 0;
            $meetsThreshold = $score >= 0.35 ? '✓' : '✗';
            echo sprintf("%s Product ID: %d, Score: %.4f, Name: %s\n",
                $meetsThreshold,
                $result['product_id'],
                $score,
                $result['name'] ?? 'N/A'
            );
        }

        $aboveThreshold = array_filter($results, fn ($r) => ($r['score'] ?? 0) >= 0.35);
        echo "\nProducts above threshold (0.35): ".count($aboveThreshold)."\n";
    } catch (Exception $e) {
        echo 'Error performing vector search: '.$e->getMessage()."\n";
    }
}
