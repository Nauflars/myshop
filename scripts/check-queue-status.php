<?php
/**
 * Check RabbitMQ Queue Status
 * 
 * Queries RabbitMQ Management API to check queue depths and status.
 * Migrated from Doctrine Messenger to RabbitMQ.
 */

// Configuration
$rabbitmqHost = getenv('RABBITMQ_HOST') ?: 'localhost';
$rabbitmqPort = getenv('RABBITMQ_MGMT_PORT') ?: '15672';
$rabbitmqUser = getenv('RABBITMQ_USER') ?: 'myshop_user';
$rabbitmqPass = getenv('RABBITMQ_PASSWORD') ?: 'myshop_pass';
$rabbitmqVhost = getenv('RABBITMQ_VHOST') ?: '%2F'; // URL encoded '/'

$apiBaseUrl = "http://{$rabbitmqHost}:{$rabbitmqPort}/api";

/**
 * Query RabbitMQ Management API
 */
function queryRabbitMQ(string $endpoint, string $user, string $pass): ?array
{
    $ch = curl_init($endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$user:$pass");
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        echo "ERROR: Failed to query RabbitMQ API (HTTP $httpCode)\n";
        echo "Endpoint: $endpoint\n";
        return null;
    }
    
    return json_decode($response, true);
}

// Check RabbitMQ connection
echo "=== RabbitMQ Queue Status Check ===\n\n";

$overview = queryRabbitMQ("{$apiBaseUrl}/overview", $rabbitmqUser, $rabbitmqPass);
if (!$overview) {
    echo "ERROR: Cannot connect to RabbitMQ Management API at {$apiBaseUrl}\n";
    echo "Make sure RabbitMQ is running and management plugin is enabled.\n";
    exit(1);
}

echo "✓ RabbitMQ Version: {$overview['rabbitmq_version']}\n";
echo "✓ Management API: Connected\n\n";

// Get all queues
$queues = queryRabbitMQ("{$apiBaseUrl}/queues/{$rabbitmqVhost}", $rabbitmqUser, $rabbitmqPass);

if (empty($queues)) {
    echo "No queues found in vhost '/{$rabbitmqVhost}'\n";
    exit(0);
}

echo "=== Queue Statistics ===\n\n";

$totalMessages = 0;
$totalPending = 0;
$totalConsumers = 0;

foreach ($queues as $queue) {
    $name = $queue['name'];
    $messages = $queue['messages'] ?? 0;
    $messagesReady = $queue['messages_ready'] ?? 0;
    $messagesUnack = $queue['messages_unacknowledged'] ?? 0;
    $consumers = $queue['consumers'] ?? 0;
    $messageRate = $queue['messages_details']['rate'] ?? 0.0;
    
    $totalMessages += $messages;
    $totalPending += $messagesReady;
    $totalConsumers += $consumers;
    
    echo "Queue: {$name}\n";
    echo "  Total Messages: {$messages}\n";
    echo "  Ready (Pending): {$messagesReady}\n";
    echo "  Unacknowledged (Processing): {$messagesUnack}\n";
    echo "  Consumers: {$consumers}\n";
    echo "  Message Rate: " . number_format($messageRate, 2) . " msgs/s\n";
    
    if ($messagesReady > 100) {
        echo "  ⚠️  WARNING: High queue depth! Consider scaling workers.\n";
    }
    
    if ($consumers === 0 && $messages > 0) {
        echo "  ⚠️  WARNING: No consumers! Messages are not being processed.\n";
    }
    
    echo "\n";
}

echo "=== Summary ===\n";
echo "Total Messages: {$totalMessages}\n";
echo "Total Pending: {$totalPending}\n";
echo "Total Active Consumers: {$totalConsumers}\n";

// Check failed/DLQ queue specifically
$failedQueue = null;
foreach ($queues as $queue) {
    if ($queue['name'] === 'failed') {
        $failedQueue = $queue;
        break;
    }
}

if ($failedQueue) {
    $failedCount = $failedQueue['messages'] ?? 0;
    echo "\n=== Dead Letter Queue (DLQ) ===\n";
    echo "Failed Messages: {$failedCount}\n";
    
    if ($failedCount > 0) {
        echo "⚠️  WARNING: There are {$failedCount} failed messages in DLQ!\n";
        echo "Review and retry with: php bin/console messenger:failed:retry --force\n";
    } else {
        echo "✓ No failed messages\n";
    }
}

echo "\n=== RabbitMQ Management UI ===\n";
echo "Access at: http://{$rabbitmqHost}:{$rabbitmqPort}\n";
echo "Username: {$rabbitmqUser}\n";
echo "Password: {$rabbitmqPass}\n";

