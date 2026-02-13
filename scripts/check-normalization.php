<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__ . '/.env');

$mongoUrl = $_ENV['MONGODB_URL'] ?? 'mongodb://root:rootpassword@mongodb:27017';
$dbName = $_ENV['MONGODB_DATABASE'] ?? 'myshop';

echo "Checking Vector Normalization\n";
echo "==============================\n\n";

function calculateMagnitude(array $vector): float {
    $sum = 0;
    foreach ($vector as $val) {
        $sum += $val * $val;
    }
    return sqrt($sum);
}

try {
    $client = new MongoDB\Client($mongoUrl);
    $database = $client->selectDatabase($dbName);
    
    // Check user embedding
    $userCollection = $database->selectCollection('user_embeddings');
    $userEmbedding = $userCollection->findOne(['user_id' => 2004716280]);
    
    if ($userEmbedding) {
        $userVector = $userEmbedding['embedding']->getArrayCopy();
        $userMagnitude = calculateMagnitude($userVector);
        
        echo "User Embedding (ID: 2004716280):\n";
        echo "  Magnitude: " . round($userMagnitude, 6) . "\n";
        echo "  Expected: ~1.0 (if normalized)\n";
        echo "  Is Normalized: " . (abs($userMagnitude - 1.0) < 0.01 ? "✓ YES" : "✗ NO") . "\n\n";
    }
    
    // Check product embeddings
    $productCollection = $database->selectCollection('product_embeddings');
    $products = $productCollection->find([], ['limit' => 5]);
    
    echo "Product Embeddings (first 5):\n";
    echo "------------------------------\n";
    
    $productNormalized = true;
    foreach ($products as $product) {
        $productVector = $product['embedding']->getArrayCopy();
        $magnitude = calculateMagnitude($productVector);
        $isNorm = abs($magnitude - 1.0) < 0.01;
        
        if (!$isNorm) {
            $productNormalized = false;
        }
        
        echo "  Product: " . substr($product['product_id'], 0, 8) . "... \n";
        echo "    Magnitude: " . round($magnitude, 6) . "\n";
        echo "    Is Normalized: " . ($isNorm ? "✓ YES" : "✗ NO ($magnitude)") . "\n\n";
    }
    
    echo "\nDiagnosis:\n";
    echo "----------\n";
    
    if ($userEmbedding) {
        $userMag = calculateMagnitude($userEmbedding['embedding']->getArrayCopy());
        if (abs($userMag - 1.0) > 0.01) {
            echo "✗ User embedding is NOT normalized (magnitude: $userMag)\n";
            echo "  Solution: User embeddings need L2 normalization\n";
        } else {
            echo "✓ User embedding is normalized\n";
        }
    }
    
    if (!$productNormalized) {
        echo "✗ Some product embeddings are NOT normalized\n";
        echo "  Solution: Product embeddings need L2 normalization\n";
    } else {
        echo "✓ Product embeddings appear normalized\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
