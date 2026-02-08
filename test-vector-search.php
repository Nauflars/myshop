<?php
require __DIR__ . '/vendor/autoload.php';

echo "Test 1: Testing vector search directly...\n";
try {
    $client = new MongoDB\Client('mongodb://root:rootpassword@mongodb:27017');
    $coll = $client->myshop->product_embeddings;
    
    echo "Connected to MongoDB\n";
    echo "Products count: " . $coll->countDocuments([]) . "\n\n";
    
    $testVector = array_fill(0, 1536, 0.1);
    
    $pipeline = [
        ['$vectorSearch' => [
            'index' => 'vector_index',
            'path' => 'embedding',
            'queryVector' => $testVector,
            'numCandidates' => 100,
            'limit' => 10,
        ]],
        ['$project' => [
            'product_id' => 1, 
            'score' => ['$meta' => 'vectorSearchScore']
        ]]
    ];
    
    echo "Executing vector search...\n";
    $results = $coll->aggregate($pipeline);
    
    $count = 0;
    foreach ($results as $r) {
        echo "Product: " . $r['product_id'] . " - Score: " . round($r['score'], 4) . "\n";
        $count++;
    }
    
    echo "\nTotal results: $count\n";
    echo "✅ Vector search works!\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
