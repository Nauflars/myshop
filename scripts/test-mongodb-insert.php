<?php

require __DIR__.'/vendor/autoload.php';

try {
    $client = new MongoDB\Client('mongodb://root:rootpassword@mongodb:27017');
    $collection = $client->selectDatabase('myshop')->selectCollection('user_embeddings');

    $document = [
        'user_id' => 999,
        'embedding' => array_fill(0, 1536, 0.1),
        'dimension_count' => 1536,
        'last_updated' => new MongoDB\BSON\UTCDateTime(),
        'version' => 1,
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
    ];

    $result = $collection->insertOne($document);
    echo '✓ Inserted: '.$result->getInsertedCount()." document\n";
    echo '✓ Inserted ID: '.$result->getInsertedId()."\n";

    // Count documents
    $count = $collection->countDocuments([]);
    echo "✓ Total documents in user_embeddings: $count\n";
} catch (Throwable $e) {
    echo '✗ Error: '.$e->getMessage()."\n";
    echo '✗ Type: '.get_class($e)."\n";
    echo '✗ Trace: '.$e->getTraceAsString()."\n";
}
