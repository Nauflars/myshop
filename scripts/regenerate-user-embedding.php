<?php

require_once __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$mongoUrl = $_ENV['MONGODB_URL'] ?? 'mongodb://root:rootpassword@mongodb:27017';
$dbName = $_ENV['MONGODB_DATABASE'] ?? 'myshop';

$userId = 2004716280;

echo "Regenerating User Embedding\n";
echo "===========================\n\n";
echo "User ID: {$userId}\n";
echo "Email: naoufal.lars@gmail.com\n\n";

try {
    $client = new MongoDB\Client($mongoUrl);
    $database = $client->selectDatabase($dbName);
    $collection = $database->selectCollection('user_embeddings');

    // Delete existing embedding
    $result = $collection->deleteOne(['user_id' => $userId]);

    if ($result->getDeletedCount() > 0) {
        echo "✓ Deleted old embedding (with dummy vectors)\n";
    } else {
        echo "⚠ No existing embedding found\n";
    }

    echo "\nNext steps:\n";
    echo "----------\n";
    echo "1. Make a search as this user: naoufal.lars@gmail.com\n";
    echo "2. Worker will generate NEW embedding using OpenAI (real vectors)\n";
    echo "3. Visit home page - recommendations should work!\n\n";
    echo "Example search:\n";
    echo '  curl -X GET "http://localhost:8080/api/products/search?q=laptop" -H "Cookie: PHPSESSID=..."'."\n\n";
} catch (Exception $e) {
    echo 'ERROR: '.$e->getMessage()."\n";
}
