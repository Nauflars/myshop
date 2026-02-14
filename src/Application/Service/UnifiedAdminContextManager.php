<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Infrastructure\Repository\UnifiedConversationStorage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Uid\Uuid;

/**
 * Unified Admin Context Manager (spec-012 compliant).
 *
 * Manages admin virtual assistant conversations with:
 * - Separate history/state/meta in Redis
 * - FIFO history management
 * - Multi-step operation context
 */
class UnifiedAdminContextManager
{
    private const ROLE = 'admin';

    public function __construct(
        private readonly UnifiedConversationStorage $storage,
        private readonly LoggerInterface $logger,
        private readonly int $ttl,
    ) {
    }

    /**
     * Get or create conversation for admin.
     */
    public function getOrCreateConversation(string $adminId, ?string $conversationId = null): array
    {
        // If conversationId provided, try to load it
        if (null !== $conversationId && $this->storage->exists(self::ROLE, $adminId, $conversationId)) {
            $state = $this->storage->getState(self::ROLE, $adminId, $conversationId);
            $history = $this->storage->getHistory(self::ROLE, $adminId, $conversationId);

            $this->logger->debug('Existing admin conversation loaded', [
                'adminId' => $adminId,
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
        $this->storage->initializeMeta(self::ROLE, $adminId, $conversationId, $this->ttl);

        // Initialize state
        $this->storage->setState(self::ROLE, $adminId, $conversationId, $defaultState, $this->ttl);

        $this->logger->info('New admin conversation created', [
            'adminId' => $adminId,
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
        string $adminId,
        string $conversationId,
        string $role,
        string $content,
    ): bool {
        $result = $this->storage->addMessageToHistory(
            self::ROLE,
            $adminId,
            $conversationId,
            $role,
            $content,
            $this->ttl
        );

        // Refresh TTL
        $this->storage->refreshTtl(self::ROLE, $adminId, $conversationId, $this->ttl);

        return $result;
    }

    /**
     * Get conversation history.
     */
    public function getHistory(string $adminId, string $conversationId): array
    {
        return $this->storage->getHistory(self::ROLE, $adminId, $conversationId);
    }

    /**
     * Get conversation state.
     */
    public function getState(string $adminId, string $conversationId): array
    {
        $state = $this->storage->getState(self::ROLE, $adminId, $conversationId);

        return $state ?? $this->getDefaultState();
    }

    /**
     * Update conversation state.
     */
    public function updateState(string $adminId, string $conversationId, array $state): bool
    {
        return $this->storage->setState(self::ROLE, $adminId, $conversationId, $state, $this->ttl);
    }

    /**
     * Update state after tool execution (admin-specific).
     */
    public function updateAfterToolExecution(
        string $adminId,
        string $conversationId,
        string $toolName,
        array $toolResult,
    ): bool {
        $state = $this->getState($adminId, $conversationId);

        // Update context based on admin tool
        if (str_contains($toolName, 'AdminCreate') || str_contains($toolName, 'AdminUpdate')) {
            // Product management context
            if (isset($toolResult['product_id'])) {
                $state['current_product'] = $toolResult['product_id'];
            }

            // Draft product for multi-step creation
            if (isset($toolResult['draft_product'])) {
                $state['draft_product'] = $toolResult['draft_product'];
            }
        } elseif (str_contains($toolName, 'Stock')) {
            // Inventory management context
            if (isset($toolResult['product_id'])) {
                $state['current_product'] = $toolResult['product_id'];
            }
        } elseif (str_contains($toolName, 'Sales') || str_contains($toolName, 'Stats')) {
            // Analytics context
            if (isset($toolResult['period'])) {
                $state['current_period'] = $toolResult['period'];
            }
        }

        // Update last tool and turn count
        $state['last_tool'] = $toolName;
        $state['turn_count'] = ($state['turn_count'] ?? 0) + 1;

        $this->logger->debug('Admin state updated after tool execution', [
            'adminId' => $adminId,
            'conversationId' => $conversationId,
            'tool' => $toolName,
        ]);

        return $this->updateState($adminId, $conversationId, $state);
    }

    /**
     * Build MessageBag-ready history with state context.
     */
    public function buildMessageBagContext(string $adminId, string $conversationId): array
    {
        $messages = [];

        // Add state as system message
        $state = $this->getState($adminId, $conversationId);
        $stateContext = $this->formatStateForPrompt($state);

        $messages[] = [
            'role' => 'system',
            'content' => $stateContext,
        ];

        // Add history
        $history = $this->getHistory($adminId, $conversationId);
        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg['role'],
                'content' => $msg['content'],
            ];
        }

        return $messages;
    }

    /**
     * Format admin state as prompt context.
     */
    private function formatStateForPrompt(array $state): string
    {
        $context = "**Current Admin Session State:**\n\n";

        $context .= '- Turn count: '.($state['turn_count'] ?? 0)."\n";

        if (!empty($state['last_tool'])) {
            $context .= '- Last action: '.$state['last_tool']."\n";
        }

        if (!empty($state['current_product'])) {
            $context .= '- Current product: '.$state['current_product']."\n";
        }

        if (!empty($state['draft_product'])) {
            $context .= "- Draft product in progress:\n";
            foreach ($state['draft_product'] as $key => $value) {
                $context .= "  - {$key}: {$value}\n";
            }
        }

        if (!empty($state['current_period'])) {
            $context .= '- Analysis period: '.$state['current_period']."\n";
        }

        if (!empty($state['pending_confirmation'])) {
            $context .= '- ⚠️ Pending confirmation: '.$state['pending_confirmation']."\n";
        }

        $context .= "\nUse this context for multi-step operations and follow-up questions.";

        return $context;
    }

    /**
     * Set pending confirmation for destructive actions.
     */
    public function setPendingConfirmation(
        string $adminId,
        string $conversationId,
        string $action,
        array $params,
    ): bool {
        $state = $this->getState($adminId, $conversationId);

        $state['pending_confirmation'] = [
            'action' => $action,
            'params' => $params,
            'timestamp' => time(),
        ];

        return $this->updateState($adminId, $conversationId, $state);
    }

    /**
     * Get and clear pending confirmation.
     */
    public function getPendingConfirmation(string $adminId, string $conversationId): ?array
    {
        $state = $this->getState($adminId, $conversationId);

        $pending = $state['pending_confirmation'] ?? null;

        if (null !== $pending) {
            // Clear it
            unset($state['pending_confirmation']);
            $this->updateState($adminId, $conversationId, $state);
        }

        return $pending;
    }

    /**
     * Delete conversation.
     */
    public function deleteConversation(string $adminId, string $conversationId): bool
    {
        $result = $this->storage->delete(self::ROLE, $adminId, $conversationId);

        if ($result) {
            $this->logger->info('Admin conversation deleted', [
                'adminId' => $adminId,
                'conversationId' => $conversationId,
            ]);
        }

        return $result;
    }

    /**
     * Refresh TTL.
     */
    public function refreshTtl(string $adminId, string $conversationId): bool
    {
        return $this->storage->refreshTtl(self::ROLE, $adminId, $conversationId, $this->ttl);
    }

    /**
     * Check if conversation exists.
     */
    public function exists(string $adminId, string $conversationId): bool
    {
        return $this->storage->exists(self::ROLE, $adminId, $conversationId);
    }

    /**
     * Get default state for new admin conversations.
     */
    private function getDefaultState(): array
    {
        return [
            'last_tool' => null,
            'turn_count' => 0,
            'current_product' => null,
            'draft_product' => null,
            'current_period' => null,
            'pending_confirmation' => null,
        ];
    }
}
