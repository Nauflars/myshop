<?php
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();
try {
    $response = $client->request('POST', 'http://nginx/api/ai/chat', [
        'json' => ['message' => 'Hello'],
        'headers' => ['Accept' => 'application/json'],
        'timeout' => 30
    ]);
    
    $statusCode = $response->getStatusCode();
    $content = $response->getContent(false);
    
    echo "Status: $statusCode\n";
    
    // Try to parse as JSON first
    $json = json_decode($content, true);
    if ($json) {
        echo "JSON Response:\n";
        echo json_encode($json, JSON_PRETTY_PRINT) . "\n";
    } else {
        // If not JSON, show first 500 chars
        echo "HTML Response (first 500 chars):\n";
        echo substr($content, 0, 500) . "\n...";
    }
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
