<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
(new Dotenv())->bootEnv(__DIR__ . '/.env');

$mongoUrl = $_ENV['MONGODB_URL'] ?? 'mongodb://root:rootpassword@mongodb:27017';
$dbName = $_ENV['MONGODB_DATABASE'] ?? 'myshop';

echo "Testing Recommendation Flow\n";
echo "============================\n\n";

try {
    $client = new MongoDB\Client($mongoUrl);
    $database = $client->selectDatabase($dbName);
    
    $userId = 2004716280;
    
    // 1. Get user embedding
    $userCollection = $database->selectCollection('user_embeddings');
    $userEmbedding = $userCollection->findOne(['user_id' => $userId]);
    
    if (!$userEmbedding) {
        echo "✗ User embedding NOT found for user_id: {$userId}\n";
        exit(1);
    }
    
    echo "✓ User embedding found\n";
    echo "  User ID: {$userEmbedding['user_id']}\n";
    echo "  Vector dimension: " . count($userEmbedding['embedding']->getArrayCopy()) . "\n\n";
    
    // 2. Check product embeddings
    $productCollection = $database->selectCollection('product_embeddings');
    $productCount = $productCollection->countDocuments([]);
    
    echo "Product embeddings in collection: {$productCount}\n";
    
    if ($productCount === 0) {
        echo "✗ NO PRODUCT EMBEDDINGS FOUND!\n";
        echo "This is the problem - cannot recommend without product embeddings.\n";
        exit(1);
    }
    
    echo "✓ Product embeddings exist\n\n";
    
    // 3. Simulate vector search
    echo "Simulating vector search...\n";
    echo "----------------------------\n";
    
    $userVector = $userEmbedding['embedding']->getArrayCopy();
    $allProducts = $productCollection->find([], ['limit' => 10]);
    
    $similarities = [];
    foreach ($allProducts as $product) {
        $productId = $product['product_id'];
        $productVector = $product['embedding']->getArrayCopy();
        
        // Calculate cosine similarity
        $dotProduct = 0;
        $magnitude1 = 0;
        $magnitude2 = 0;
        
        for ($i = 0; $i < min(count($userVector), count($productVector)); $i++) {
            $dotProduct += $userVector[$i] * $productVector[$i];
            $magnitude1 += $userVector[$i] * $userVector[$i];
            $magnitude2 += $productVector[$i] * $productVector[$i];
        }
        
        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);
        
        $similarity = 0;
        if ($magnitude1 > 0 && $magnitude2 > 0) {
            $similarity = $dotProduct / ($magnitude1 * $magnitude2);
        }
        
        $similarities[] = [
            'product_id' => $productId,
            'score' => $similarity
        ];
    }
    
    // Sort by score
    usort($similarities, fn($a, $b) => $b['score'] <=> $a['score']);
    
    echo "\nTop 5 recommendations:\n";
    foreach (array_slice($similarities, 0, 5) as $i => $result) {
        $num = $i + 1;
        echo "  {$num}. Product: {$result['product_id']}, Score: " . round($result['score'], 4) . "\n";
    }
    
    // Filter by minimum score
    $minScore = 0.35;
    $filtered = array_filter($similarities, fn($item) => $item['score'] >= $minScore);
    
    echo "\n\nFiltered by min score ({$minScore}):\n";
    echo "  Total: " . count($filtered) . " products\n";
    
    if (count($filtered) === 0) {
        echo "\n✗ PROBLEM FOUND: No products meet minimum similarity score of {$minScore}\n";
        echo "  This means the user embedding is not matching well with product embeddings.\n";
        echo "\n  Top scores we got:\n";
        foreach (array_slice($similarities, 0, 5) as $i => $result) {
            echo "    - " . round($result['score'], 4) . "\n";
        }
    } else {
        echo "✓ Found " . count($filtered) . " products above minimum score\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
