<?php
// Direct agent test
require __DIR__.'/vendor/autoload.php';
require __DIR__.'/config/bootstrap.php';

use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

try {
    echo "Testing Symfony AI configuration...\n\n";
    
    // Check if OpenAI API key is set
    $apiKey = $_ENV['OPENAI_API_KEY'] ?? 'NOT_SET';
    echo "API Key configured: " . (strlen($apiKey) > 20 ? "Yes (" . substr($apiKey, 0, 10) . "...)" : "No") . "\n";
    
    // Try to get AI agent
    try {
        $agent = $container->get('ai.agent.openAiAgent');
        echo "✓ Agent service exists\n";
        echo "Agent class: " . get_class($agent) . "\n\n";
    } catch (\Exception $e) {
        echo "✗ Cannot get agent: " . $e->getMessage() . "\n";
        exit(1);
    }
    
    // Check tools
    echo "Checking tools...\n";
    $toolCount = 0;
    foreach (['GetProductsNameTool', 'GetProductsNameByMaxPriceTool', 'GetPriceByProductIdTool', 
              'GetProductImagesByProductIdTool', 'GetCartItemsTool', 'AddToCartTool',                 'RemoveFromCartTool', 'ProcessCheckoutTool'] as $toolClass) {
        $fullClass = 'App\\Infrastructure\\AI\\Tool\\' . $toolClass;
        try {
            $tool = $container->get($fullClass);
            echo "  ✓ $toolClass\n";
            $toolCount++;
        } catch (\Exception $e) {
            echo "  ✗ $toolClass: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nTotal tools available: $toolCount/8\n";
    
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}
