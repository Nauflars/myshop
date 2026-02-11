<?php

declare(strict_types=1);

namespace App\Infrastructure\MessageHandler;

use App\Application\Message\UpdateUserEmbeddingMessage;
use App\Application\UseCase\CalculateUserEmbedding;
use App\Domain\Repository\EmbeddingServiceInterface;
use App\Domain\Repository\ProductEmbeddingRepositoryInterface;
use App\Domain\Repository\UserEmbeddingRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

/**
 * UpdateUserEmbeddingHandler - RabbitMQ message consumer with idempotency and fault tolerance
 * 
 * Implements spec-014 US4: Fault-tolerant message processing
 * - Idempotency check using message_id
 * - Comprehensive error handling
 * - Structured logging with context
 * 
 * Note: Registered ONLY to command.bus with from_transport='user_embedding_updates' in services/queue.yaml
 * This ensures the handler ONLY processes messages from RabbitMQ, not during message dispatch
 * The AsMessageHandler attribute has been removed to prevent auto-registration to all buses
 */
final class UpdateUserEmbeddingHandler
{
    // Track processed message IDs in-memory (per worker instance)
    private static array $processedMessages = [];
    private const MAX_CACHE_SIZE = 10000;

    public function __construct(
        private readonly CalculateUserEmbedding $calculateUseCase,
        private readonly UserEmbeddingRepositoryInterface $embeddingRepository,
        private readonly ProductEmbeddingRepositoryInterface $productEmbeddingRepository,
        private readonly EmbeddingServiceInterface $embeddingService,
        private readonly LoggerInterface $logger
    ) {}

