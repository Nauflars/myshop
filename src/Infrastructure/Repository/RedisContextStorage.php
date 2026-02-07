<?php

declare(strict_types=1);

namespace App\Infrastructure\Repository;

use App\Domain\Repository\ContextStorageInterface;
use App\Domain\ValueObject\ConversationContext;
use Predis\Client;
use Psr\Log\LoggerInterface;
use RuntimeException;

/**
 * Redis implementation of context storage
 * 
 * Uses Predis client to store conversation contexts in Redis
 * with automatic expiration via TTL.
 */
class RedisContextStorage implements ContextStorageInterface
{
    public function __construct(
        private readonly Client $redis,
        private readonly LoggerInterface $logger
    ) {
    }

    public function set(string $key, ConversationContext $context, int $ttl): void
    {
        try {
            $data = $context->toArray();
            // Add context type for deserialization
            $data['_context_type'] = get_class($context);
            $serialized = json_encode($data, JSON_THROW_ON_ERROR);
            
            $this->redis->setex($key, $ttl, $serialized);
            
            $this->logger->debug('Context stored in Redis', [
                'key' => $key,
                'ttl' => $ttl,
                'context_type' => get_class($context)
            ]);
        } catch (\JsonException $e) {
            $this->logger->error('Failed to serialize context', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException("Failed to serialize context: {$e->getMessage()}", 0, $e);
        } catch (\Exception $e) {
            $this->logger->error('Failed to store context in Redis', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException("Failed to store context: {$e->getMessage()}", 0, $e);
        }
    }

    public function get(string $key): ?ConversationContext
    {
        try {
            $serialized = $this->redis->get($key);
            
            if ($serialized === null) {
                $this->logger->debug('Context not found in Redis', ['key' => $key]);
                return null;
            }

            $data = json_decode($serialized, true, 512, JSON_THROW_ON_ERROR);
            
            // Context type is stored in the data to enable proper deserialization
            $contextClass = $data['_context_type'] ?? null;
            
            if (!$contextClass || !class_exists($contextClass)) {
                $this->logger->error('Invalid or missing context type', [
                    'key' => $key,
                    'context_type' => $contextClass
                ]);
                return null;
            }

            $context = $contextClass::fromArray($data);
            
            $this->logger->debug('Context retrieved from Redis', [
                'key' => $key,
                'context_type' => $contextClass
            ]);
            
            return $context;
        } catch (\JsonException $e) {
            $this->logger->error('Failed to deserialize context', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Failed to retrieve context from Redis', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            throw new RuntimeException("Failed to retrieve context: {$e->getMessage()}", 0, $e);
        }
    }

    public function exists(string $key): bool
    {
        try {
            return (bool) $this->redis->exists($key);
        } catch (\Exception $e) {
            $this->logger->error('Failed to check context existence', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            $result = $this->redis->del([$key]);
            
            $deleted = $result > 0;
            $this->logger->debug('Context deletion attempt', [
                'key' => $key,
                'deleted' => $deleted
            ]);
            
            return $deleted;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete context', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function refreshTtl(string $key, int $ttl): bool
    {
        try {
            $result = $this->redis->expire($key, $ttl);
            
            $this->logger->debug('TTL refresh attempt', [
                'key' => $key,
                'ttl' => $ttl,
                'success' => $result
            ]);
            
            return (bool) $result;
        } catch (\Exception $e) {
            $this->logger->error('Failed to refresh TTL', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function getTtl(string $key): ?int
    {
        try {
            $ttl = $this->redis->ttl($key);
            
            if ($ttl === -2) {
                // Key does not exist
                return null;
            }
            
            if ($ttl === -1) {
                // Key exists but has no expiry
                return -1;
            }
            
            return $ttl;
        } catch (\Exception $e) {
            $this->logger->error('Failed to get TTL', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}
