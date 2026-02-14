# Phase 1: Data Model

**Feature**: User Embeddings Queue System  
**Date**: February 10, 2026  
**Status**: Complete

## Overview

This document defines the data models and entities used in the user embeddings queue system, including event messages, user embeddings, and supporting value objects.

---

## 1. User Interaction Event (MySQL)

**Purpose**: Source of truth for all user interactions, stored in MySQL for compliance, auditing, and replay capability.

**Entity**: `App\Entity\UserInteraction`

```php
class UserInteraction
{
    private ?int $id;
    private int $userId;               // Foreign key to User entity
    private string $eventType;         // ENUM: 'search', 'product_view', 'product_click', 'product_purchase'
    private ?string $searchPhrase;     // Populated for 'search' events
    private ?int $productId;           // Populated for product events, FK to Product
    private DateTimeImmutable $occurredAt;
    private array $metadata;           // JSON: {device, channel, category, etc.}
    private DateTimeImmutable $createdAt;
    private bool $processedToQueue;   // Flag: has event been published to RabbitMQ?
}
```

**MySQL Table**: `user_interactions`

| Column | Type | Constraints | Description |
|--------|------|-------------|-------------|
| id | INT AUTO_INCREMENT | PRIMARY KEY | Unique identifier |
| user_id | INT | NOT NULL, INDEX, FK(users.id) | User performing action |
| event_type | ENUM | NOT NULL, INDEX | 'search', 'product_view', 'product_click', 'product_purchase' |
| search_phrase | VARCHAR(255) | NULL | Search query text (for search events) |
| product_id | INT | NULL, INDEX, FK(products.id) | Product reference (for product events) |
| occurred_at | DATETIME | NOT NULL, INDEX | Exact timestamp of interaction |
| metadata | JSON | NULL | Additional context: {"device": "mobile", "channel": "app"} |
| created_at | DATETIME | NOT NULL | Record creation time |
| processed_to_queue | BOOLEAN | DEFAULT false, INDEX | Has message been published to RabbitMQ? |

**Indexes**:
- `idx_user_occurred` (user_id, occurred_at DESC) - for user timeline queries
- `idx_event_type` (event_type) - for analytics
- `idx_processed` (processed_to_queue, occurred_at) - for replay queries

**Business Rules**:
- `search_phrase` required if `event_type = 'search'`, otherwise NULL
- `product_id` required if `event_type` starts with 'product_', otherwise NULL
- `occurred_at` must be ≤ current timestamp (no future events)
- `processed_to_queue` set to true after successful RabbitMQ publish

---

## 2. User Embedding (MongoDB)

**Purpose**: Semantic vector representation of user's interests and behavior, updated asynchronously by workers.

**Collection**: `user_embeddings`

```json
{
  "_id": ObjectId("..."),
  "user_id": 12345,
  "embedding": [0.123, -0.456, 0.789, ...],  // 1536 dimensions (OpenAI)
  "dimension_count": 1536,
  "last_updated": ISODate("2026-02-10T14:30:00Z"),
  "event_count": 47,
  "last_event_type": "product_purchase",
  "created_at": ISODate("2026-01-15T09:00:00Z"),
  "updated_at": ISODate("2026-02-10T14:30:00Z"),
  "version": 12
}
```

**Schema Validation**:
```javascript
{
  $jsonSchema: {
    bsonType: "object",
    required: ["user_id", "embedding", "dimension_count", "last_updated"],
    properties: {
      user_id: {
        bsonType: "int",
        description: "User ID (references MySQL users table)"
      },
      embedding: {
        bsonType: "array",
        minItems: 1536,
        maxItems: 1536,
        items: { bsonType: "double" },
        description: "1536-dimensional embedding vector"
      },
      dimension_count: {
        bsonType: "int",
        enum: [1536],
        description: "Vector dimensionality (must match OpenAI model)"
      },
      last_updated: {
        bsonType: "date",
        description: "Timestamp of most recent embedding update"
      },
      event_count: {
        bsonType: "int",
        minimum: 0,
        description: "Total number of events incorporated"
      },
      last_event_type: {
        bsonType: "string",
        enum: ["search", "product_view", "product_click", "product_purchase"],
        description: "Type of most recent event"
      },
      version: {
        bsonType: "int",
        minimum: 1,
        description: "Optimistic locking version number"
      }
    }
  }
}
```

**Indexes**:
- Unique index on `user_id`
- Index on `last_updated` (for stale embedding detection)
- Vector search index on `embedding` (for similarity queries - future use)

