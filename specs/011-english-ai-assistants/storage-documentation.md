# Conversation Storage Architecture Documentation

**Feature 011: English AI Assistants with Context Persistence**

This document provides detailed information about where and how AI assistant conversations and context are stored in the system.

## Architecture Overview

The system uses a **dual-storage architecture** to persist conversation data:

1. **MySQL (via Doctrine ORM)**: Permanent conversation history storage
2. **Redis**: Temporary context storage with TTL-based expiration

```
┌─────────────────┐
│  User Request   │
└────────┬────────┘
         │
         v
┌─────────────────────────────┐
│   ChatbotController         │
│  (Infrastructure Layer)     │
└──────────┬──────────────────┘
           │
           ├──────────────────────────────┐
           │                              │
           v                              v
┌──────────────────────┐      ┌────────────────────────┐
│  ConversationManager │      │ CustomerContextManager │
│  (MySQL - Doctrine)  │      │  (Redis - TTL)         │
└──────────────────────┘      └────────────────────────┘
           │                              │
           v                              v
┌──────────────────────┐      ┌────────────────────────┐
│  conversations       │      │  chat:customer:{id}    │
│  conversation_msgs   │      │  (Redis key)           │
└──────────────────────┘      └────────────────────────┘
```

---

## 1. MySQL Storage (Permanent History)

### Purpose
Stores **complete conversation history** for audit, analytics, and long-term retrieval.

### Tables

#### `conversations`
Stores conversation metadata and associations.

| Column         | Type         | Description                              |
|----------------|--------------|------------------------------------------|
| id             | UUID         | Primary key                              |
| user_id        | UUID         | Foreign key to users table               |
| created_at     | DATETIME     | Conversation start timestamp             |
| updated_at     | DATETIME     | Last message timestamp                   |
| is_active      | BOOLEAN      | Whether conversation is still open       |

#### `conversation_messages`
Stores individual messages within conversations.

| Column            | Type         | Description                              |
|-------------------|--------------|------------------------------------------|
| id                | UUID         | Primary key                              |
| conversation_id   | UUID         | Foreign key to conversations table       |
| role              | VARCHAR(50)  | Message role: 'user' or 'assistant'      |
| content           | TEXT         | Message content                          |
| created_at        | DATETIME     | Message timestamp                        |
| metadata          | JSON         | Optional metadata (tool calls, etc.)     |

### Repository

**File**: `src/Infrastructure/Repository/DoctrineConversationRepository.php`

**Key Methods**:
- `save(Conversation $conversation): void` - Persist conversation to database
- `findById(string $id): ?Conversation` - Retrieve conversation by UUID
- `findByUser(User $user): array` - Get all user conversations
- `findActiveForUser(User $user): ?Conversation` - Get active conversation
- `delete(Conversation $conversation): void` - Remove conversation

### Example Query

```sql
-- Get all messages for a specific user's conversation
SELECT 
    c.id AS conversation_id,
    c.created_at AS conversation_started,
    cm.role,
    cm.content,
    cm.created_at AS message_timestamp
FROM conversations c
INNER JOIN conversation_messages cm ON cm.conversation_id = c.id
WHERE c.user_id = '123e4567-e89b-12d3-a456-426614174000'
ORDER BY cm.created_at ASC;

-- Find conversations with unanswered questions (empty assistant responses)
SELECT DISTINCT c.id, c.created_at, u.email
FROM conversations c
INNER JOIN conversation_messages cm ON cm.conversation_id = c.id
INNER JOIN users u ON u.id = c.user_id
WHERE cm.role = 'assistant' 
  AND (cm.content = '' OR cm.content IS NULL)
ORDER BY c.created_at DESC;

-- Get conversation count by user (most active users)
SELECT 
    u.email,
    COUNT(DISTINCT c.id) AS total_conversations,
    COUNT(cm.id) AS total_messages
FROM users u
INNER JOIN conversations c ON c.user_id = u.id
INNER JOIN conversation_messages cm ON cm.conversation_id = c.id
GROUP BY u.id, u.email
ORDER BY total_conversations DESC
LIMIT 10;
```

---

## 2. Redis Storage (Temporary Context)

### Purpose
Stores **short-term conversational context** between sessions to maintain continuity without database hits.

### Key Structure

**Pattern**: `chat:customer:{userId}`

**Example**: `chat:customer:123e4567-e89b-12d3-a456-426614174000`

### Stored Data (Serialized PHP Object)

```php
CustomerConversationContext {
    userId: string (UUID)
    conversationId: ?string (UUID)
    turnCount: int
    lastInteractionAt: DateTime
    preferences: array
}
```

### Configuration

**TTL (Time To Live)**: Configurable via service definition (default: 1 hour)

**File**: `src/Application/Service/CustomerContextManager.php`

