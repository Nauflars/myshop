<?php

require __DIR__.'/../vendor/autoload.php';

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

echo "=== TEST: Direct RabbitMQ Message Publishing ===\n\n";

// Get the RabbitMQ publisher service
$publisher = $container->get('App\Infrastructure\Queue\RabbitMQPublisher');

// Create test message
$message = new UpdateUserEmbeddingMessage(
    userId: 'test-user-123',
    eventType: EventType::SEARCH,
    searchPhrase: 'test gaming laptop ' . time(),
    productId: null,
    occurredAt: new \DateTimeImmutable(),
    metadata: ['test' => true],
    messageId: hash('sha256', 'direct-test-' . microtime(true))
);

echo "📤 Publishing message to RabbitMQ...\n";
echo "  - Message ID: {$message->messageId}\n";
echo "  - User ID: {$message->userId}\n";
echo "  - Search: {$message->searchPhrase}\n\n";

try {
    $result = $publisher->publish($message);
    if ($result) {
        echo "✅ Message published successfully!\n\n";
    } else {
        echo "❌ Failed to publish message\n\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "❌ ERROR: {$e->getMessage()}\n\n";
    exit(1);
}

// Check RabbitMQ queue
echo "🐰 Checking RabbitMQ queue...\n";
$rabbitmqUser = $_ENV['RABBITMQ_USER'] ?? 'myshop_user';
$rabbitmqPass = $_ENV['RABBITMQ_PASSWORD'] ?? 'myshop_pass';

$ch = curl_init("http://localhost:15672/api/queues/%2F/user_embedding_updates");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$rabbitmqUser:$rabbitmqPass");
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $queueInfo = json_decode($response, true);
    echo "  ✓ Queue: user_embedding_updates\n";
    echo "  - Total messages: " . ($queueInfo['messages'] ?? 0) . "\n";
    echo "  - Ready: " . ($queueInfo['messages_ready'] ?? 0) . "\n";
    echo "  - Unacknowledged: " . ($queueInfo['messages_unacknowledged'] ?? 0) . "\n";
    echo "  - Consumers: " . ($queueInfo['consumers'] ?? 0) . "\n";
    
    if (($queueInfo['messages'] ?? 0) > 0 || ($queueInfo['messages_unacknowledged'] ?? 0) > 0) {
        echo "\n✅ SUCCESS: Message is in RabbitMQ queue!\n";
    } else {
        echo "\n⚠️  Message may have been processed already by worker\n";
    }
} else {
    echo "  ⚠️  Cannot access RabbitMQ Management API\n";
    echo "  Check manually at: http://localhost:15672\n";
}

echo "\n=== END TEST ===\n";