**Business Rules**:
- New user: initialize embedding from first event (no prior state)
- Embedding must be L2-normalized (unit vector) after each update
- `version` incremented on every update (optimistic locking)
- `event_count` tracks total events processed (debugging/analytics)

---

## 3. Queue Message: UpdateUserEmbeddingMessage

**Purpose**: Async message published to RabbitMQ, consumed by workers to update user embeddings.

**Class**: `App\Application\Message\UpdateUserEmbeddingMessage`

```php
final readonly class UpdateUserEmbeddingMessage implements AsyncMessageInterface
{
    public function __construct(
        public int $userId,
        public EventType $eventType,        // Value object enum
        public ?string $searchPhrase,
        public ?int $productId,
        public DateTimeImmutable $occurredAt,
        public array $metadata,
        public string $messageId             // SHA-256 hash for idempotency
    ) {}
}
```

**JSON Serialization** (for RabbitMQ AMQP body):
```json
{
  "user_id": 12345,
  "event_type": "product_purchase",
  "search_phrase": null,
  "product_id": 789,
  "occurred_at": "2026-02-10T14:30:00+00:00",
  "metadata": {
    "device": "mobile",
    "channel": "app",
    "category": "electronics"
  },
  "message_id": "abc123...",
  "version": "1.0"
}
```

**Message Headers** (AMQP):
```yaml
content_type: application/json
content_encoding: utf-8
delivery_mode: 2           # Persistent
priority: 5                # Normal priority (0-9 scale)
timestamp: 1707574200      # Unix timestamp
app_id: myshop-api
message_id: abc123...      # Idempotency key
```

**Routing**:
- **Exchange**: `user_events` (topic exchange)
- **Routing Key**: `user.embedding.{event_type}` (e.g., `user.embedding.product_purchase`)
- **Queue**: `user_embedding_updates` (binds to `user.embedding.*`)

**Business Rules**:
- `message_id` = SHA256(user_id + event_type + reference + occurred_at ISO8601)
- `search_phrase` XOR `product_id` must be set (based on event_type)
- `occurred_at` in ISO 8601 format with timezone
- `version` field for future schema evolution

---

## 4. Value Object: EventType

**Purpose**: Type-safe enumeration for user interaction types with associated weights.

**Class**: `App\Domain\ValueObject\EventType`

```php
enum EventType: string
{
    case SEARCH = 'search';
    case PRODUCT_VIEW = 'product_view';
    case PRODUCT_CLICK = 'product_click';
    case PRODUCT_PURCHASE = 'product_purchase';

    public function weight(): float
    {
        return match($this) {
            self::PRODUCT_PURCHASE => 1.0,
            self::SEARCH => 0.7,
            self::PRODUCT_CLICK => 0.5,
            self::PRODUCT_VIEW => 0.3,
        };
    }

    public function requiresProduct(): bool
    {
        return match($this) {
            self::PRODUCT_VIEW, self::PRODUCT_CLICK, self::PRODUCT_PURCHASE => true,
            self::SEARCH => false,
        };
    }

    public function requiresSearchPhrase(): bool
    {
        return $this === self::SEARCH;
    }
}
```

**Rationale**:
- **Type Safety**: Compile-time validation, no invalid event types
- **Weight Logic**: Centralized business rule for event weighting
- **Validation Helpers**: Enforce message structure constraints

---

## 5. Value Object: UserEmbedding

**Purpose**: Domain value object encapsulating user embedding vector with operations.

**Class**: `App\Domain\ValueObject\UserEmbedding`

