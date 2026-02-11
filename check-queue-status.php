<?php

require 'vendor/autoload.php';

$conn = \Doctrine\DBAL\DriverManager::getConnection(['url' => 'mysql://root:rootpassword@mysql:3306/myshop']);

$total = $conn->fetchOne('SELECT COUNT(*) FROM messenger_messages');
echo "Total messages in queue: $total\n";

$pending = $conn->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NULL');
echo "Pending messages (not delivered): $pending\n";

$delivered = $conn->fetchOne('SELECT COUNT(*) FROM messenger_messages WHERE delivered_at IS NOT NULL');
echo "Delivered messages: $delivered\n";

if ($total > 0) {
    echo "\nRecent messages:\n";
    $stmt = $conn->executeQuery('SELECT id, queue_name, delivered_at, created_at FROM messenger_messages ORDER BY created_at DESC LIMIT 5');
    while ($row = $stmt->fetchAssociative()) {
        echo sprintf("- ID: %d, Queue: %s, Created: %s, Delivered: %s\n", 
            $row['id'], 
            $row['queue_name'], 
            $row['created_at'],
            $row['delivered_at'] ?? 'PENDING'
        );
    }
}
