<?php

require 'vendor/autoload.php';

$client = new MongoDB\Client('mongodb://root:rootpassword@mongodb:27017');
$database = $client->selectDatabase('myshop');
$collection = $database->selectCollection('product_embeddings');

echo "=== PRODUCT EMBEDDINGS IN MONGODB ===\n\n";

$count = $collection->countDocuments([]);
echo "Total product embeddings: $count\n\n";

if ($count > 0) {
    $sample = $collection->findOne([]);

    echo "Sample document structure:\n";
    foreach ($sample as $key => $value) {
        if ('embedding' === $key) {
            echo "  $key: array with ".count($value)." dimensions\n";
        } else {
            echo "  $key: ".(is_object($value) ? get_class($value) : gettype($value))."\n";
        }
    }

    echo "\nFirst 3 products:\n";
    $cursor = $collection->find([], ['limit' => 3]);
    foreach ($cursor as $doc) {
        echo sprintf("- Product ID: %s\n", $doc['product_id'] ?? 'N/A');
        if (isset($doc['embedding'])) {
            echo sprintf("  Embedding dimensions: %d\n", count($doc['embedding']));
        }
        if (isset($doc['name'])) {
            echo sprintf("  Name: %s\n", $doc['name']);
        }
        echo "\n";
    }
} else {
    echo "⚠ NO PRODUCT EMBEDDINGS FOUND!\n";
    echo "This is why recommendations are not working.\n\n";
    echo "You need to generate product embeddings first.\n";
    echo "Run: php bin/console app:generate-product-embeddings\n";
}
