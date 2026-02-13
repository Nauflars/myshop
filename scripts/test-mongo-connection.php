<?php

require 'vendor/autoload.php';

try {
    $client = new \MongoDB\Client('mongodb://root:rootpassword@mongodb:27017');
    
    echo "MongoDB connection successful\n";
    
    $dbs = $client->listDatabases();
    echo "Databases:\n";
    foreach ($dbs as $db) {
        echo "- " . $db['name'] . "\n";
    }
    
    // Test myshop database
    $database = $client->selectDatabase('myshop');
    echo "\nCollections in myshop database:\n";
    foreach ($database->listCollections() as $collection) {
        echo "- " . $collection->getName() . "\n";
    }
    
    // Try to insert a test document
    $collection = $database->selectCollection('user_embeddings');
    
    $testDoc = [
        'user_id' => 'test-connection-' . uniqid(),
        'vector' => array_fill(0, 1536, 0.1),
        'version' => 1,
        'created_at' => new \MongoDB\BSON\UTCDateTime(),
        'updated_at' => new \MongoDB\BSON\UTCDateTime(),
    ];
    
    $result = $collection->insertOne($testDoc);
    echo "\nTest insert successful: " . ($result->getInsertedCount() === 1 ? 'YES' : 'NO') . "\n";
    echo "Inserted ID: " . $result->getInsertedId() . "\n";
    
    // Clean up test document
    $collection->deleteOne(['_id' => $result->getInsertedId()]);
    echo "Test document cleaned up\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
