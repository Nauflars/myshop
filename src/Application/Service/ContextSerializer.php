<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\ValueObject\ConversationContext;
use JsonException;
use Psr\Log\LoggerInterface;

/**
 * Service for serializing and deserializing conversation contexts
 * 
 * Handles conversion between ConversationContext objects and
 * string representations suitable for storage in Redis.
 */
class ContextSerializer
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Serialize a context to JSON string
     * 
     * @param ConversationContext $context The context to serialize
     * @return string JSON string representation
     * @throws JsonException If serialization fails
     */
    public function serialize(ConversationContext $context): string
    {
        $data = $context->toArray();
        
        // Add context type for deserialization
        $data['_context_type'] = get_class($context);
        $data['_serialized_at'] = (new \DateTimeImmutable())->format(\DateTimeInterface::RFC3339);
        
        try {
            $json = json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT);
            
            $this->logger->debug('Context serialized', [
                'context_type' => get_class($context),
                'size_bytes' => strlen($json)
            ]);
            
            return $json;
        } catch (JsonException $e) {
            $this->logger->error('Failed to serialize context', [
                'context_type' => get_class($context),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Deserialize a JSON string to a context object
     * 
     * @param string $json JSON string representation
     * @return ConversationContext|null The deserialized context or null if invalid
     */
    public function deserialize(string $json): ?ConversationContext
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            
            if (!is_array($data)) {
                $this->logger->warning('Invalid JSON data: not an array');
                return null;
            }
            
            $contextClass = $data['_context_type'] ?? null;
            
            if (!$contextClass) {
                $this->logger->warning('Missing context type in serialized data');
                return null;
            }
            
            if (!class_exists($contextClass)) {
                $this->logger->error('Context class does not exist', [
                    'context_type' => $contextClass
                ]);
                return null;
            }
            
            if (!is_subclass_of($contextClass, ConversationContext::class)) {
                $this->logger->error('Invalid context class', [
                    'context_type' => $contextClass,
                    'expected' => ConversationContext::class
                ]);
                return null;
            }
            
            /** @var ConversationContext $context */
            $context = $contextClass::fromArray($data);
            
            $this->logger->debug('Context deserialized', [
                'context_type' => $contextClass
            ]);
            
            return $context;
        } catch (JsonException $e) {
            $this->logger->error('Failed to deserialize context', [
                'error' => $e->getMessage()
            ]);
            return null;
        } catch (\Exception $e) {
            $this->logger->error('Unexpected error during deserialization', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Validate a serialized context string
     * 
     * @param string $json JSON string to validate
     * @return bool True if valid, false otherwise
     */
    public function isValid(string $json): bool
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            
            if (!is_array($data)) {
                return false;
            }
            
            $contextClass = $data['_context_type'] ?? null;
            
            return $contextClass && class_exists($contextClass) && is_subclass_of($contextClass, ConversationContext::class);
        } catch (JsonException) {
            return false;
        }
    }

    /**
     * Extract metadata from serialized context without full deserialization
     * 
     * @param string $json JSON string
     * @return array{type: string, serializedAt: string}|null Metadata or null if invalid
     */
    public function getMetadata(string $json): ?array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            
            if (!is_array($data)) {
                return null;
            }
            
            return [
                'type' => $data['_context_type'] ?? 'unknown',
                'serializedAt' => $data['_serialized_at'] ?? 'unknown'
            ];
        } catch (JsonException) {
            return null;
        }
    }
}
