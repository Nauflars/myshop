<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Repository\ContextStorageInterface;
use App\Domain\ValueObject\AdminConversationContext;
use Psr\Log\LoggerInterface;

/**
 * Manages admin conversation context.
 *
 * Handles loading, saving, and enriching AI prompts with admin context
 * to enable multi-step operations with context retention.
 */
class AdminContextManager
{
    private const KEY_PREFIX = 'chat:admin:';

    public function __construct(
        private readonly ContextStorageInterface $contextStorage,
        private readonly LoggerInterface $logger,
        private readonly int $ttl,
    ) {
    }

    /**
     * Load context for an admin.
     *
     * @param string $adminId Admin user ID
     *
     * @return AdminConversationContext|null Context if found and not expired
     */
    public function loadContext(string $adminId): ?AdminConversationContext
    {
        $key = self::KEY_PREFIX.$adminId;

        try {
            $context = $this->contextStorage->get($key);

            if (null === $context) {
                $this->logger->info('No existing context found for admin', ['adminId' => $adminId]);

                return null;
            }

            if (!$context instanceof AdminConversationContext) {
                $this->logger->error('Invalid context type retrieved', [
                    'adminId' => $adminId,
                    'expected' => AdminConversationContext::class,
                    'actual' => get_class($context),
                ]);

                return null;
            }

            $this->logger->debug('Admin context loaded', [
                'adminId' => $adminId,
                'flow' => $context->getFlow(),
                'turnCount' => $context->getTurnCount(),
            ]);

            return $context;
        } catch (\Exception $e) {
            $this->logger->error('Failed to load admin context', [
                'adminId' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Save context for an admin.
     *
     * @param AdminConversationContext $context    Context to save
     * @param bool                     $refreshTtl Whether to refresh TTL for existing context
     */
    public function saveContext(AdminConversationContext $context, bool $refreshTtl = true): void
    {
        $key = self::KEY_PREFIX.$context->getAdminId();

        try {
            $this->contextStorage->set($key, $context, $this->ttl);

            $this->logger->debug('Admin context saved', [
                'adminId' => $context->getAdminId(),
                'flow' => $context->getFlow(),
                'turnCount' => $context->getTurnCount(),
                'ttl' => $this->ttl,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save admin context', [
                'adminId' => $context->getAdminId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get or create context for an admin.
     *
     * @param string $adminId Admin user ID
     *
     * @return AdminConversationContext Existing or freshly created context
     */
    public function getOrCreateContext(string $adminId): AdminConversationContext
    {
        $context = $this->loadContext($adminId);

        if (null === $context) {
            $this->logger->info('Creating fresh context for admin', ['adminId' => $adminId]);
            $context = AdminConversationContext::createFresh($adminId);
        }

        return $context;
    }

    /**
     * Delete context for an admin (manual reset).
     *
     * @param string $adminId Admin user ID
     *
     * @return bool True if deleted, false if not found
     */
    public function deleteContext(string $adminId): bool
    {
        $key = self::KEY_PREFIX.$adminId;

        try {
            $deleted = $this->contextStorage->delete($key);

            if ($deleted) {
                $this->logger->info('Admin context deleted', ['adminId' => $adminId]);
            } else {
                $this->logger->debug('No context to delete for admin', ['adminId' => $adminId]);
            }

            return $deleted;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete admin context', [
                'adminId' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Refresh TTL for existing context.
     *
     * @param string $adminId Admin user ID
     *
     * @return bool True if refreshed, false if not found
     */
    public function refreshTtl(string $adminId): bool
    {
        $key = self::KEY_PREFIX.$adminId;

        try {
            $refreshed = $this->contextStorage->refreshTtl($key, $this->ttl);

            if ($refreshed) {
                $this->logger->debug('Admin context TTL refreshed', [
                    'adminId' => $adminId,
                    'ttl' => $this->ttl,
                ]);
            }

            return $refreshed;
        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh admin context TTL', [
                'adminId' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Enrich AI system prompt with admin context.
     *
     * @param string                   $basePrompt The base system prompt
     * @param AdminConversationContext $context    Admin context
     *
     * @return string Enhanced prompt with context injected
     */
    public function enrichPrompt(string $basePrompt, AdminConversationContext $context): string
    {
        $contextInfo = $context->toPromptContext();

        $enrichedPrompt = $basePrompt."\n\n## Current Conversation Context\n\n".$contextInfo;

        $enrichedPrompt .= "\n\nUse this context to provide more natural and relevant responses. ";
        $enrichedPrompt .= "When the admin references 'that product', 'those orders', or 'the pending action', ";
        $enrichedPrompt .= 'refer to the entities and pending actions in the context without asking for clarification. ';

        // Add guidance for pending actions
        if (!empty($context->getPendingActions())) {
            $enrichedPrompt .= "\n\n⚠️ IMPORTANT: There are pending actions awaiting confirmation. ";
            $enrichedPrompt .= "If the admin says 'confirm', 'yes', 'proceed', or similar, execute the most recent pending action. ";
            $enrichedPrompt .= "If the admin says 'cancel', 'no', 'wait', or similar, clear the pending action.";
        }

        $this->logger->debug('AI prompt enriched with admin context', [
            'adminId' => $context->getAdminId(),
            'flow' => $context->getFlow(),
            'pendingActionsCount' => count($context->getPendingActions()),
        ]);

        return $enrichedPrompt;
    }

    /**
     * Update context after tool execution.
     *
     * @param AdminConversationContext $context    Context to update
     * @param string                   $toolName   Name of the executed tool
     * @param array                    $toolResult Result from tool execution
     *
     * @return AdminConversationContext Updated context
     */
    public function updateAfterToolExecution(
        AdminConversationContext $context,
        string $toolName,
        array $toolResult,
    ): AdminConversationContext {
        // Update last tool
        $context->setLastTool($toolName);

        // Update flow based on tool
        if (str_contains($toolName, 'Product') || str_contains($toolName, 'Stock')) {
            $context->setFlow('inventory_management');

            // Extract product IDs from result if available
            if (isset($toolResult['products']) && is_array($toolResult['products'])) {
                $productIds = array_column($toolResult['products'], 'id');
                foreach ($productIds as $productId) {
                    $context->addActiveEntity('product', $productId);
                }
            }
        } elseif (str_contains($toolName, 'Order')) {
            $context->setFlow('order_reviews');

            // Extract order IDs from result if available
            if (isset($toolResult['orders']) && is_array($toolResult['orders'])) {
                $orderIds = array_column($toolResult['orders'], 'id');
                foreach ($orderIds as $orderId) {
                    $context->addActiveEntity('order', $orderId);
                }
            }
        } elseif (str_contains($toolName, 'User') || str_contains($toolName, 'Customer')) {
            $context->setFlow('user_management');

            // Extract user IDs from result if available
            if (isset($toolResult['users']) && is_array($toolResult['users'])) {
                $userIds = array_column($toolResult['users'], 'id');
                foreach ($userIds as $userId) {
                    $context->addActiveEntity('user', $userId);
                }
            }
        } elseif (str_contains($toolName, 'Analytics') || str_contains($toolName, 'Report')) {
            $context->setFlow('analytics');

            // Extract time period from result if available
            if (isset($toolResult['timePeriod'])) {
                $context->setTimePeriod($toolResult['timePeriod']);
            }
        }

        // Increment turn count
        $context->incrementTurnCount();

        $this->logger->debug('Admin context updated after tool execution', [
            'adminId' => $context->getAdminId(),
            'tool' => $toolName,
            'flow' => $context->getFlow(),
            'turnCount' => $context->getTurnCount(),
        ]);

        return $context;
    }

    /**
     * Check if context has expired.
     *
     * @param string $adminId Admin user ID
     *
     * @return bool True if expired or doesn't exist, false if still valid
     */
    public function isContextExpired(string $adminId): bool
    {
        $key = self::KEY_PREFIX.$adminId;

        try {
            return !$this->contextStorage->exists($key);
        } catch (\Exception $e) {
            $this->logger->error('Failed to check context expiry', [
                'adminId' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return true;
        }
    }

    /**
     * Get remaining TTL for an admin context.
     *
     * @param string $adminId Admin user ID
     *
     * @return int|null Remaining seconds, null if doesn't exist
     */
    public function getRemainingTtl(string $adminId): ?int
    {
        $key = self::KEY_PREFIX.$adminId;

        try {
            return $this->contextStorage->getTtl($key);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get remaining TTL', [
                'adminId' => $adminId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
