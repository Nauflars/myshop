<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\ValueObject\CustomerConversationContext;
use App\Infrastructure\Repository\UnifiedConversationStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Manages customer conversation context (spec-012 compliant).
 *
 * Refactored to use UnifiedConversationStorage with:
 * - Separate history/state/meta in Redis
 * - FIFO history management (max 10 messages)
 * - Consistent architecture with AdminContextManager
 */
class UnifiedCustomerContextManager
{
    private const ROLE = 'client';

    public function __construct(
        private readonly UnifiedConversationStorage $storage,
        private readonly LoggerInterface $logger,
        private readonly int $ttl,
    ) {
    }

    /**
     * Get or create conversation for customer.
     *
     * Returns conversationId (UUID) and loads state from Redis
     */
    public function getOrCreateConversation(string $userId, ?string $conversationId = null): array
    {
        // If conversationId provided, try to load it
        if (null !== $conversationId && $this->storage->exists(self::ROLE, $userId, $conversationId)) {
            $state = $this->storage->getState(self::ROLE, $userId, $conversationId);
            $history = $this->storage->getHistory(self::ROLE, $userId, $conversationId);

            $this->logger->debug('Existing conversation loaded', [
                'userId' => $userId,
                'conversationId' => $conversationId,
                'historyCount' => count($history),
            ]);

            return [
                'conversationId' => $conversationId,
                'state' => $state ?? $this->getDefaultState(),
                'history' => $history,
            ];
        }

        // Create new conversation
        $conversationId = Uuid::v4()->toRfc4122();
        $defaultState = $this->getDefaultState();

        // Initialize metadata
        $this->storage->initializeMeta(self::ROLE, $userId, $conversationId, $this->ttl);

        // Initialize state
        $this->storage->setState(self::ROLE, $userId, $conversationId, $defaultState, $this->ttl);

        $this->logger->info('New conversation created', [
            'userId' => $userId,
            'conversationId' => $conversationId,
        ]);

        return [
            'conversationId' => $conversationId,
            'state' => $defaultState,
            'history' => [],
        ];
    }

    /**
     * Add message to conversation history.
     */
    public function addMessage(
        string $userId,
        string $conversationId,
        string $role,
        string $content,
    ): bool {
        $result = $this->storage->addMessageToHistory(
            self::ROLE,
            $userId,
            $conversationId,
            $role,
            $content,
            $this->ttl
        );

        // Refresh TTL
        $this->storage->refreshTtl(self::ROLE, $userId, $conversationId, $this->ttl);

        return $result;
    }

    /**
     * Get conversation history (últimos 10 mensajes).
     */
    public function getHistory(string $userId, string $conversationId): array
    {
        return $this->storage->getHistory(self::ROLE, $userId, $conversationId);
    }

    /**
     * Get conversation state.
     */
    public function getState(string $userId, string $conversationId): array
    {
        $state = $this->storage->getState(self::ROLE, $userId, $conversationId);

        return $state ?? $this->getDefaultState();
    }

    /**
     * Update conversation state.
     */
    public function updateState(string $userId, string $conversationId, array $state): bool
    {
        return $this->storage->setState(self::ROLE, $userId, $conversationId, $state, $this->ttl);
    }

    /**
     * Update state after tool execution.
     */
    public function updateAfterToolExecution(
        string $userId,
        string $conversationId,
        string $toolName,
        array $toolResult,
    ): bool {
        $state = $this->getState($userId, $conversationId);

        // Update flow based on tool
        if (str_contains($toolName, 'Product') || str_contains($toolName, 'Search')) {
            $state['flow'] = 'product_browsing';

            // Extract product IDs from result if available
            if (isset($toolResult['products']) && is_array($toolResult['products'])) {
                $productIds = array_column($toolResult['products'], 'id');
                if (!empty($productIds)) {
                    $state['selected_products'] = array_slice($productIds, 0, 5); // Max 5
                }
            }
        } elseif (str_contains($toolName, 'Cart')) {
            $state['flow'] = 'cart_management';

            // Update cart snapshot if available
            if (isset($toolResult['items']) && is_array($toolResult['items'])) {
                $state['cart_items'] = array_map(function ($item) {
                    return [
                        'product_id' => $item['product_id'] ?? $item['id'] ?? null,
                        'quantity' => $item['quantity'] ?? 1,
                    ];
                }, $toolResult['items']);
            }
        } elseif (str_contains($toolName, 'Order')) {
            $state['flow'] = 'order_tracking';
        } elseif (str_contains($toolName, 'Checkout') || str_contains($toolName, 'Collect')) {
            $state['flow'] = 'checkout';

            if (isset($toolResult['step'])) {
                $state['checkout_step'] = $toolResult['step'];
            }
        }

        // Update last tool and turn count
        $state['last_tool'] = $toolName;
        $state['turn_count'] = ($state['turn_count'] ?? 0) + 1;

        $this->logger->debug('State updated after tool execution', [
            'userId' => $userId,
            'conversationId' => $conversationId,
            'tool' => $toolName,
            'flow' => $state['flow'],
        ]);

        return $this->updateState($userId, $conversationId, $state);
    }

