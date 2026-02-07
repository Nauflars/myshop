<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Repository\ContextStorageInterface;
use App\Domain\ValueObject\CustomerConversationContext;
use Psr\Log\LoggerInterface;

/**
 * Manages customer conversation context
 * 
 * Handles loading, saving, and enriching AI prompts with customer context
 * to enable natural follow-up questions and context-aware responses.
 */
class CustomerContextManager
{
    private const KEY_PREFIX = 'chat:customer:';

    public function __construct(
        private readonly ContextStorageInterface $contextStorage,
        private readonly LoggerInterface $logger,
        private readonly int $ttl
    ) {
    }

    /**
     * Load context for a customer
     * 
     * @param string $userId Customer user ID
     * @return CustomerConversationContext|null Context if found and not expired
     */
    public function loadContext(string $userId): ?CustomerConversationContext
    {
        $key = self::KEY_PREFIX . $userId;
        
        try {
            $context = $this->contextStorage->get($key);
            
            if ($context === null) {
                $this->logger->info('No existing context found for customer', ['userId' => $userId]);
                return null;
            }
            
            if (!$context instanceof CustomerConversationContext) {
                $this->logger->error('Invalid context type retrieved', [
                    'userId' => $userId,
                    'expected' => CustomerConversationContext::class,
                    'actual' => get_class($context)
                ]);
                return null;
            }
            
            $this->logger->debug('Customer context loaded', [
                'userId' => $userId,
                'flow' => $context->getFlow(),
                'turnCount' => $context->getTurnCount()
            ]);
            
            return $context;
        } catch (\Exception $e) {
            $this->logger->error('Failed to load customer context', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Save context for a customer
     * 
     * @param CustomerConversationContext $context Context to save
     * @param bool $refreshTtl Whether to refresh TTL for existing context
     */
    public function saveContext(CustomerConversationContext $context, bool $refreshTtl = true): void
    {
        $key = self::KEY_PREFIX . $context->getUserId();
        
        try {
            $this->contextStorage->set($key, $context, $this->ttl);
            
            $this->logger->debug('Customer context saved', [
                'userId' => $context->getUserId(),
                'flow' => $context->getFlow(),
                'turnCount' => $context->getTurnCount(),
                'ttl' => $this->ttl
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save customer context', [
                'userId' => $context->getUserId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Get or create context for a customer
     * 
     * @param string $userId Customer user ID
     * @param string $language Preferred language (default: 'en')
     * @return CustomerConversationContext Existing or freshly created context
     */
    public function getOrCreateContext(string $userId, string $language = 'en'): CustomerConversationContext
    {
        $context = $this->loadContext($userId);
        
        if ($context === null) {
            $this->logger->info('Creating fresh context for customer', ['userId' => $userId]);
            $context = CustomerConversationContext::createFresh($userId, $language);
        }
        
        return $context;
    }

    /**
     * Delete context for a customer (manual reset)
     * 
     * @param string $userId Customer user ID
     * @return bool True if deleted, false if not found
     */
    public function deleteContext(string $userId): bool
    {
        $key = self::KEY_PREFIX . $userId;
        
        try {
            $deleted = $this->contextStorage->delete($key);
            
            if ($deleted) {
                $this->logger->info('Customer context deleted', ['userId' => $userId]);
            } else {
                $this->logger->debug('No context to delete for customer', ['userId' => $userId]);
            }
            
            return $deleted;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete customer context', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Refresh TTL for existing context
     * 
     * @param string $userId Customer user ID
     * @return bool True if refreshed, false if not found
     */
    public function refreshTtl(string $userId): bool
    {
        $key = self::KEY_PREFIX . $userId;
        
        try {
            $refreshed = $this->contextStorage->refreshTtl($key, $this->ttl);
            
            if ($refreshed) {
                $this->logger->debug('Customer context TTL refreshed', [
                    'userId' => $userId,
                    'ttl' => $this->ttl
                ]);
            }
            
            return $refreshed;
        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh customer context TTL', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Enrich AI system prompt with customer context
     * 
     * @param string $basePrompt The base system prompt
     * @param CustomerConversationContext $context Customer context
     * @return string Enhanced prompt with context injected
     */
    public function enrichPrompt(string $basePrompt, CustomerConversationContext $context): string
    {
        $contextInfo = $context->toPromptContext();
        
        $enrichedPrompt = $basePrompt . "\n\n## Current Conversation Context\n\n" . $contextInfo;
        
        $enrichedPrompt .= "\n\nUse this context to provide more natural and relevant responses. ";
        $enrichedPrompt .= "When the user asks follow-up questions like 'show me the cheapest one' or 'add it to cart', ";
        $enrichedPrompt .= "refer to the products in the context without asking for clarification.";
        
        $this->logger->debug('AI prompt enriched with customer context', [
            'userId' => $context->getUserId(),
            'flow' => $context->getFlow()
        ]);
        
        return $enrichedPrompt;
    }

    /**
     * Update context after tool execution
     * 
     * @param CustomerConversationContext $context Context to update
     * @param string $toolName Name of the executed tool
     * @param array $toolResult Result from tool execution
     * @return CustomerConversationContext Updated context
     */
    public function updateAfterToolExecution(
        CustomerConversationContext $context,
        string $toolName,
        array $toolResult
    ): CustomerConversationContext {
        // Update last tool
        $context->setLastTool($toolName);
        
        // Update flow based on tool
        if (str_contains($toolName, 'Product')) {
            $context->setFlow('product_browsing');
            
            // Extract product IDs from result if available
            if (isset($toolResult['products']) && is_array($toolResult['products'])) {
                $productIds = array_column($toolResult['products'], 'id');
                if (!empty($productIds)) {
                    $context->setSelectedProducts($productIds);
                }
            }
        } elseif (str_contains($toolName, 'Cart')) {
            $context->setFlow('cart_management');
            
            // Update cart snapshot if available
            if (isset($toolResult['cart'])) {
                $context->setCartSnapshot($toolResult['cart']);
            }
        } elseif (str_contains($toolName, 'Order')) {
            $context->setFlow('order_tracking');
        } elseif (str_contains($toolName, 'Checkout')) {
            $context->setFlow('checkout');
        }
        
        // Increment turn count
        $context->incrementTurnCount();
        
        $this->logger->debug('Customer context updated after tool execution', [
            'userId' => $context->getUserId(),
            'tool' => $toolName,
            'flow' => $context->getFlow(),
            'turnCount' => $context->getTurnCount()
        ]);
        
        return $context;
    }

    /**
     * Check if context has expired
     * 
     * @param string $userId Customer user ID
     * @return bool True if expired or doesn't exist, false if still valid
     */
    public function isContextExpired(string $userId): bool
    {
        $key = self::KEY_PREFIX . $userId;
        
        try {
            return !$this->contextStorage->exists($key);
        } catch (\Exception $e) {
            $this->logger->error('Failed to check context expiry', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return true;
        }
    }

    /**
     * Get remaining TTL for a customer context
     * 
     * @param string $userId Customer user ID
     * @return int|null Remaining seconds, null if doesn't exist
     */
    public function getRemainingTtl(string $userId): ?int
    {
        $key = self::KEY_PREFIX . $userId;
        
        try {
            return $this->contextStorage->getTtl($key);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get remaining TTL', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
