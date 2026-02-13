<?php

require 'vendor/autoload.php';

try {
    $client = new \MongoDB\Client('mongodb://root:rootpassword@mongodb:27017');
    $database = $client->selectDatabase('myshop');
    $collection = $database->selectCollection('user_embeddings');
    
    $userId = 'c15e880a-c359-41a9-8fa9-ec844543433b';
    
    echo "Searching for user embedding: $userId\n\n";
    
    $embedding = $collection->findOne(['user_id' => $userId]);
    
    if ($embedding) {
        echo "✓ Embedding found!\n\n";
        echo "User ID: " . $embedding['user_id'] . "\n";
        echo "Vector dimensions: " . count($embedding['vector']) . "\n";
        echo "Version: " . $embedding['version'] . "\n";
        echo "Created at: " . $embedding['created_at']->toDateTime()->format('Y-m-d H:i:s') . "\n";
        echo "Updated at: " . $embedding['updated_at']->toDateTime()->format('Y-m-d H:i:s') . "\n";
        if (isset($embedding['last_updated_at'])) {
            echo "Last updated at: " . $embedding['last_updated_at']->toDateTime()->format('Y-m-d H:i:s') . "\n";
        }
        echo "\nFirst 10 vector values:\n";
        for ($i = 0; $i < 10; $i++) {
            echo sprintf("  [%d] = %.6f\n", $i, $embedding['vector'][$i]);
        }
        echo "\n✓ SUCCESS: User embedding was saved correctly to MongoDB!\n";
    } else {
        echo "✗ No embedding found for user ID: $userId\n";
        
        echo "\nAll embeddings in collection:\n";
        $cursor = $collection->find([], ['limit' => 10]);
        foreach ($cursor as $doc) {
            echo "- User ID: " . $doc['user_id'] . ", Version: " . $doc['version'] . "\n";
        }
    }
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
