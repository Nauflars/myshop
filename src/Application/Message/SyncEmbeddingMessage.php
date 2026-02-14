<?php

declare(strict_types=1);

namespace App\Application\Message;

/**
 * SyncEmbeddingMessage - Async message for embedding synchronization.
 *
 * Implements spec-010 FR-005: Async embedding sync using Symfony Messenger
 */
final class SyncEmbeddingMessage
{
    public function __construct(
        private readonly string $productId,
        private readonly string $operation,
    ) {
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }
}
