<?php

declare(strict_types=1);

namespace App\Infrastructure\AI\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * ConversationManager - Manages conversation history using Symfony session
 * 
 * This service stores and retrieves conversation context for multi-turn AI interactions.
 * Implements conversation memory to enable contextual follow-up questions.
 * 
 * Architecture: Infrastructure layer service (session management)
 * DDD Role: Technical adapter for conversation persistence
 */
class ConversationManager
{
    private const SESSION_KEY = 'ai_conversation_history';
    private const MAX_MESSAGES = 20; // Keep last 20 messages for context
    
    public function __construct(
        private readonly RequestStack $requestStack
    ) {
    }
    
    /**
     * Add a message to the conversation history
     *
     * @param string $role Either 'user' or 'assistant'
     * @param string $content The message content
     * @param array<string, mixed> $metadata Optional metadata (tool usage, tokens, etc.)
     */
    public function addMessage(string $role, string $content, array $metadata = []): void
    {
        $session = $this->requestStack->getSession();
        $history = $session->get(self::SESSION_KEY, []);
        
        $message = [
            'role' => $role,
            'content' => $content,
            'timestamp' => time(),
            'metadata' => $metadata,
        ];
        
        $history[] = $message;
        
        // Limit history size to prevent session bloat
        if (count($history) > self::MAX_MESSAGES) {
            $history = array_slice($history, -self::MAX_MESSAGES);
        }
        
        $session->set(self::SESSION_KEY, $history);
    }
    
    /**
     * Get the complete conversation history
     *
     * @return array<int, array{role: string, content: string, timestamp: int, metadata: array}>
     */
    public function getHistory(): array
    {
        $session = $this->requestStack->getSession();
        return $session->get(self::SESSION_KEY, []);
    }
    
    /**
     * Get conversation history formatted for AI agent
     * Returns only role and content (excludes metadata)
     *
     * @return array<int, array{role: string, content: string}>
     */
    public function getFormattedHistory(): array
    {
        $history = $this->getHistory();
        
        return array_map(function ($message) {
            return [
                'role' => $message['role'],
                'content' => $message['content'],
            ];
        }, $history);
    }
    
    /**
     * Clear the conversation history
     * Called on logout or when user explicitly resets conversation
     */
    public function clearHistory(): void
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::SESSION_KEY);
    }
    
    /**
     * Get conversation ID for debugging and monitoring
     * Uses session ID as conversation identifier
     */
    public function getConversationId(): string
    {
        $session = $this->requestStack->getSession();
        return $session->getId();
    }
    
    /**
     * Get the number of messages in current conversation
     */
    public function getMessageCount(): int
    {
        return count($this->getHistory());
    }
    
    /**
     * Check if conversation has context (history exists)
     */
    public function hasContext(): bool
    {
        return $this->getMessageCount() > 0;
    }
}