**Constructor Parameter**:
```php
public function __construct(
    private readonly CacheInterface $cache,
    private readonly int $ttl = 3600  // 1 hour default
) {}
```

### Key Methods

- `loadContext(string $userId): ?CustomerConversationContext` - Retrieve context from Redis
- `saveContext(CustomerConversationContext $context, bool $refreshTtl = true): void` - Persist context with TTL
- `getOrCreateContext(string $userId): CustomerConversationContext` - Get existing or initialize new
- `deleteContext(string $userId): bool` - Clear context (fresh start)
- `refreshTtl(string $userId): void` - Extend expiration on interaction

### Redis Commands for Debugging

```bash
# Connect to Redis container
docker exec -it <redis-container> redis-cli

# Check if user has context
EXISTS chat:customer:123e4567-e89b-12d3-a456-426614174000

# View raw serialized context
GET chat:customer:123e4567-e89b-12d3-a456-426614174000

# Check TTL (seconds remaining)
TTL chat:customer:123e4567-e89b-12d3-a456-426614174000

# List all customer contexts
KEYS chat:customer:*

# Clear specific user's context
DEL chat:customer:123e4567-e89b-12d3-a456-426614174000

# Clear all customer contexts (DANGEROUS - use carefully)
DEL chat:customer:*
```

---

## 3. Flow Diagram: How Conversations Are Saved

```
┌─────────────────────┐
│ User sends message  │
└──────────┬──────────┘
           │
           v
┌──────────────────────────────────────────────┐
│ ChatbotController::chat()                    │
│                                              │
│ Step 1: Validate authentication              │
│ Step 2: Load conversation from MySQL         │
│ Step 3: Load/create context from Redis       │
└──────────┬───────────────────────────────────┘
           │
           v
┌──────────────────────────────────────────────┐
│ Save user message to MySQL                   │
│ → ConversationManager::saveUserMessage()    │
└──────────┬───────────────────────────────────┘
           │
           v
┌──────────────────────────────────────────────┐
│ Build MessageBag with history                │
│ Call Symfony AI Agent                        │
└──────────┬───────────────────────────────────┘
           │
           v
┌──────────────────────────────────────────────┐
│ Save assistant response to MySQL             │
│ → ConversationManager::saveAssistantMessage()│
└──────────┬───────────────────────────────────┘
           │
           v
┌──────────────────────────────────────────────┐
│ Update context (turnCount++, timestamp)      │
│ Save context to Redis with refreshed TTL     │
│ → CustomerContextManager::saveContext()     │
└──────────┬───────────────────────────────────┘
           │
           v
┌──────────────────────────────────────────────┐
│ Return response to frontend                  │
└──────────────────────────────────────────────┘
```

---

## 4. Context Persistence Behavior

### Session 1 (Initial conversation)
1. User sends first message → New conversation created in MySQL
2. New context created in Redis with TTL
3. Each interaction refreshes Redis TTL
4. All messages saved to MySQL permanently

### Session 2 (Return within TTL window - e.g., 30 minutes later)
1. User returns and sends message
2. Context loaded from Redis (fast) → conversationId retrieved
3. Conversation history loaded from MySQL using conversationId
4. AI agent has full context from previous session ✅
5. Redis TTL refreshed again

### Session 3 (Return after TTL expiry - e.g., 2 hours later)
1. User returns and sends message
2. Context NOT found in Redis (expired)
3. New context created, but conversationId is null
4. **Starts new conversation** (no reference to previous conversation)
5. Previous conversation still exists in MySQL for audit

### How to Fix: Link Old Conversations on Return

**Option A**: Increase Redis TTL (e.g., 24 hours)
```yaml
# config/services.yaml
services:
    App\Application\Service\CustomerContextManager:
        arguments:
            $ttl: 86400  # 24 hours
```

**Option B**: Retrieve latest active conversation from MySQL if Redis context is missing
```php
// In CustomerContextManager::loadContext()
if ($context === null) {
    $activeConversation = $this->conversationRepository->findActiveForUser($user);
    if ($activeConversation) {
        $context = new CustomerConversationContext(
            userId: $user->getId(),
            conversationId: $activeConversation->getId()
        );
        $this->saveContext($context);
    }
}
```

---

## 5. API Endpoints

### POST `/api/chat`
**Purpose**: Send message to chatbot

**Request**:
```json
{
  "message": "Show me laptops for gaming",
  "conversationId": "optional-uuid-to-continue-conversation"
}
```

**Response**:
```json
{
  "response": "I found 3 gaming laptops...",
  "conversationId": "123e4567-e89b-12d3-a456-426614174000",
  "timestamp": "2026-02-07T10:30:00Z"
}
```

### GET `/api/chat/history/{conversationId}`
**Purpose**: Retrieve full conversation history

