<?php

require __DIR__ . '/vendor/autoload.php';

use App\Infrastructure\Repository\UserEmbeddingRepository;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

// Create a simple test
$logger = new NullLogger();

$client = new MongoDB\Client('mongodb://root:rootpassword@mongodb:27017');
$repository = new UserEmbeddingRepository($client, $logger);

try {
    $embedding = $repository->findByUserId(1);
    
    if ($embedding === null) {
        echo "❌ No embedding found for user 1\n";
    } else {
        echo "✓ Embedding found for user 1\n";
        echo "  - User ID: {$embedding->userId}\n";
        echo "  - Vector length: " . count($embedding->vector) . "\n";
        echo "  - Version: {$embedding->version}\n";
        echo "  - Last updated: " . $embedding->lastUpdatedAt->format('Y-m-d H:i:s') . "\n";
    }
} catch (Throwable $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "❌ Type: " . get_class($e) . "\n";
    echo "❌ File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    
    // Show nested exceptions
    $previous = $e->getPrevious();
    if ($previous) {
        echo "\n❌ PREVIOUS Error: " . $previous->getMessage() . "\n";
        echo "❌ PREVIOUS Type: " . get_class($previous) . "\n";
        echo "❌ PREVIOUS File: " . $previous->getFile() . ":" . $previous->getLine() . "\n";
        echo "❌ PREVIOUS Trace:\n" . $previous->getTraceAsString() . "\n";
    }
}
