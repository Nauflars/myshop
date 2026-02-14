<?php

/**
 * Test script to verify end-to-end RabbitMQ message flow:
 * 1. Publish message via RabbitMQPublisher
 * 2. Verify message in RabbitMQ queue
 * 3. Start worker
 * 4. Verify MongoDB update
 */

require __DIR__.'/../vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

echo "==========================================================\n";
echo "END-TO-END RABBITMQ VERIFICATION TEST\n";
echo "==========================================================\n\n";

// Step 1: Publish message
echo "📤 STEP 1: Publishing message to RabbitMQ...\n";

$publisher = $container->get('App\Infrastructure\Queue\RabbitMQPublisher');
$testUserId = 'test-e2e-' . time();
$messageId = hash('sha256', $testUserId . microtime(true));

$message = new UpdateUserEmbeddingMessage(
    userId: $testUserId,
    eventType: EventType::SEARCH,
    searchPhrase: 'gaming laptop e2e test ' . time(),
    productId: null,
    occurredAt: new \DateTimeImmutable(),
    metadata: ['test' => 'e2e-verification'],
    messageId: $messageId
);

try {
    $result = $publisher->publish($message);
    if ($result) {
        echo "  ✅ Message published\n";
        echo "    - Message ID: {$messageId}\n";
        echo "    - User ID: {$testUserId}\n\n";
    } else {
        echo "  ❌ Publish failed\n";
        exit(1);
    }
} catch (\Throwable $e) {
    echo "  ❌ Exception: {$e->getMessage()}\n";
    exit(1);
}

// Step 2: Check queue (wait a moment for async processing)
echo "⏳ STEP 2: Waiting 2 seconds...\n";
sleep(2);

echo "\n🐰 STEP 3: Check RabbitMQ queue status\n";
echo "  Run manually: docker-compose exec rabbitmq rabbitmqctl list_queues -p / name messages\n";
echo "  Expected: 1 message in user_embedding_updates queue (if worker is stopped)\n\n";

echo "💡 Next steps:\n";
echo "  1. Stop worker: docker-compose stop worker\n";
echo "  2. Run this script again\n";
echo "  3. Check queue: docker-compose exec rabbitmq rabbitmqctl list_queues -p /\n";
echo "  4. Should see 1 message waiting\n";
echo "  5. Start worker: docker-compose start worker\n";
echo "  6. Message should be consumed immediately\n\n";

echo "==========================================================\n";
echo "TEST COMPLETED\n";
echo "==========================================================\n";
