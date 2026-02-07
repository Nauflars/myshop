<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Customer conversation context  
 * 
 * Tracks conversation state for customer chatbot interactions
 * to enable natural follow-up questions and context-aware responses.
 * 
 * Context Attributes:
 * - userId: Unique customer identifier
 * - flow: Current conversation flow (product_browsing, cart_management, checkout, order_tracking)
 * - selectedProducts: Array of product IDs from recent searches/views
 * - cartSnapshot: Current cart state (product IDs and quantities)
 * - lastTool: Last AI tool executed (GetProductsTool, AddToCartTool, etc.)
 * - language: User's preferred language (en, es, fr, etc.)
 */
class CustomerConversationContext extends ConversationContext
{
    private const DEFAULT_LANGUAGE = 'en';

    public function __construct(
        string $userId,
        string $flow,
        ?string $lastTool,
        int $turnCount,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
        private array $selectedProducts = [],
        private array $cartSnapshot = [],
        private string $language = self::DEFAULT_LANGUAGE
    ) {
        parent::__construct($userId, $flow, $lastTool, $turnCount, $createdAt, $updatedAt);
    }

    public function getSelectedProducts(): array
    {
        return $this->selectedProducts;
    }

    public function setSelectedProducts(array $productIds): void
    {
        $this->selectedProducts = $productIds;
        $this->touch();
    }

    public function addSelectedProduct(int $productId): void
    {
        if (!in_array($productId, $this->selectedProducts, true)) {
            $this->selectedProducts[] = $productId;
            $this->touch();
        }
    }

    public function clearSelectedProducts(): void
    {
        $this->selectedProducts = [];
        $this->touch();
    }

    public function getCartSnapshot(): array
    {
        return $this->cartSnapshot;
    }

    public function setCartSnapshot(array $cart): void
    {
        $this->cartSnapshot = $cart;
        $this->touch();
    }

    public function getLanguage(): string
    {
        return $this->language;
    }

    public function setLanguage(string $language): void
    {
        $this->language = $language;
        $this->touch();
    }

    public function toArray(): array
    {
        return [
            'userId' => $this->userId,
            'flow' => $this->flow,
            'lastTool' => $this->lastTool,
            'turnCount' => $this->turnCount,
            'createdAt' => $this->createdAt->format(\DateTimeInterface::RFC3339),
            'updatedAt' => $this->updatedAt->format(\DateTimeInterface::RFC3339),
            'selectedProducts' => $this->selectedProducts,
            'cartSnapshot' => $this->cartSnapshot,
            'language' => $this->language,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            userId: $data['userId'],
            flow: $data['flow'],
            lastTool: $data['lastTool'] ?? null,
            turnCount: $data['turnCount'] ?? 1,
            createdAt: new DateTimeImmutable($data['createdAt']),
            updatedAt: new DateTimeImmutable($data['updatedAt']),
            selectedProducts: $data['selectedProducts'] ?? [],
            cartSnapshot: $data['cartSnapshot'] ?? [],
            language: $data['language'] ?? self::DEFAULT_LANGUAGE
        );
    }

    public function toPromptContext(): string
    {
        $context = "Customer Context:\n";
        $context .= "- User ID: {$this->userId}\n";
        $context .= "- Current Flow: {$this->flow}\n";
        $context .= "- Turn Count: {$this->turnCount}\n";
        
        if ($this->lastTool) {
            $context .= "- Last Tool Used: {$this->lastTool}\n";
        }
        
        if (!empty($this->selectedProducts)) {
            $productList = implode(', ', $this->selectedProducts);
            $context .= "- Recently Viewed Products: [{$productList}]\n";
        }
        
        if (!empty($this->cartSnapshot)) {
            $itemCount = array_sum(array_column($this->cartSnapshot, 'quantity'));
            $context .= "- Cart Items: {$itemCount} item(s)\n";
        }
        
        $context .= "- Language: {$this->language}\n";
        
        return $context;
    }

    /**
     * Create a fresh context for a new conversation
     */
    public static function createFresh(string $userId, string $language = self::DEFAULT_LANGUAGE): self
    {
        $now = new DateTimeImmutable();
        return new self(
            userId: $userId,
            flow: 'browsing',
            lastTool: null,
            turnCount: 0,
            createdAt: $now,
            updatedAt: $now,
            selectedProducts: [],
            cartSnapshot: [],
            language: $language
        );
    }
}
