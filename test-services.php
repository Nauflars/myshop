<?php

require __DIR__ . '/vendor/autoload.php';

use App\Kernel;

try {
    echo "Testing service instantiation...\n";
    $kernel = new Kernel('dev', true);
    $kernel->boot();
    $container = $kernel->getContainer();
    
    echo "1. Getting CustomerContextManager...\n";
    $manager = $container->get('App\Application\Service\CustomerContextManager');
    echo "   ✓ CustomerContextManager loaded\n";
    
    echo "2. Getting RoleAwareAssistant...\n";
    $assistant = $container->get('App\Infrastructure\AI\Service\RoleAwareAssistant');
    echo "   ✓ RoleAwareAssistant loaded\n";
    
    echo "3. Getting ConversationManager...\n";
    $convo = $container->get('App\Infrastructure\AI\Service\ConversationManager');
    echo "   ✓ ConversationManager loaded\n";
    
    echo "4. Getting UnansweredQuestionCapture...\n";
    $capture = $container->get('App\Application\Service\UnansweredQuestionCapture');
    echo "   ✓ HttpClient loaded\n";
    
    echo "\nAll services loaded successfully!\n";
    
} catch (\Throwable $e) {
    echo "\n✗ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ':' . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
