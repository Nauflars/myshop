<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\ValueObject\ConversationContext;

/**
 * Interface for context storage operations
 * 
 * Provides abstraction for storing and retrieving conversation context
 * with TTL (Time To Live) support for automatic expiration.
 */
interface ContextStorageInterface
{
    /**
     * Store a conversation context with TTL
     * 
     * @param string $key Unique identifier for the context (e.g., "chat:customer:123")
     * @param ConversationContext $context The context to store
     * @param int $ttl Time to live in seconds
     * @throws \RuntimeException If storage operation fails
     */
    public function set(string $key, ConversationContext $context, int $ttl): void;

    /**
     * Retrieve a conversation context by key
     * 
     * @param string $key The context identifier
     * @return ConversationContext|null The context if found and not expired, null otherwise
     * @throws \RuntimeException If retrieval operation fails
     */
    public function get(string $key): ?ConversationContext;

    /**
     * Check if a context exists (not expired)
     * 
     * @param string $key The context identifier
     * @return bool True if context exists, false otherwise
     */
    public function exists(string $key): bool;

    /**
     * Delete a context
     * 
     * @param string $key The context identifier
     * @return bool True if deleted, false if not found
     */
    public function delete(string $key): bool;

    /**
     * Refresh TTL for an existing context
     * 
     * @param string $key The context identifier
     * @param int $ttl New time to live in seconds
     * @return bool True if TTL refreshed, false if key not found
     */
    public function refreshTtl(string $key, int $ttl): bool;

    /**
     * Get the remaining TTL for a context
     * 
     * @param string $key The context identifier
     * @return int|null Remaining seconds, null if key doesn't exist, -1 if no expiry
     */
    public function getTtl(string $key): ?int;
}
