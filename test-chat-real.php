<?php

// Simulate a real chat request to capture the actual error
$url = 'http://localhost:8080/api/chat';
$data = [
    'message' => 'Hola',
    'conversationId' => null
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Cookie: PHPSESSID=test123'  // Simulate session
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $httpCode\n";
echo "Response length: " . strlen($response) . " bytes\n\n";

if ($httpCode === 500) {
    // Parse the error from HTML
    if (preg_match('/<title>(.*?)<\/title>/', $response, $matches)) {
        echo "Error Title: " . $matches[1] . "\n\n";
    }
    
    if (preg_match('/<h2 class="block_exception.*?">(.*?)<\/h2>/s', $response, $matches)) {
        echo "Exception: " . strip_tags($matches[1]) . "\n\n";
    }
    
    if (preg_match('/<div class="trace message">\s*<pre>(.*?)<\/pre>/s', $response, $matches)) {
        echo "Message:\n" . html_entity_decode(strip_tags($matches[1])) . "\n\n";
    }
    
    // Show first 2000 chars of response for analysis
    echo "First 2000 chars of HTML response:\n";
    echo substr($response, 0, 2000) . "\n";
}
