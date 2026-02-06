<?php
// Simpler test
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();
try {
    $response = $client->request('POST', 'http://nginx/api/ai/chat', [
        'json' => ['message' => 'Hello'],
        'timeout' => 30
    ]);
    
    $statusCode = $response->getStatusCode();
    $content = $response->getContent(false); // false = don't throw on error
    
    echo "Status: $statusCode\n";
    
    if ($statusCode >= 400) {
        echo "Full response:\n";
        echo $content . "\n";
    } else {
        echo "Success!\n";
        echo substr($content, 0, 500) . "\n";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
