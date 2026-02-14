<?php

require __DIR__.'/../vendor/autoload.php';

use App\Entity\UserInteraction;
use App\Domain\ValueObject\EventType;
use Symfony\Component\Dotenv\Dotenv;

echo "==========================================================\n";
echo "TESTING USER EMBEDDING QUEUE FLOW\n";
echo "==========================================================\n\n";

// Load environment variables
(new Dotenv())->bootEnv(__DIR__.'/../.env');

// Boot Symfony kernel
$kernel = new App\Kernel($_ENV['APP_ENV'], (bool) $_ENV['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

// Get services
$entityManager = $container->get('doctrine')->getManager();
$userRepo = $entityManager->getRepository(App\Domain\Entity\User::class);
$mongoClient = $container->get('MongoDB\Client');

// Find or create test user
$testEmail = 'test.embeddings@example.com';
$user = $userRepo->findOneBy(['email' => $testEmail]);

if (!$user) {
    echo "❌ Test user not found. Creating one...\n";
    $email = new \App\Domain\ValueObject\Email($testEmail);
    $user = new App\Domain\Entity\User(
        name: 'Test Embeddings User',
        email: $email,
        passwordHash: password_hash('test123', PASSWORD_BCRYPT),
        role: 'ROLE_USER'
    );
    $entityManager->persist($user);
    $entityManager->flush();
    echo "✅ Created test user: {$user->getEmail()} (ID: {$user->getId()})\n";
} else {
    echo "✅ Using existing user: {$user->getEmail()} (ID: {$user->getId()})\n";
}

$userId = $user->getId();
echo "\n";

// Check MongoDB embedding BEFORE search
echo "📊 Checking MongoDB BEFORE search event...\n";
$mongoDb = $mongoClient->selectDatabase($_ENV['MONGODB_DB'] ?? 'myshop');
$userEmbeddingsCollection = $mongoDb->selectCollection('user_embeddings');

$embeddingBefore = $userEmbeddingsCollection->findOne(['user_id' => $userId]);
if ($embeddingBefore) {
    echo "  ✓ Existing embedding found\n";
    echo "  - Vector dimensions: " . count($embeddingBefore['embedding_vector'] ?? []) . "\n";
    echo "  - Last updated: " . ($embeddingBefore['last_updated']->toDateTime()->format('Y-m-d H:i:s')) . "\n";
} else {
    echo "  ⚠️  No embedding found yet (will be created)\n";
}
echo "\n";

// Create a search interaction
echo "🔍 Creating search interaction...\n";
$searchPhrase = 'gaming laptop high performance ' . time(); // unique query

$interaction = new UserInteraction(
    userId: (int) $userId, // Cast to int if needed
    eventType: 'search',
    occurredAt: new \DateTimeImmutable(),
    searchPhrase: $searchPhrase,
    productId: null
);

echo "  - User ID: {$userId}\n";
echo "  - Event Type: search\n";
echo "  - Search Phrase: {$searchPhrase}\n";
echo "  - Message ID: {$interaction->getMessageId()}\n";
echo "\n";

// Persist interaction (this should trigger the listener to publish to RabbitMQ)
echo "💾 Persisting interaction to database...\n";
$entityManager->persist($interaction);
$entityManager->flush();
echo "  ✅ Saved to MySQL\n";
echo "  ℹ️  PostPersist listener should have published to RabbitMQ\n";
echo "\n";

// Check RabbitMQ queue
echo "🐰 Checking RabbitMQ queue status...\n";
$rabbitmqHost = 'localhost';
$rabbitmqPort = 15672;
$rabbitmqUser = $_ENV['RABBITMQ_USER'] ?? 'myshop_user';
$rabbitmqPass = $_ENV['RABBITMQ_PASSWORD'] ?? 'myshop_pass';

$ch = curl_init("http://{$rabbitmqHost}:{$rabbitmqPort}/api/queues/%2F/user_embedding_updates");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERPWD, "$rabbitmqUser:$rabbitmqPass");
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $queueInfo = json_decode($response, true);
    echo "  ✓ Queue: user_embedding_updates\n";
    echo "  - Messages: " . ($queueInfo['messages'] ?? 0) . "\n";
    echo "  - Ready: " . ($queueInfo['messages_ready'] ?? 0) . "\n";
    echo "  - Unacknowledged: " . ($queueInfo['messages_unacknowledged'] ?? 0) . "\n";
    echo "  - Consumers: " . ($queueInfo['consumers'] ?? 0) . "\n";
    
    if (($queueInfo['consumers'] ?? 0) === 0) {
        echo "  ⚠️  WARNING: No consumers! Worker not running?\n";
    }
} else {
    echo "  ⚠️  Could not connect to RabbitMQ Management API\n";
}
echo "\n";

// Wait for worker to process
echo "⏳ Waiting 5 seconds for worker to process message...\n";
sleep(5);
echo "\n";

// Check MongoDB embedding AFTER processing
echo "📊 Checking MongoDB AFTER processing...\n";
$embeddingAfter = $userEmbeddingsCollection->findOne(['user_id' => $userId]);

if ($embeddingAfter) {
    echo "  ✅ Embedding found!\n";
    echo "  - Vector dimensions: " . count($embeddingAfter['embedding_vector'] ?? []) . "\n";
    echo "  - Last updated: " . ($embeddingAfter['last_updated']->toDateTime()->format('Y-m-d H:i:s')) . "\n";
    
    if ($embeddingBefore) {
        $timeBefore = $embeddingBefore['last_updated']->toDateTime()->getTimestamp();
        $timeAfter = $embeddingAfter['last_updated']->toDateTime()->getTimestamp();
        
        if ($timeAfter > $timeBefore) {
            echo "  ✅ SUCCESS: Embedding was UPDATED!\n";
            echo "  - Time difference: " . ($timeAfter - $timeBefore) . " seconds\n";
        } else {
            echo "  ⚠️  WARNING: Embedding timestamp NOT changed\n";
        }
    } else {
        echo "  ✅ SUCCESS: New embedding was CREATED!\n";
    }
    
    // Show recent search phrases
    if (isset($embeddingAfter['recent_searches'])) {
        echo "\n  📝 Recent searches:\n";
        foreach (array_slice($embeddingAfter['recent_searches'], 0, 5) as $search) {
            echo "    - {$search}\n";
        }
    }
} else {
    echo "  ❌ ERROR: No embedding found after processing\n";
    echo "  - Check worker logs: docker-compose logs worker\n";
}

echo "\n";
echo "==========================================================\n";
echo "TEST COMPLETED\n";
echo "==========================================================\n";
echo "\n";
echo "To debug:\n";
echo "  - Check worker logs: docker-compose logs worker --tail=50\n";
echo "  - Check RabbitMQ UI: http://localhost:15672\n";
echo "  - Check MongoDB: docker-compose exec mongodb mongosh myshop\n";
echo "    > db.user_embeddings.findOne({user_id: {$userId}})\n";