    /**
     * Build MessageBag-ready history with state context.
     *
     * Returns array suitable for Symfony AI Agent MessageBag construction:
     * [
     *   ['role' => 'system', 'content' => 'State context...'],
     *   ['role' => 'user', 'content' => '...'],
     *   ['role' => 'assistant', 'content' => '...'],
     *   ...
     * ]
     */
    public function buildMessageBagContext(string $userId, string $conversationId): array
    {
        $messages = [];

        // Add state as system message
        $state = $this->getState($userId, $conversationId);
        $stateContext = $this->formatStateForPrompt($state);

        $messages[] = [
            'role' => 'system',
            'content' => $stateContext,
        ];

        // Add history
        $history = $this->getHistory($userId, $conversationId);
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        return $messages;
    }

    /**
     * Format state as prompt context.
     */
    private function formatStateForPrompt(array $state): string
    {
        $context = "**Current Conversation State:**\n\n";

        $context .= '- Flow: '.($state['flow'] ?? 'browsing')."\n";
        $context .= '- Turn count: '.($state['turn_count'] ?? 0)."\n";

        if (!empty($state['last_tool'])) {
            $context .= '- Last action: '.$state['last_tool']."\n";
        }

        if (!empty($state['selected_products'])) {
            $productList = implode(', ', $state['selected_products']);
            $context .= '- Recently viewed products: '.$productList."\n";
        }

        if (!empty($state['cart_items'])) {
            $itemCount = array_sum(array_column($state['cart_items'], 'quantity'));
            $context .= '- Cart: '.$itemCount." items\n";
        }

        if (!empty($state['checkout_step'])) {
            $context .= '- Checkout step: '.$state['checkout_step']."\n";
        }

        $context .= "\nUse this context to provide natural follow-up responses.";

        return $context;
    }

    /**
     * Delete conversation.
     */
    public function deleteConversation(string $userId, string $conversationId): bool
    {
        $result = $this->storage->delete(self::ROLE, $userId, $conversationId);

        if ($result) {
            $this->logger->info('Conversation deleted', [
                'userId' => $userId,
                'conversationId' => $conversationId,
            ]);
        }

        return $result;
    }

    /**
     * Refresh TTL.
     */
    public function refreshTtl(string $userId, string $conversationId): bool
    {
        return $this->storage->refreshTtl(self::ROLE, $userId, $conversationId, $this->ttl);
    }

    /**
     * Check if conversation exists.
     */
    public function exists(string $userId, string $conversationId): bool
    {
        return $this->storage->exists(self::ROLE, $userId, $conversationId);
    }

    /**
     * Get default state for new conversations.
     */
    private function getDefaultState(): array
    {
        return [
            'flow' => 'browsing',
            'last_tool' => null,
            'turn_count' => 0,
            'selected_products' => [],
            'cart_items' => [],
            'checkout_step' => null,
            'language' => 'en',
        ];
    }

    // ========== LEGACY COMPATIBILITY METHODS ==========
    // These methods maintain compatibility with old CustomerContextManager API

    /**
     * Load context (legacy compatibility).
     */
    public function loadContext(string $userId): ?CustomerConversationContext
    {
        // For backwards compatibility, try to find any active conversation
        // In spec-012, we work with conversationId directly
        $this->logger->warning('Deprecated loadContext() called - use getOrCreateConversation() instead', [
            'userId' => $userId,
        ]);

        return null;
    }

    /**
     * Save context (legacy compatibility).
     */
    public function saveContext(CustomerConversationContext $context): void
    {
        $this->logger->warning('Deprecated saveContext() called - use updateState() instead', [
            'userId' => $context->getUserId(),
        ]);
    }

    /**
     * Get or create context (legacy compatibility).
     */
    public function getOrCreateContext(string $userId, string $language = 'en'): CustomerConversationContext
    {
        $this->logger->warning('Deprecated getOrCreateContext() called - use getOrCreateConversation() instead', [
            'userId' => $userId,
        ]);

        return CustomerConversationContext::createFresh($userId, $language);
    }

    /**
     * Delete context (legacy compatibility).
     */
    public function deleteContext(string $userId): bool
    {
        $this->logger->warning('Deprecated deleteContext() called - use deleteConversation() instead', [
            'userId' => $userId,
        ]);

        return false;
    }
}
