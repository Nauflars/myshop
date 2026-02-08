# Unified Conversation Architecture - Developer Guide

**Specification**: spec-012  
**Version**: 1.0  
**Status**: Implemented  
**Date**: February 2026

---

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Redis Key Structure](#redis-key-structure)
3. [Storage Layer](#storage-layer)
4. [Context Managers](#context-managers)
5. [Controller Integration](#controller-integration)
6. [State Management](#state-management)
7. [Code Examples](#code-examples)
8. [Migration Guide](#migration-guide)
9. [Testing](#testing)
10. [Troubleshooting](#troubleshooting)

---

## Architecture Overview

### The Two-Tier Storage Model

spec-012 introduces a unified architecture for conversation management with two storage layers:

```
┌─────────────────────────────────────────────┐
│              User Request                   │
└─────────────────┬───────────────────────────┘
                  │
         ┌────────▼────────┐
         │   Controller    │
         │  (Chat/Admin)   │
         └────────┬────────┘
                  │
    ┌─────────────┴─────────────┐
    │                           │
┌───▼──────────┐      ┌─────────▼──────┐
│  MYSQL DB    │      │  REDIS CACHE   │
│              │      │                │
│ Full history │      │ Last 10 msgs   │
│ Persistence  │      │ State          │
│ Audit trail  │      │ Metadata       │
└──────────────┘      └────────────────┘
```

**MySQL** (via `ConversationManager`):
- Complete conversation history
- Persistent audit trail
- Searchable long-term storage

**Redis** (via `UnifiedConversationStorage`):
- Last 10 messages (FIFO)
- Conversation state (structured data)
- Metadata (timestamps, role)
- 30-minute TTL

### Why This Design?

1. **Performance**: AI agents only need recent context, not full history
2. **Scalability**: Redis caching reduces MySQL load
3. **Consistency**: Same architecture for Customer and Admin assistants
4. **Flexibility**: State separated from history for easier updates

---

## Redis Key Structure

### Key Pattern

All conversation data uses this pattern:

```
conversation:{role}:{userId}:{conversationId}:{suffix}
```

**Components**:
- `{role}`: `client` or `admin`
- `{userId}`: User's database ID (string)
- `{conversationId}`: UUID v4 per conversation
- `{suffix}`: `history`, `state`, or `meta`

### Key Types

#### 1. History Key

**Pattern**: `conversation:client:123:abc-uuid:history`

**Value** (JSON array):
```json
[
  {
    "role": "user",
    "content": "Hello, I need help",
    "timestamp": 1707398400
  },
  {
    "role": "assistant",
    "content": "Hi! How can I help you?",
    "timestamp": 1707398405
  }
]
```

**Max size**: 10 messages (FIFO)  
**TTL**: 30 minutes (renewed on each interaction)

#### 2. State Key

**Pattern**: `conversation:client:123:abc-uuid:state`

**Value** (JSON object for customer):
```json
{
  "flow": "cart_management",
  "last_tool": "AddToCart",
  "turn_count": 5,
  "selected_products": [1, 2, 3],
  "cart_items": [
    {"product_id": 1, "quantity": 2}
  ],
  "checkout_step": null,
  "language": "en"
}
```

**Value** (JSON object for admin):
```json
{
  "last_tool": "AdminCreateProduct",
  "turn_count": 3,
  "current_product": 42,
  "draft_product": {
    "name": "New Shoe",
    "price": 99.99
  },
  "current_period": "2026-02",
  "pending_confirmation": null
}
```

#### 3. Meta Key

**Pattern**: `conversation:client:123:abc-uuid:meta`

**Value** (JSON object):
```json
{
  "role": "client",
  "created_at": 1707398400,
  "last_activity": 1707398550
}
```

**Updated**: Every interaction (auto-touch)

---

## Storage Layer

### `UnifiedConversationStorage`

Located: `src/Infrastructure/Repository/UnifiedConversationStorage.php`

Core service for all Redis operations.

#### Key Methods

##### History Management

```php
// Get history (returns array of messages, max 10)
$history = $storage->getHistory('client', 'user123', 'conv-uuid');

// Set entire history (replaces existing)
$storage->setHistory('client', 'user123', 'conv-uuid', $messages, 1800);

// Add single message (FIFO enforced)
$storage->addMessageToHistory(
    'client',
    'user123',
    'conv-uuid',
    'user',
    'Hello!',
    1800
);
```

##### State Management

```php
// Get state
$state = $storage->getState('client', 'user123', 'conv-uuid');

// Set state (full replacement)
$storage->setState('client', 'user123', 'conv-uuid', $newState, 1800);
```

##### Metadata Management

```php
// Initialize new conversation metadata
$storage->initializeMeta('client', 'user123', 'conv-uuid', 1800);

// Update last_activity timestamp
$storage->touchMeta('client', 'user123', 'conv-uuid', 1800);

// Get metadata
$meta = $storage->getMeta('client', 'user123', 'conv-uuid');
```

##### Lifecycle

```php
// Check if conversation exists
$exists = $storage->exists('client', 'user123', 'conv-uuid');

// Refresh TTL for all keys (history, state, meta)
$storage->refreshTtl('client', 'user123', 'conv-uuid', 1800);

// Delete conversation (all 3 keys)
$storage->delete('client', 'user123', 'conv-uuid');
```

---

## Context Managers

### `UnifiedCustomerContextManager`

Located: `src/Application/Service/UnifiedCustomerContextManager.php`

High-level API for customer conversations.

#### Usage

```php
use App\Application\Service\UnifiedCustomerContextManager;

public function __construct(
    private UnifiedCustomerContextManager $contextManager
) {}

// Get or create conversation
$conversation = $this->contextManager->getOrCreateConversation(
    userId: 'user123',
    conversationId: 'existing-uuid' // or null for new
);

// Returns:
// [
//   'conversationId' => 'uuid-v4',
//   'state' => [...],
//   'history' => [...]
// ]

// Add messages
$this->contextManager->addMessage(
    'user123',
    $conversation['conversationId'],
    'user',
    'I want to buy shoes'
);

$this->contextManager->addMessage(
    'user123',
    $conversation['conversationId'],
    'assistant',
    'Great! Here are our shoes...'
);

// Update state
$state = $this->contextManager->getState('user123', $conversationId);
$state['cart_items'][] = ['product_id' => 1, 'quantity' => 2];
$this->contextManager->updateState('user123', $conversationId, $state);

// Build MessageBag for AI Agent
$messages = $this->contextManager->buildMessageBagContext('user123', $conversationId);
// Returns: [
//   ['role' => 'system', 'content' => '**Current Session State:...**'],
//   ['role' => 'user', 'content' => 'Previous message'],
//   ['role' => 'assistant', 'content' => 'Previous reply'],
//   ...
// ]
```

### `UnifiedAdminContextManager`

Located: `src/Application/Service/UnifiedAdminContextManager.php`

Same API as customer manager, but with admin-specific state.

#### Additional Methods

```php
// Set pending confirmation (destructive operations)
$this->contextManager->setPendingConfirmation(
    'admin123',
    $conversationId,
    'delete_product',
    ['product_id' => 42]
);

// Get and clear pending confirmation
$pending = $this->contextManager->getPendingConfirmation('admin123', $conversationId);
if ($pending && $pending['action'] === 'delete_product') {
    // Execute confirmed action
}
```

---

## Controller Integration

### ChatbotController Pattern

```php
use App\Application\Service\UnifiedCustomerContextManager;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;

public function chat(Request $request): JsonResponse
{
    $user = $this->security->getUser();
    $userId = (string) $user->getId();
    $userMessage = $request->get('message');
    $conversationId = $request->get('conversationId') ?? null;
    
    // 1. Save to MySQL (full history)
    $this->conversationManager->saveUserMessage($user, $conversationId, $userMessage);
    
    // 2. Get or create unified conversation (Redis)
    $conversation = $this->unifiedContextManager->getOrCreateConversation($userId, $conversationId);
    
    // 3. Add user message to Redis history
    $this->unifiedContextManager->addMessage(
        $userId,
        $conversation['conversationId'],
        'user',
        $userMessage
    );
    
    // 4. Build MessageBag with context + history
    $messages = [];
    $contextMessages = $this->unifiedContextManager->buildMessageBagContext(
        $userId,
        $conversation['conversationId']
    );
    
    foreach ($contextMessages as $msg) {
        if ($msg['role'] === 'user') {
            $messages[] = Message::ofUser($msg['content']);
        } elseif ($msg['role'] === 'assistant') {
            $messages[] = Message::ofAssistant($msg['content']);
        }
    }
    
    $messages[] = Message::ofUser($userMessage);
    
    // 5. Get AI response
    $messageBag = new MessageBag(...$messages);
    $response = $this->agent->call($messageBag);
    $assistantResponse = $response->getContent();
    
    // 6. Add assistant response to Redis history
    $this->unifiedContextManager->addMessage(
        $userId,
        $conversation['conversationId'],
        'assistant',
        $assistantResponse
    );
    
    // 7. Update state
    $state = $this->unifiedContextManager->getState($userId, $conversation['conversationId']);
    $state['turn_count']++;
    $this->unifiedContextManager->updateState($userId, $conversation['conversationId'], $state);
    
    // 8. Save assistant response to MySQL
    $this->conversationManager->saveAssistantMessage($user, $conversationId, $assistantResponse);
    
    return $this->json(['response' => $assistantResponse]);
}
```

---

## State Management

### Customer State Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `flow` | string | Current conversation flow | `browsing`, `cart_management`, `checkout`, `order_tracking` |
| `last_tool` | string\|null | Last tool executed | `SearchProducts`, `AddToCart` |
| `turn_count` | int | Number of interactions | `5` |
| `selected_products` | array | Product IDs being discussed (max 5) | `[1, 2, 3]` |
| `cart_items` | array | Items in cart | `[{"product_id": 1, "quantity": 2}]` |
| `checkout_step` | string\|null | Checkout progress | `address`, `payment`, `confirmation` |
| `language` | string | Conversation language | `en`, `es` |

### Admin State Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `last_tool` | string\|null | Last admin tool executed | `AdminCreateProduct` |
| `turn_count` | int | Number of interactions | `3` |
| `current_product` | int\|null | Product being edited | `42` |
| `draft_product` | array\|null | Multi-step product creation | `{"name": "Shoe", "price": 99}` |
| `current_period` | string\|null | Analytics period | `2026-02` |
| `pending_confirmation` | array\|null | Awaiting user confirmation | `{"action": "delete", "params": {...}}` |

---

## Code Examples

### Example 1: New Customer Conversation

```php
$conversation = $contextManager->getOrCreateConversation('user123');

// Result:
// [
//   'conversationId' => '550e8400-e29b-41d4-a716-446655440000',
//   'state' => [
//     'flow' => 'browsing',
//     'last_tool' => null,
//     'turn_count' => 0,
//     'selected_products' => [],
//     'cart_items' => [],
//     'checkout_step' => null,
//     'language' => 'en'
//   ],
//   'history' => []
// ]
```

### Example 2: Add Message to Existing Conversation

```php
$contextManager->addMessage('user123', $conversationId, 'user', 'Show me running shoes');
$contextManager->addMessage('user123', $conversationId, 'assistant', 'Here are our running shoes...');

// Redis now contains:
// conversation:client:user123:conv-uuid:history = [
//   {"role": "user", "content": "Show me running shoes", "timestamp": 1707398400},
//   {"role": "assistant", "content": "Here are our running shoes...", "timestamp": 1707398405}
// ]
```

### Example 3: Update State After Tool Execution

```php
$contextManager->updateAfterToolExecution(
    'user123',
    $conversationId,
    'AddToCart',
    ['product_id' => 1, 'quantity' => 2]
);

// State automatically updated:
// {
//   "flow": "cart_management", // <-- Changed from "browsing"
//   "last_tool": "AddToCart",   // <-- Updated
//   "turn_count": 1             // <-- Incremented
// }
```

### Example 4: Build MessageBag for AI

```php
$messages = $contextManager->buildMessageBagContext('user123', $conversationId);

// Returns:
// [
//   [
//     'role' => 'system',
//     'content' => '**Current Session State:**\n\n- Flow: cart_management\n- Turn count: 3\n- Cart: 1 item\n...'
//   ],
//   ['role' => 'user', 'content' => 'Show me running shoes'],
//   ['role' => 'assistant', 'content' => 'Here are our running shoes...'],
//   ['role' => 'user', 'content' => 'Add the blue ones to cart'],
// ]
```

---

## Migration Guide

### From spec-009 to spec-012

#### Breaking Changes

1. **Constructor signature changed**:
   ```php
   // OLD (spec-009)
   public function __construct(
       private readonly CustomerContextManager $contextManager
   ) {}
   
   // NEW (spec-012)
   public function __construct(
       private readonly UnifiedCustomerContextManager $unifiedContextManager
   ) {}
   ```

2. **API methods changed**:
   ```php
   // OLD
   $context = $contextManager->getOrCreateContext($userId);
   $contextManager->saveContext($context);
   
   // NEW
   $conversation = $unifiedContextManager->getOrCreateConversation($userId, $conversationId);
   // Auto-saves, no explicit save needed
   ```

3. **State access changed**:
   ```php
   // OLD
   $context->incrementTurnCount();
   
   // NEW
   $state = $unifiedContextManager->getState($userId, $conversationId);
   $state['turn_count']++;
   $unifiedContextManager->updateState($userId, $conversationId, $state);
   ```

#### Update Checklist

- [ ] Replace `CustomerContextManager` with `UnifiedCustomerContextManager`
- [ ] Update constructor dependencies
- [ ] Replace `getOrCreateContext()` → `getOrCreateConversation()`
- [ ] Remove explicit `saveContext()` calls (auto-saves now)
- [ ] Update state access patterns
- [ ] Test with live traffic

---

## Testing

### Unit Tests

```bash
vendor/bin/phpunit tests/Unit/Infrastructure/Repository/UnifiedConversationStorageTest.php
```

Tests FIFO, key patterns, TTL, metadata.

### Integration Tests

```bash
vendor/bin/phpunit tests/Integration/Application/Service/UnifiedCustomerContextManagerTest.php
```

Tests conversation lifecycle, state management, MessageBag construction.

### Manual Redis Verification

```bash
# Connect to Redis
redis-cli

# List all conversation keys
KEYS conversation:*

# Inspect history
GET conversation:client:123:abc-uuid:history

# Inspect state
GET conversation:client:123:abc-uuid:state

# Check TTL
TTL conversation:client:123:abc-uuid:history

# Delete conversation
DEL conversation:client:123:abc-uuid:history
DEL conversation:client:123:abc-uuid:state
DEL conversation:client:123:abc-uuid:meta
```

---

## Troubleshooting

### Problem: Conversation not found after creation

**Cause**: Redis connection issue or TTL expired.

**Solution**:
```php
// Check if conversation exists
if (!$contextManager->exists($userId, $conversationId)) {
    // Recreate
    $conversation = $contextManager->getOrCreateConversation($userId);
}
```

### Problem: History not updating

**Cause**: FIFO limit reached, oldest messages removed.

**Solution**: Expected behavior. Full history is in MySQL, Redis keeps only last 10.

### Problem: State changes not persisting

**Cause**: Missing `updateState()` call.

**Solution**: Always call `updateState()` after modifying state array:
```php
$state = $contextManager->getState($userId, $conversationId);
$state['cart_items'][] = $newItem;
$contextManager->updateState($userId, $conversationId, $state); // <-- REQUIRED
```

### Problem: TTL expires too quickly

**Cause**: No activity for 30 minutes.

**Solution**: TTL auto-refreshes on every interaction. If expiring, check:
```php
// Manually refresh if needed
$contextManager->refreshTtl($userId, $conversationId);
```

---

**Next**: See [Migration Guide](./migration-guide.md) for production rollout plan.
