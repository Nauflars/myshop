<?php

require_once __DIR__.'/../vendor/autoload.php';

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Domain\ValueObject\EventType;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/../.env');

$_SERVER += $_ENV;
$_SERVER['APP_ENV'] = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'dev';
$_SERVER['APP_DEBUG'] = $_SERVER['APP_DEBUG'] ?? $_ENV['APP_DEBUG'] ?? '1';

require __DIR__.'/../config/bootstrap.php';

$kernel = new \App\Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

// Get services
$rabbitMQPublisher = $container->get('App\Infrastructure\Queue\RabbitMQPublisher');
$entityManager = $container->get('doctrine.orm.entity_manager');

echo "==========================================================\n";
echo "TEST: Search + RabbitMQ Message Publishing\n";
echo "==========================================================\n\n";

// Find user
$userRepo = $entityManager->getRepository('App\Domain\Entity\User');
$user = $userRepo->findOneBy(['email' => 'nhaddouche@werfen.com']);

if (!$user) {
    echo "❌ User not found\n";
    exit(1);
}

echo "✅ User found: {$user->getEmail()} (ID: {$user->getId()})\n\n";

// Create and publish search message
$searchQuery = "laptop gaming professional";

echo "📤 Publishing search event message...\n";
echo "   User ID: {$user->getId()}\n";
echo "   Query: $searchQuery\n\n";

$message = UpdateUserEmbeddingMessage::fromDomainEvent(
    userId: $user->getId(),
    eventType: EventType::SEARCH,
    searchPhrase: $searchQuery,
    productId: null,
    occurredAt: new \DateTimeImmutable(),
    metadata: ['source' => 'test_script']
);

try {
    $published = $rabbitMQPublisher->publish($message);
    
    if ($published) {
        echo "✅ Message published successfully!\n";
        echo "   Message ID: {$message->messageId}\n\n";
    } else {
        echo "❌ Failed to publish message\n\n";
        exit(1);
    }
} catch (\Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n\n";
    exit(1);
}

// Wait a moment for processing
sleep(2);

// Check RabbitMQ queue status
echo "🔍 Checking RabbitMQ queue status...\n";
exec('docker-compose exec -T rabbitmq rabbitmqctl list_queues -p / name messages 2>&1', $output, $ret);

if ($ret === 0) {
    echo implode("\n", $output) . "\n\n";
} else {
    echo "❌ Could not check RabbitMQ status\n\n";
}

echo "==========================================================\n";
echo "✅ Test completed!\n";
echo "==========================================================\n";
