<?php

require 'vendor/autoload.php';

$client = new MongoDB\Client('mongodb://root:rootpassword@mongodb:27017');
$database = $client->selectDatabase('myshop');

// Get a user with embedding
$userCollection = $database->selectCollection('user_embeddings');
$userDoc = $userCollection->findOne([], ['sort' => ['updated_at' => -1]]);

if (!$userDoc) {
    echo "No user embeddings found!\n";
    exit;
}

$userId = $userDoc['user_id'];
$userVector = $userDoc['vector'];

// Convert BSON array to PHP array
if ($userVector instanceof MongoDB\Model\BSONArray) {
    $userVector = $userVector->getArrayCopy();
}

echo "=== SIMULATING RECOMMENDATION FOR USER ===\n\n";
echo "User ID: $userId\n";
echo 'User vector dimensions: '.count($userVector)."\n\n";

// Fetch all product embeddings
$productCollection = $database->selectCollection('product_embeddings');
$allProducts = $productCollection->find([], [
    'projection' => [
        'product_id' => 1,
        'name' => 1,
        'embedding' => 1,
        '_id' => 0,
    ],
])->toArray();

echo 'Total products in database: '.count($allProducts)."\n\n";

// Calculate cosine similarity
function cosineSimilarity(array $a, array $b): float
{
    $dotProduct = 0.0;
    $magnitudeA = 0.0;
    $magnitudeB = 0.0;

    for ($i = 0; $i < count($a); ++$i) {
        $dotProduct += $a[$i] * $b[$i];
        $magnitudeA += $a[$i] * $a[$i];
        $magnitudeB += $b[$i] * $b[$i];
    }

    $magnitudeA = sqrt($magnitudeA);
    $magnitudeB = sqrt($magnitudeB);

    if (0 == $magnitudeA || 0 == $magnitudeB) {
        return 0.0;
    }

    return $dotProduct / ($magnitudeA * $magnitudeB);
}

$results = [];

foreach ($allProducts as $doc) {
    $docArray = $doc instanceof MongoDB\Model\BSONDocument ? $doc->getArrayCopy() : (array) $doc;

    if (!isset($docArray['embedding'])) {
        continue;
    }

    $productEmbedding = $docArray['embedding'];
    if ($productEmbedding instanceof MongoDB\Model\BSONArray) {
        $productEmbedding = $productEmbedding->getArrayCopy();
    }

    $similarity = cosineSimilarity($userVector, $productEmbedding);

    $results[] = [
        'product_id' => $docArray['product_id'],
        'name' => $docArray['name'] ?? 'Unknown',
        'score' => $similarity,
    ];
}

// Sort by score descending
usort($results, fn ($a, $b) => $b['score'] <=> $a['score']);

// Show top 20
$top20 = array_slice($results, 0, 20);

echo "TOP 20 RECOMMENDATIONS (sorted by similarity):\n\n";

$minThreshold = 0.01;
$aboveThreshold = 0;

foreach ($top20 as $i => $result) {
    $meetsThreshold = $result['score'] >= $minThreshold;
    if ($meetsThreshold) {
        ++$aboveThreshold;
    }

    $icon = $meetsThreshold ? '✓' : '✗';

    echo sprintf("%2d. %s Score: %.4f - %s (ID: %s)\n",
        $i + 1,
        $icon,
        $result['score'],
        $result['name'],
        $result['product_id']
    );
}

echo "\n".str_repeat('=', 70)."\n";
echo sprintf("Products above threshold (%.2f): %d / %d\n", $minThreshold, $aboveThreshold, count($top20));

if (0 == $aboveThreshold) {
    echo "\n⚠ WARNING: No products meet the minimum similarity threshold!\n";
    echo "This is why recommendations are not showing.\n\n";
    echo "Possible solutions:\n";
    echo "1. Lower the MIN_SIMILARITY_SCORE in RecommendationService (currently 0.35)\n";
    echo "2. Generate more diverse user interactions to improve embeddings\n";
    echo "3. Check if product embeddings match user interests\n";
} else {
    echo "\n✓ SUCCESS: $aboveThreshold products should appear as recommendations!\n";
}
