<?php
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();

echo "Testing /api/chat endpoint (chatbot widget)...\n\n";

try {
    $response = $client->request('POST', 'http://nginx/api/chat', [
        'json' => ['message' => 'Show me all products'],
        'headers' => ['Accept' => 'application/json'],
        'timeout' => 60
    ]);
    
    $statusCode = $response->getStatusCode();
    $content = $response->getContent(false);
    
    echo "Status: $statusCode\n";
    echo "Raw content: " . substr($content, 0, 500) . "\n\n";
    
    $json = json_decode($content, true);
    
    if ($statusCode === 200 && isset($json['response'])) {
        echo "✓ SUCCESS - AI Tools working!\n";
        echo "Response: " . substr($json['response'], 0, 200) . "...\n";
    } else {
        echo "✗ Error\n";
        echo json_encode($json, JSON_PRETTY_PRINT) . "\n";
    }
} catch (\Exception $e) {
    echo "✗ Exception: " . $e->getMessage() . "\n";
}