```php
final readonly class UserEmbedding
{
    private const DIMENSIONS = 1536;

    public function __construct(
        public array $vector,                    // float[], length = 1536
        public DateTimeImmutable $lastUpdated,
        public int $eventCount = 0
    ) {
        if (count($vector) !== self::DIMENSIONS) {
            throw new InvalidArgumentException('Embedding must be 1536 dimensions');
        }
    }

    public static function fromEventEmbedding(array $eventVector, DateTimeImmutable $timestamp): self
    {
        return new self(
            vector: self::normalize($eventVector),
            lastUpdated: $timestamp,
            eventCount: 1
        );
    }

    public function updateWith(
        array $eventVector,
        float $eventWeight,
        DateTimeImmutable $eventTimestamp,
        float $decayLambda = 0.023
    ): self {
        $daysSinceLastUpdate = $eventTimestamp->diff($this->lastUpdated)->days;
        $decayFactor = exp(-$decayLambda * $daysSinceLastUpdate);

        $newVector = [];
        for ($i = 0; $i < self::DIMENSIONS; $i++) {
            $newVector[$i] = (
                $this->vector[$i] * $decayFactor +
                $eventVector[$i] * $eventWeight
            ) / ($decayFactor + $eventWeight);
        }

        return new self(
            vector: self::normalize($newVector),
            lastUpdated: $eventTimestamp,
            eventCount: $this->eventCount + 1
        );
    }

    private static function normalize(array $vector): array
    {
        $magnitude = sqrt(array_sum(array_map(fn($v) => $v ** 2, $vector)));
        return array_map(fn($v) => $v / $magnitude, $vector);
    }

    public function toArray(): array
    {
        return $this->vector;
    }
}
```

**Mathematical Properties**:
- **Normalization**: All vectors L2-normalized (unit length) for cosine similarity
- **Temporal Decay**: Exponential decay with configurable lambda
- **Immutability**: Value object never mutates, returns new instance

---

## 6. Product Embedding (MongoDB) - Existing

**Purpose**: Pre-computed product embeddings (from spec-010 semantic search).

**Collection**: `product_embeddings`

```json
{
  "_id": ObjectId("..."),
  "product_id": 789,
  "embedding": [0.234, -0.567, 0.890, ...],  // 1536 dimensions
  "dimension_count": 1536,
  "product_name": "Wireless Bluetooth Headphones",
  "category": "Electronics > Audio",
  "created_at": ISODate("2026-01-20T10:00:00Z"),
  "updated_at": ISODate("2026-01-20T10:00:00Z")
}
```

**Usage in This Feature**:
- **Read-Only**: Workers retrieve product embeddings, never write
- **Lookup**: By `product_id` when processing product events
- **Integration Point**: Assumes embeddings already exist (from product sync)

---

## 7. Failed Message (Dead-Letter Queue)

**Purpose**: Messages that failed processing after exhausting retries.

**Queue**: `user_embedding_updates_failed`

**Message Structure** (augmented original):
```json
{
  "original_message": { /* ... original UpdateUserEmbeddingMessage ... */ },
  "failure_info": {
    "retry_count": 5,
    "first_failure_at": "2026-02-10T14:31:00+00:00",
    "last_failure_at": "2026-02-10T14:35:00+00:00",
    "error_messages": [
      "MongoConnectionException: Connection timeout",
      "MongoConnectionException: Connection timeout",
      "MongoServerException: Replica set not available"
    ],
    "worker_host": "worker-pod-3"
  }
}
```

**Metadata Headers**:
- `x-death` (RabbitMQ built-in): Array of death records with timestamps and reasons
- `x-first-death-reason`: Root cause of first failure
- `x-first-death-exchange`: Original exchange name

**Business Rules**:
- Messages routed to DLQ after 5 failed attempts
- Manual review required before replay
- DLQ monitoring alert if depth > 10

---

## Entity Relationships

```
┌─────────────────┐
│  User (MySQL)   │
└────────┬────────┘
         │ 1
         │
         │ N
┌────────▼──────────────────┐
│ UserInteraction (MySQL)   │
│ - id                      │
│ - user_id (FK)            │
│ - event_type              │
│ - search_phrase           │
│ - product_id (FK)         │
│ - occurred_at             │
│ - metadata                │
│ - processed_to_queue      │
└────────┬──────────────────┘
         │
         │ [publishes]
         ▼
┌────────────────────────────┐
│ RabbitMQ Queue             │
│ user_embedding_updates     │
└────────┬───────────────────┘
         │
         │ [consumes]
         ▼
┌────────────────────────────┐
│ Worker Process             │
│ UpdateUserEmbeddingHandler │
└────────┬───────────────────┘
         │
         │ [reads]          [writes]
         ▼                      │
┌──────────────────┐           │
│ ProductEmbedding │           │
│ (MongoDB)        │           │
│ - product_id     │           │
│ - embedding      │           │
└──────────────────┘           │
                               ▼
                    ┌────────────────────┐
                    │ UserEmbedding      │
                    │ (MongoDB)          │
                    │ - user_id          │
                    │ - embedding        │
                    │ - last_updated     │
                    │ - event_count      │
                    └────────────────────┘
```

---

## Data Flow Sequence

