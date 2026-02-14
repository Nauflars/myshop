<?php

declare(strict_types=1);

namespace App\Application\Port;

use App\Application\Message\UpdateUserEmbeddingMessage;

/**
 * MessagePublisherInterface - Port for publishing messages to message broker.
 *
 * Following Hexagonal Architecture (Ports & Adapters):
 * - This is a PORT (interface) in the Application layer
 * - Implementations (like RabbitMQPublisher) are ADAPTERS in Infrastructure layer
 *
 * This allows:
 * - Dependency Inversion Principle (SOLID): Application depends on abstraction
 * - Easy testing: Mock this interface instead of concrete implementations
 * - Flexibility: Swap message broker implementations without changing business logic
 */
interface MessagePublisherInterface
{
    /**
     * Publish a message to the message broker.
     *
     * @param UpdateUserEmbeddingMessage $message Message to publish
     * @param int                        $delayMs Optional delay in milliseconds (default: 0)
     *
     * @return bool True if published successfully, false otherwise
     */
    public function publish(UpdateUserEmbeddingMessage $message, int $delayMs = 0): bool;

    /**
     * Publish order created event to queue.
     *
     * @param array<string, mixed> $orderData Order data to publish
     *
     * @return bool True if published successfully, false otherwise
     */
    public function publishOrderCreated(array $orderData): bool;
}