**Response**:
```json
{
  "success": true,
  "messages": [
    {
      "role": "user",
      "content": "Show me laptops",
      "timestamp": "2026-02-07T10:25:00Z"
    },
    {
      "role": "assistant", 
      "content": "I found 3 gaming laptops...",
      "timestamp": "2026-02-07T10:25:02Z"
    }
  ]
}
```

### POST `/api/chat/clear`
**Purpose**: Delete conversation from MySQL

**Request**:
```json
{
  "conversationId": "123e4567-e89b-12d3-a456-426614174000"
}
```

### POST `/api/chat/reset-context`
**Purpose**: Clear Redis context (fresh start, keeps MySQL history)

**Response**:
```json
{
  "success": true,
  "message": "Context reset successful. Starting fresh conversation."
}
```

---

## 6. Troubleshooting Guide

### Issue: User says context is lost between sessions

**Diagnostic Steps**:

1. **Check Redis TTL**:
   ```bash
   docker exec -it <redis-container> redis-cli
   TTL chat:customer:{userId}
   ```
   - If `-2`: Key doesn't exist (context expired or never created)
   - If `-1`: Key exists but has no expiration (shouldn't happen)
   - If `>0`: Key exists with N seconds remaining

2. **Check if context was saved**:
   ```bash
   EXISTS chat:customer:{userId}
   ```
   - If `0`: Context was never saved or already expired

3. **Check MySQL conversation history**:
   ```sql
   SELECT * FROM conversations WHERE user_id = '{userId}' ORDER BY created_at DESC;
   ```
   - If conversations exist: Data is saved, only Redis context expired
   - If no conversations: Issue with MySQL persistence

4. **Check ChatbotController logs**:
   ```bash
   docker logs <php-container> | grep "Context saved"
   ```

### Issue: Messages not appearing in history

**Check**:
1. Error in `conversation_messages` insert
2. Database connection issues
3. Check `var/log/dev.log` for Doctrine errors

### Issue: Context expires too quickly

**Solution**: Increase TTL in `services.yaml`
```yaml
services:
    App\Application\Service\CustomerContextManager:
        arguments:
            $ttl: 86400  # 24 hours instead of 1 hour
```

---

## 7. Performance Considerations

### Reads
- **Context load**: Redis (< 5ms) ✅ Fast
- **History load**: MySQL (10-50ms depending on message count)
- **Optimization**: Limit conversation history to last 20 messages in `ConversationManager::formatMessagesForAI()`

### Writes
- **Message save**: MySQL (10-20ms per message)
- **Context save**: Redis (< 5ms)
- **Total overhead per interaction**: ~30-50ms

### Caching Strategy
- Redis context avoids MySQL queries on every interaction
- Conversation history only loaded when needed (not on every message if messageBag already has history)

---

## 8. Security Considerations

### Access Control
- All endpoints validate `$this->security->getUser()` before access
- Users can only access their own conversations and contexts
- Admin users have no special access to customer conversations (by design)

### Data Retention
- **MySQL**: Conversations retained indefinitely (manual cleanup required if GDPR applies)
- **Redis**: Context auto-expires via TTL (no manual cleanup needed)

### Sensitive Data
- No passwords or payment information stored in conversations
- User email and name are in `users` table, not duplicated in messages
- Tool calls may contain product names and prices (non-sensitive)

---

## 9. Summary

| Aspect              | MySQL (Doctrine)                     | Redis                              |
|---------------------|--------------------------------------|------------------------------------|
| **Purpose**         | Permanent conversation history       | Temporary session context          |
| **Data Stored**     | All messages (user + assistant)      | Context metadata (conversationId, turnCount) |
| **Retention**       | Indefinite (until manually deleted)  | TTL-based expiration (configurable) |
| **Access Speed**    | ~10-50ms                             | ~1-5ms                             |
| **Use Case**        | Audit, analytics, history retrieval  | Fast context retrieval between sessions |
| **Managed By**      | ConversationManager                  | CustomerContextManager             |

**Key Insight**: The dual-storage architecture balances **performance** (Redis) with **durability** (MySQL). Context persistence relies on Redis TTL configuration; if TTL expires, the conversation is effectively "forgotten" by the AI until the user explicitly references a conversationId.

---

## 10. References

- **Feature Spec**: `specs/011-english-ai-assistants/spec.md`
- **ConversationManager**: `src/Application/Service/ConversationManager.php`
- **CustomerContextManager**: `src/Application/Service/CustomerContextManager.php`
- **ChatbotController**: `src/Infrastructure/Controller/ChatbotController.php`
- **Doctrine Repository**: `src/Infrastructure/Repository/DoctrineConversationRepository.php`

---

**Document Version**: 1.0  
**Last Updated**: February 7, 2026  
**Author**: Feature 011 Implementation Team