1. **Event Creation**:
   ```
   User Action → Controller → UserInteraction inserted to MySQL
   ```

2. **Queue Publishing**:
   ```
   Doctrine PostPersist Event → RabbitMQPublisher
   → UpdateUserEmbeddingMessage serialized to JSON
   → Published to RabbitMQ user_embedding_updates queue
   → UserInteraction.processed_to_queue = true
   ```

3. **Worker Consumption**:
   ```
   Worker polls RabbitMQ → UpdateUserEmbeddingHandler receives message
   → Validate message structure
   → Generate/retrieve event embedding (search phrase or product)
   → Fetch current UserEmbedding from MongoDB (or create new)
   → Calculate updated embedding (weighted + decay)
   → Persist to MongoDB with optimistic locking
   → Acknowledge message (deleted from queue)
   ```

4. **Failure Handling**:
   ```
   Worker encounters error (MongoDB down)
   → Exception thrown, message NOT acknowledged
   → RabbitMQ redelivers message after delay (retry#1)
   → Retry 5 times with exponential backoff
   → After 5 failures: route to user_embedding_updates_failed DLQ
   → Alert triggered, manual review required
   ```

---

## Configuration Schema

**Environment Variables** (`.env`):
```bash
# RabbitMQ Connection
RABBITMQ_DSN=amqp://guest:guest@rabbitmq:5672/%2F

# Embedding Configuration
EMBEDDING_DECAY_LAMBDA=0.023           # 30-day half-life
EMBEDDING_BATCH_ENABLED=false          # Enable time-window batching
EMBEDDING_BATCH_WINDOW=5               # Seconds to batch events per user
EMBEDDING_CACHE_TTL=3600               # Redis cache TTL for search embeddings

# Worker Configuration
WORKER_MAX_RETRIES=5                   # Retries before DLQ
WORKER_RETRY_DELAY=5000                # Initial delay in ms (exponential backoff)
WORKER_MEMORY_LIMIT=512M               # PHP memory limit per worker
```

**Symfony Messenger Config** (`config/packages/messenger.yaml`):
```yaml
framework:
    messenger:
        transports:
            user_embedding_updates:
                dsn: '%env(RABBITMQ_DSN)%'
                options:
                    exchange:
                        name: user_events
                        type: topic
                    queues:
                        user_embedding_updates:
                            binding_keys: ['user.embedding.*']
                            arguments:
                                x-max-priority: 10
                retry_strategy:
                    max_retries: 5
                    delay: 5000
                    multiplier: 2
                    max_delay: 60000
            failed:
                dsn: '%env(RABBITMQ_DSN)%'
                options:
                    exchange:
                        name: 'failed'
                        type: direct
                    queues:
                        failed:
                            binding_keys: ['failed']
```

---

## Migration Strategy

### Phase 1: Schema Creation

1. **MySQL Migration**:
   ```sql
   CREATE TABLE user_interactions (
       id INT AUTO_INCREMENT PRIMARY KEY,
       user_id INT NOT NULL,
       event_type ENUM('search', 'product_view', 'product_click', 'product_purchase'),
       search_phrase VARCHAR(255),
       product_id INT,
       occurred_at DATETIME NOT NULL,
       metadata JSON,
       created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
       processed_to_queue BOOLEAN DEFAULT FALSE,
       INDEX idx_user_occurred (user_id, occurred_at DESC),
       INDEX idx_processed (processed_to_queue, occurred_at),
       FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
       FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
   );
   ```

2. **MongoDB Collections**:
   ```javascript
   db.createCollection("user_embeddings", {
       validator: { /* ... JSON schema from above ... */ }
   });
   db.user_embeddings.createIndex({user_id: 1}, {unique: true});
   db.user_embeddings.createIndex({last_updated: 1});
   ```

### Phase 2: Backfill Historical Data (Optional)

```php
// Command: bin/console app:backfill-user-embeddings
// Reads historical interactions from MySQL, publishes to queue for processing
```

---

## Summary

This data model supports:
- ✅ **FR-001 to FR-003**: Event storage in MySQL with queue flag
- ✅ **FR-004 to FR-012**: Message structure and worker processing
- ✅ **FR-013**: Idempotency via message_id and MongoDB upsert
- ✅ **FR-014 to FR-016**: Retry, DLQ, and error logging
- ✅ **SC-001 to SC-008**: Performance, scalability, and reliability requirements

**Phase 1 Data Model Status**: ✅ **Complete** - Ready for contract definition and quickstart guide.
