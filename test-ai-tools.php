<?php
require __DIR__.'/vendor/autoload.php';

use Symfony\Component\HttpClient\HttpClient;

$client = HttpClient::create();

echo "Testing AI Tools...\n\n";

$questions = [
    "Show me all products",
    "What products cost less than 50 euros?",
    "What's the price of product ID 1?",
];

foreach ($questions as $i => $question) {
    echo "Question " . ($i + 1) . ": $question\n";
    
    try {
        $response = $client->request('POST', 'http://nginx/api/ai/chat', [
            'json' => ['message' => $question],
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 60
        ]);
        
        $statusCode = $response->getStatusCode();
        $json = json_decode($response->getContent(false), true);
        
        if ($statusCode === 200 && isset($json['response'])) {
            echo "✓ Response: " . substr($json['response'], 0, 150) . "...\n\n";
        } else {
            echo "✗ Error: Status $statusCode\n\n";
        }
    } catch (\Exception $e) {
        echo "✗ Exception: " . $e->getMessage() . "\n\n";
    }
}