    /**
     * Handle UpdateUserEmbeddingMessage from RabbitMQ queue
     * 
     * @param UpdateUserEmbeddingMessage $message Message from queue
     * @throws UnrecoverableMessageHandlingException For irrecoverable errors
     * @throws \Exception For retryable errors
     */
    public function __invoke(UpdateUserEmbeddingMessage $message): void
    {
        $startTime = microtime(true);

        $this->logger->info('Processing user embedding update message', [
            'message_id' => $message->messageId,
            'user_id' => $message->userId,
            'event_type' => $message->eventType->value,
            'occurred_at' => $message->occurredAt->format('c'),
            'metadata' => $message->metadata,
        ]);

        try {
            // 1. Idempotency check: Skip already processed messages
            if ($this->isAlreadyProcessed($message->messageId)) {
                $this->logger->info('Message already processed (idempotency check), skipping', [
                    'message_id' => $message->messageId,
                    'user_id' => $message->userId,
                ]);
                return;
            }

            // 2. Check for timestamp-based idempotency
            // If user embedding exists and was updated after this event, skip
            $existingEmbedding = $this->embeddingRepository->findByUserId($message->userId);
            if ($existingEmbedding !== null && 
                $existingEmbedding->lastUpdatedAt > $message->occurredAt) {
                
                $this->logger->info('User embedding already up-to-date (newer than event), skipping', [
                    'message_id' => $message->messageId,
                    'user_id' => $message->userId,
                    'event_occurred_at' => $message->occurredAt->format('c'),
                    'embedding_updated_at' => $existingEmbedding->lastUpdatedAt->format('c'),
                ]);
                
                $this->markAsProcessed($message->messageId);
                return;
            }

            // 3. Get event embedding based on event type
            $eventEmbedding = $this->retrieveEventEmbedding($message);

            // 4. Calculate and save user embedding
            $userEmbedding = $this->calculateUseCase->execute(
                userId: $message->userId,
                eventType: $message->eventType,
                eventEmbedding: $eventEmbedding,
                occurredAt: $message->occurredAt
            );

            // 5. Mark as processed (idempotency cache)
            $this->markAsProcessed($message->messageId);

            $duration = (microtime(true) - $startTime) * 1000; // milliseconds

            $this->logger->info('Successfully updated user embedding', [
                'message_id' => $message->messageId,
                'user_id' => $message->userId,
                'event_type' => $message->eventType->value,
                'version' => $userEmbedding->version,
                'last_updated_at' => $userEmbedding->lastUpdatedAt->format('c'),
                'processing_time_ms' => round($duration, 2),
            ]);

        } catch (\InvalidArgumentException $e) {
            // Irrecoverable error: invalid message data
            $this->logger->error('Invalid message data (irrecoverable)', [
                'message_id' => $message->messageId,
                'user_id' => $message->userId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            throw new UnrecoverableMessageHandlingException(
                'Invalid message data: ' . $e->getMessage(),
                0,
                $e
            );

        } catch (\MongoDB\Driver\Exception\ConnectionException $e) {
            // Retryable error: MongoDB connection failed
            $this->logger->warning('MongoDB connection failed (retryable)', [
                'message_id' => $message->messageId,
                'user_id' => $message->userId,
                'error' => $e->getMessage(),
                'retry_hint' => 'Message will be retried automatically',
            ]);

            throw $e; // Re-throw for retry

        } catch (\RuntimeException $e) {
            // Potentially retryable error: Repository save failed (optimistic locking)
            if (str_contains($e->getMessage(), 'optimistic locking')) {
                $this->logger->warning('Optimistic locking conflict (retryable)', [
                    'message_id' => $message->messageId,
                    'user_id' => $message->userId,
                    'error' => $e->getMessage(),
                ]);

                throw $e; // Re-throw for retry
            }

            // Other runtime errors
            $this->logger->error('Runtime error during processing', [
                'message_id' => $message->messageId,
                'user_id' => $message->userId,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
            ]);

            throw $e;

        } catch (\Throwable $e) {
            // Generic error handling
            $this->logger->error('Failed to process user embedding update', [
                'message_id' => $message->messageId,
                'user_id' => $message->userId,
                'event_type' => $message->eventType->value,
                'error' => $e->getMessage(),
                'exception' => get_class($e),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger Symfony Messenger retry logic
            throw $e;
        }
    }

    /**
     * Check if message has already been processed (idempotency)
     */
    private function isAlreadyProcessed(string $messageId): bool
    {
        return isset(self::$processedMessages[$messageId]);
    }

    /**
     * Mark message as processed (idempotency cache)
     */
    private function markAsProcessed(string $messageId): void
    {
        // Prevent memory leaks: clear cache if too large
        if (count(self::$processedMessages) >= self::MAX_CACHE_SIZE) {
            self::$processedMessages = array_slice(
                self::$processedMessages,
                self::MAX_CACHE_SIZE / 2,
                preserve_keys: true
            );
        }

        self::$processedMessages[$messageId] = time();
    }

    /**
     * Retrieve event embedding based on event type
     * 
     * For product events: fetch from MongoDB product_embeddings collection
     * For search events: TODO - call AI service to generate from search phrase
     * 
     * @param UpdateUserEmbeddingMessage $message Message containing event data
     * @return array<float> 1536-dimensional embedding vector
     */
    private function retrieveEventEmbedding(UpdateUserEmbeddingMessage $message): array
    {
        // Product events: retrieve from product_embeddings collection
        if ($message->eventType->requiresProduct() && $message->productId !== null) {
            $productEmbedding = $this->productEmbeddingRepository->findEmbeddingByProductId($message->productId);

            if ($productEmbedding !== null) {
                $this->logger->info('Retrieved product embedding from MongoDB', [
                    'product_id' => $message->productId,
                    'event_type' => $message->eventType->value,
                    'dimensions' => count($productEmbedding),
                ]);

                return $productEmbedding;
            }

            // Product embedding not found - log warning and use dummy
            $this->logger->warning('Product embedding not found, using fallback', [
                'product_id' => $message->productId,
                'event_type' => $message->eventType->value,
            ]);

            return $this->getDummyEmbedding();
        }

        // Search events: Generate embedding from search phrase using OpenAI
        if ($message->eventType->requiresSearchPhrase() && $message->searchPhrase !== null) {
            try {
                $searchEmbedding = $this->embeddingService->generateEmbedding($message->searchPhrase);
                
                $this->logger->info('Generated embedding from search phrase', [
                    'search_phrase' => $message->searchPhrase,
                    'event_type' => $message->eventType->value,
                    'dimensions' => count($searchEmbedding),
                ]);
                
                return $searchEmbedding;
                
            } catch (\Exception $e) {
                $this->logger->error('Failed to generate search phrase embedding', [
                    'search_phrase' => $message->searchPhrase,
                    'error' => $e->getMessage(),
                ]);
                
                // Fallback to dummy if OpenAI fails
                return $this->getDummyEmbedding();
            }
        }

        // Fallback for unknown event types
        $this->logger->warning('Unknown event type, using dummy embedding', [
            'event_type' => $message->eventType->value,
        ]);

        return $this->getDummyEmbedding();
    }

    /**
     * Get dummy embedding for testing
     * Used as fallback when product embedding not found or AI service not integrated
     * 
     * @return array<float> 1536-dimensional normalized vector
     */
    private function getDummyEmbedding(): array
    {
        // Generate normalized random vector for testing
        $embedding = [];
        $sumSquares = 0.0;

        for ($i = 0; $i < 1536; $i++) {
            $value = (mt_rand() / mt_getrandmax()) * 2 - 1; // Random -1 to 1
            $embedding[] = $value;
            $sumSquares += $value * $value;
        }

        // Normalize to unit vector
        $magnitude = sqrt($sumSquares);
        if ($magnitude > 0) {
            $embedding = array_map(fn($v) => $v / $magnitude, $embedding);
        }

        return $embedding;
    }
}
