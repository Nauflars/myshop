<?php

require __DIR__.'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();

$container = $kernel->getContainer();

echo "🔍 Testing Monolog Configuration...\n\n";

// Test AI Agent Logger
echo "1. Testing AI Agent Logger...\n";
try {
    $aiAgentLogger = $container->get('monolog.logger.ai_agent');
    $aiAgentLogger->info('Test log from ai_agent channel', [
        'test' => true,
        'timestamp' => date('Y-m-d H:i:s'),
        'message' => 'This is a test message for AI Agent logging'
    ]);
    echo "   ✅ AI Agent logger working!\n";
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test AI Tools Logger
echo "\n2. Testing AI Tools Logger...\n";
try {
    $aiToolsLogger = $container->get('monolog.logger.ai_tools');
    $aiToolsLogger->info('🔧 Test log from ai_tools channel', [
        'tool' => 'TestTool',
        'parameters' => ['param1' => 'value1'],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    echo "   ✅ AI Tools logger working!\n";
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test AI Context Logger
echo "\n3. Testing AI Context Logger...\n";
try {
    $aiContextLogger = $container->get('monolog.logger.ai_context');
    $aiContextLogger->info('Test log from ai_context channel', [
        'context' => 'test_context',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    echo "   ✅ AI Context logger working!\n";
} catch (\Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n✨ Monolog test completed!\n";
echo "📁 Check log files in: var/log/\n";
echo "   - var/log/ai_agent.log\n";
echo "   - var/log/ai_tools.log\n";
echo "   - var/log/ai_context.log\n";
