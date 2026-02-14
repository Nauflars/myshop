<?php

require __DIR__.'/../vendor/autoload.php';

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== TEST: Message Dispatch Debug ===\n\n";

// Get the message bus via interface
$messageBus = $container->get('Symfony\Component\Messenger\MessageBusInterface');

// Create test message
$message = new UpdateUserEmbeddingMessage(
    userId: 'test-user-123',
    eventType: EventType::SEARCH,
    searchPhrase: 'test gaming laptop ' . time(),
    productId: null,
    occurredAt: new \DateTimeImmutable(),
    metadata: ['test' => true, 'source' => 'direct-bus-test'],
    messageId: hash('sha256', 'direct-bus-test-' . microtime(true))
);

echo "📤 Dispatching message with stamps...\n";
echo "  - Message ID: {$message->messageId}\n";
echo "  - User ID: {$message->userId}\n";
echo "  - Search: {$message->searchPhrase}\n\n";

try {
    // Dispatch WITH TransportNamesStamp to force routing
    $stamps = [
        new TransportNamesStamp(['user_embedding_updates']),
    ];
    
    $envelope = $messageBus->dispatch($message, $stamps);
    
    echo "✅ Message dispatched!\n\n";
    
    // Check stamps on returned envelope
    echo "📋 Envelope stamps:\n";
    foreach ($envelope->all() as $stampType => $instances) {
        $count = count($instances);
        echo "  - {$stampType}: {$count} instance(s)\n";
        
        // Show transport names stamp details
        if ($stampType === TransportNamesStamp::class) {
            foreach ($instances as $stamp) {
                echo "    → Transport names: " . implode(', ', $stamp->getTransportNames()) . "\n";
            }
        }
        
        // Check if ReceivedStamp exists (would mean it was handled locally!)
        if ($stampType === ReceivedStamp::class) {
            echo "    ⚠️  WARNING: ReceivedStamp present - message was handled locally!\n";
        }
    }
    
    echo "\n";
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n\n";
    exit(1);
}

echo "=== END TEST ===\n";
