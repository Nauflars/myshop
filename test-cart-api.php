<?php

require __DIR__.'/vendor/autoload.php';

use App\Infrastructure\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel('dev', true);
$request = Request::create('/api/cart', 'GET');

// Try to get a session or create one
$request->setSession(new \Symfony\Component\HttpFoundation\Session\Session(
    new \Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage()
));

$response = $kernel->handle($request);
$content = $response->getContent();

echo "Status: " . $response->getStatusCode() . "\n";
echo "Content:\n";
echo json_encode(json_decode($content), JSON_PRETTY_PRINT) . "\n";

$kernel->terminate($request, $response);
