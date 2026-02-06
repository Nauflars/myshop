<?php
// Simple test for AI configuration
require __DIR__.'/vendor/autoload.php';
require __DIR__.'/config/bootstrap.php';

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

$kernel = new Kernel($_SERVER['APP_ENV'] ?? 'dev', (bool) ($_SERVER['APP_DEBUG'] ?? true));
$kernel->boot();
$container = $kernel->getContainer();

try {
    // Check if AI agent service exists
    $agent = $container->get('ai.agent.openAiAgent');
    echo "✓ AI Agent loaded successfully\n";
    echo "Agent class: " . get_class($agent) . "\n";
    
    // Check registered tools
    if (method_exists($agent, 'getTools')) {
        $tools = $agent->getTools();
        echo "\n✓ Tools count: " . count($tools) . "\n";
    }
    
} catch (\Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
