<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use Predis\Client;
use Psr\Log\LoggerInterface;

/**
 * Unified Conversation Storage (spec-012).
 *
 * Implementa el modelo unificado de almacenamiento de conversaciones:
 * - conversation:{role}:{userId}:{uuid}:history
 * - conversation:{role}:{userId}:{uuid}:state
 * - conversation:{role}:{userId}:{uuid}:meta
 *
 * Ventajas:
 * - Historial corto en Redis (últimos 10 mensajes) para rápido acceso
 * - Estado estructurado separado
 * - Metadata para tracking
 * - Modelo consistente entre Cliente y Admin
 */
class UnifiedConversationStorage
{
    private const MAX_HISTORY_MESSAGES = 10;
    private const KEY_PATTERN = 'conversation:%s:%s:%s:%s'; // role, userId, uuid, suffix

    public function __construct(
        private readonly Client $redis,
        private readonly LoggerInterface $logger,
        private readonly int $defaultTtl = 1800, // 30 minutes
    ) {
    }

    /**
     * Generate conversation key.
     */
    private function makeKey(string $role, string $userId, string $conversationId, string $suffix): string
    {
        return sprintf(
            self::KEY_PATTERN,
            $role,
            $userId,
            $conversationId,
            $suffix
        );
    }

    /**
     * Get all keys for a conversation.
     */
    private function getAllKeys(string $role, string $userId, string $conversationId): array
    {
        return [
            'history' => $this->makeKey($role, $userId, $conversationId, 'history'),
            'state' => $this->makeKey($role, $userId, $conversationId, 'state'),
            'meta' => $this->makeKey($role, $userId, $conversationId, 'meta'),
        ];
    }

