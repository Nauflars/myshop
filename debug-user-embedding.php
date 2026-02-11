<?php

require_once __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

// Load environment variables
(new Dotenv())->bootEnv(__DIR__ . '/.env');

$mongoUrl = $_ENV['MONGODB_URL'] ?? 'mongodb://root:rootpassword@mongodb:27017';
$dbName = $_ENV['MONGODB_DATABASE'] ?? 'myshop';

echo "Connecting to MongoDB: {$mongoUrl}\n";
echo "Database: {$dbName}\n\n";

try {
    $client = new MongoDB\Client($mongoUrl);
    $database = $client->selectDatabase($dbName);
    $collection = $database->selectCollection('user_embeddings');
    
    $userId = 2004716280;
    
    echo "Searching for user_id: {$userId}\n";
    echo "======================================\n\n";
    
    $embedding = $collection->findOne(['user_id' => $userId]);
    
    if ($embedding) {
        echo "✓ Embedding FOUND!\n\n";
        echo "User ID: " . $embedding['user_id'] . "\n";
        echo "Version: " . $embedding['version'] . "\n";
        echo "Dimension Count: " . $embedding['dimension_count'] . "\n";
        echo "Last Updated: " . $embedding['last_updated']->toDateTime()->format('Y-m-d H:i:s') . "\n";
        
        if (isset($embedding['embedding'])) {
            $vectorLength = is_array($embedding['embedding']) 
                ? count($embedding['embedding']) 
                : count($embedding['embedding']->getArrayCopy());
            echo "Vector Length: " . $vectorLength . "\n";
            
            // Show first 5 values
            $vector = is_array($embedding['embedding']) 
                ? $embedding['embedding'] 
                : $embedding['embedding']->getArrayCopy();
            echo "First 5 values: " . implode(', ', array_slice($vector, 0, 5)) . "\n";
        }
    } else {
        echo "✗ Embedding NOT FOUND\n";
    }
    
    echo "\n\nAll user embeddings:\n";
    echo "======================================\n";
    $allEmbeddings = $collection->find();
    $count = 0;
    foreach ($allEmbeddings as $emb) {
        $count++;
        echo "{$count}. User ID: {$emb['user_id']}, Version: {$emb['version']}\n";
    }
    
    if ($count === 0) {
        echo "No embeddings found in collection.\n";
    }
    
    echo "\nTotal embeddings: {$count}\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
