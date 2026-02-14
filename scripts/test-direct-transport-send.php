<?php

require __DIR__.'/../vendor/autoload.php';

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;
use Symfony\Component\Messenger\Transport\TransportInterface;

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== TEST: Direct AMQP Transport Send ===\n\n";

// Get the AMQP transport DIRECTLY
try {
    // Access transport service
    $transport = $container->get('messenger.transport.user_embedding_updates');
    
    echo "✅ Transport loaded: " . get_class($transport) . "\n\n";
    
    // Create test message
    $message = new UpdateUserEmbeddingMessage(
        userId: 'test-user-direct-transport',
        eventType: EventType::SEARCH,
        searchPhrase: 'direct transport test ' . time(),
        productId: null,
        occurredAt: new \DateTimeImmutable(),
        metadata: ['test' => true, 'method' => 'direct-transport'],
        messageId: hash('sha256', 'direct-transport-test-' . microtime(true))
    );
    
    // Create envelope with stamps
    $envelope = new \Symfony\Component\Messenger\Envelope($message, [
        new TransportNamesStamp(['user_embedding_updates']),
    ]);
    
    echo "📤 Sending message directly to transport...\n";
    echo "  - Message ID: {$message->messageId}\n";
    echo "  - User ID: {$message->userId}\n\n";
    
    // Send DIRECTLY to transport (bypass middleware)
    $sentEnvelope = $transport->send($envelope);
    
    echo "✅ Message sent to transport!\n\n";
    
    echo "📋 Sent envelope stamps:\n";
    foreach ($sentEnvelope->all() as $stampType => $instances) {
        echo "  - {$stampType}: " . count($instances) . " instance(s)\n";
    }
    
    echo "\n✅ TEST PASSED - message sent via transport\n";
    
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n";
    echo "Class: " . get_class($e) . "\n";
    echo "Stack trace:\n{$e->getTraceAsString()}\n";
    exit(1);
}

echo "\n=== END TEST ===\n";