    /**
     * Get conversation history (últimos 10 mensajes).
     *
     * @return array<array{role: string, content: string}>
     */
    public function getHistory(string $role, string $userId, string $conversationId): array
    {
        $key = $this->makeKey($role, $userId, $conversationId, 'history');

        try {
            $serialized = $this->redis->get($key);

            if (null === $serialized) {
                $this->logger->debug('No history found in Redis', [
                    'role' => $role,
                    'userId' => $userId,
                    'conversationId' => $conversationId,
                ]);

                return [];
            }

            $history = json_decode($serialized, true, 512, JSON_THROW_ON_ERROR);

            $this->logger->debug('History retrieved from Redis', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'messageCount' => count($history),
            ]);

            return $history;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve history', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Set conversation history (sobrescribe).
     *
     * @param array<array{role: string, content: string}> $history
     */
    public function setHistory(
        string $role,
        string $userId,
        string $conversationId,
        array $history,
        ?int $ttl = null,
    ): bool {
        $key = $this->makeKey($role, $userId, $conversationId, 'history');
        $ttl = $ttl ?? $this->defaultTtl;

        try {
            $serialized = json_encode($history, JSON_THROW_ON_ERROR);
            $this->redis->setex($key, $ttl, $serialized);

            $this->logger->debug('History saved to Redis', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'messageCount' => count($history),
                'ttl' => $ttl,
            ]);

            // Actualizar last_activity en metadata
            $this->touchMeta($role, $userId, $conversationId, $ttl);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to save history', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Add message to history with FIFO (spec-012 section 4.2).
     *
     * Añade mensaje y mantiene solo los últimos 10 mensajes.
     * Excluye mensajes técnicos (solo user/assistant).
     */
    public function addMessageToHistory(
        string $role,
        string $userId,
        string $conversationId,
        string $messageRole,
        string $content,
        ?int $ttl = null,
    ): bool {
        // Solo guardar mensajes user/assistant (no system, no tool)
        if (!in_array($messageRole, ['user', 'assistant'], true)) {
            $this->logger->debug('Skipping non-human message in history', [
                'messageRole' => $messageRole,
            ]);

            return true;
        }

        $history = $this->getHistory($role, $userId, $conversationId);

        // Agregar nuevo mensaje
        $history[] = [
            'role' => $messageRole,
            'content' => $content,
        ];

        // Aplicar límite FIFO: solo últimos 10
        if (count($history) > self::MAX_HISTORY_MESSAGES) {
            $history = array_slice($history, -self::MAX_HISTORY_MESSAGES);
        }

        return $this->setHistory($role, $userId, $conversationId, $history, $ttl);
    }

    /**
     * Get conversation state (estado estructurado).
     */
    public function getState(string $role, string $userId, string $conversationId): ?array
    {
        $key = $this->makeKey($role, $userId, $conversationId, 'state');

        try {
            $serialized = $this->redis->get($key);

            if (null === $serialized) {
                return null;
            }

            return json_decode($serialized, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve state', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Set conversation state.
     */
    public function setState(
        string $role,
        string $userId,
        string $conversationId,
        array $state,
        ?int $ttl = null,
    ): bool {
        $key = $this->makeKey($role, $userId, $conversationId, 'state');
        $ttl = $ttl ?? $this->defaultTtl;

        try {
            $serialized = json_encode($state, JSON_THROW_ON_ERROR);
            $this->redis->setex($key, $ttl, $serialized);

            $this->logger->debug('State saved to Redis', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'ttl' => $ttl,
            ]);

            // Actualizar last_activity
            $this->touchMeta($role, $userId, $conversationId, $ttl);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to save state', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get conversation metadata.
     */
    public function getMeta(string $role, string $userId, string $conversationId): ?array
    {
        $key = $this->makeKey($role, $userId, $conversationId, 'meta');

        try {
            $serialized = $this->redis->get($key);

            if (null === $serialized) {
                return null;
            }

            return json_decode($serialized, true, 512, JSON_THROW_ON_ERROR);
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve metadata', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Set conversation metadata.
     */
    public function setMeta(
        string $role,
        string $userId,
        string $conversationId,
        array $meta,
        ?int $ttl = null,
    ): bool {
        $key = $this->makeKey($role, $userId, $conversationId, 'meta');
        $ttl = $ttl ?? $this->defaultTtl;

        try {
            $serialized = json_encode($meta, JSON_THROW_ON_ERROR);
            $this->redis->setex($key, $ttl, $serialized);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to save metadata', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Initialize conversation metadata.
     */
    public function initializeMeta(
        string $role,
        string $userId,
        string $conversationId,
        ?int $ttl = null,
    ): bool {
        $now = new \DateTimeImmutable();

        $meta = [
            'role' => $role,
            'created_at' => $now->format(\DateTimeImmutable::RFC3339),
            'last_activity' => $now->format(\DateTimeImmutable::RFC3339),
        ];

        return $this->setMeta($role, $userId, $conversationId, $meta, $ttl);
    }

    /**
     * Update last_activity timestamp.
     */
    private function touchMeta(string $role, string $userId, string $conversationId, int $ttl): void
    {
        $meta = $this->getMeta($role, $userId, $conversationId);

        if (null === $meta) {
            // Inicializar si no existe
            $this->initializeMeta($role, $userId, $conversationId, $ttl);

            return;
        }

        $meta['last_activity'] = (new \DateTimeImmutable())->format(\DateTimeImmutable::RFC3339);
        $this->setMeta($role, $userId, $conversationId, $meta, $ttl);
    }

    /**
     * Delete all keys for a conversation.
     */
    public function delete(string $role, string $userId, string $conversationId): bool
    {
        try {
            $keys = $this->getAllKeys($role, $userId, $conversationId);
            $keysToDelete = array_values($keys);

            $result = $this->redis->del($keysToDelete);

            $this->logger->info('Conversation deleted from Redis', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'keysDeleted' => $result,
            ]);

            return $result > 0;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete conversation', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Refresh TTL for all conversation keys.
     */
    public function refreshTtl(
        string $role,
        string $userId,
        string $conversationId,
        ?int $ttl = null,
    ): bool {
        $ttl = $ttl ?? $this->defaultTtl;

        try {
            $keys = $this->getAllKeys($role, $userId, $conversationId);

            foreach ($keys as $key) {
                $this->redis->expire($key, $ttl);
            }

            // Actualizar last_activity
            $this->touchMeta($role, $userId, $conversationId, $ttl);

            $this->logger->debug('TTL refreshed for conversation', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'ttl' => $ttl,
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh TTL', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if conversation exists.
     */
    public function exists(string $role, string $userId, string $conversationId): bool
    {
        $metaKey = $this->makeKey($role, $userId, $conversationId, 'meta');

        try {
            return (bool) $this->redis->exists($metaKey);
        } catch (\Exception $e) {
            $this->logger->error('Failed to check conversation existence', [
                'role' => $role,
                'userId' => $userId,
                'conversationId' => $conversationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
