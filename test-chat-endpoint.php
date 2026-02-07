<?php

require __DIR__ . '/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

$dotenv = new Dotenv();
$dotenv->loadEnv(__DIR__.'/.env.dev');

$kernel = new App\Kernel($_ENV['APP_ENV'] ?? 'dev', (bool) ($_ENV['APP_DEBUG'] ?? true));
$kernel->boot();

$container = $kernel->getContainer();

try {
    $contextManager = $container->get('App\Application\Service\CustomerContextManager');
    echo "CustomerContextManager loaded successfully\n";
    echo "Class: " . get_class($contextManager) . "\n";
    
    // Test creating context
    $context = $contextManager->getOrCreateContext('test-user-123');
    echo "Context created successfully\n";
    echo "User ID: " . $context->getUserId() . "\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
