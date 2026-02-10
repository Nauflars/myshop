# Quick Start Guide: User Embeddings Queue System

**Feature**: 014-user-embeddings-queue  
**Audience**: Developers setting up and testing the system locally  
**Prerequisites**: Docker, Docker Compose, PHP 8.3+, Composer

---

## Table of Contents

1. [System Overview](#system-overview)
2. [Local Setup](#local-setup)
3. [Testing the Flow](#testing-the-flow)
4. [Monitoring & Debugging](#monitoring--debugging)
5. [Common Issues](#common-issues)
6. [Development Workflows](#development-workflows)

---

## System Overview

```
User Action (API)
    │
    ├─► MySQL (user_interactions table) ─────────┐
    │                                             │
    └─► RabbitMQ (user_embedding_updates queue) ─┤
                                                  │
                                                  ▼
                                         Worker Container
                                         (3 instances)
                                                  │
                                         ┌────────┴────────┐
                                         │                 │
                                         ▼                 ▼
                                    MongoDB          OpenAI API
                              (user_embeddings)   (search phrase
                                                    embeddings)
```

**Event Flow**:
1. User performs action (search, view, click, purchase)
2. API saves event to MySQL and publishes message to RabbitMQ
3. Worker consumes message from queue
4. Worker generates/retrieves embedding
5. Worker calculates updated user embedding
6. Worker persists to MongoDB
7. Message acknowledged (removed from queue)

---

## Local Setup

### Step 1: Start Infrastructure

```bash

# Navigate to project root
cd //wsl.localhost/Ubuntu-20.04/var/www2/myshop

# Start all services (includes RabbitMQ)
docker-compose up -d

# Verify services are running
docker-compose ps

# Expected output:
# myshop_php           Up
# myshop_nginx         Up
# myshop_mysql         Up
# myshop_mongodb       Up
# myshop_redis         Up
# myshop_rabbitmq      Up  0.0.0.0:5672->5672/tcp, 0.0.0.0:15672->15672/tcp
# myshop_worker_1      Up
# myshop_worker_2      Up
# myshop_worker_3      Up
```

### Step 2: Install Dependencies

```bash
# Install PHP dependencies (if not already done)
docker-compose exec php composer install

# Verify Symfony Messenger bundle
docker-compose exec php bin/console debug:config framework messenger
```

### Step 3: Run Database Migrations

**MySQL (event log table)**:
```bash
docker-compose exec php bin/console doctrine:migrations:migrate --no-interaction

# Verify table exists
docker-compose exec mysql mysql -u root -prootpassword myshop -e "DESCRIBE user_interactions;"
```

**MongoDB (user embeddings collection)**:
```bash
# Run MongoDB schema setup command
docker-compose exec php bin/console app:setup-mongodb-embeddings

# Verify collection exists
docker-compose exec mongodb mongosh -u root -p rootpassword --eval "use myshop; db.user_embeddings.findOne();"
```

### Step 4: Configure Environment Variables

Edit `.env.local` (create if doesn't exist):

```bash
# RabbitMQ Configuration
RABBITMQ_DSN=amqp://guest:guest@rabbitmq:5672/%2F

# OpenAI API Key (required for search embeddings)
OPENAI_API_KEY=sk-your-api-key-here
OPENAI_EMBEDDING_MODEL=text-embedding-3-small

# Embedding Configuration
EMBEDDING_DECAY_LAMBDA=0.023         # 30-day half-life
EMBEDDING_BATCH_ENABLED=false        # Disable batching initially
EMBEDDING_BATCH_WINDOW=5             # 5-second batch window
EMBEDDING_CACHE_TTL=3600             # 1-hour cache for search embeddings

# Worker Configuration
WORKER_MAX_RETRIES=5
WORKER_RETRY_DELAY=5000              # 5 seconds initial delay
WORKER_MEMORY_LIMIT=512M

# MongoDB Configuration (if not already set)
MONGODB_URL=mongodb://root:rootpassword@mongodb:27017
MONGODB_DATABASE=myshop
```

### Step 5: Start Workers

```bash
# Option A: Start workers via Docker Compose (recommended)
docker-compose up -d worker

# Option B: Start workers manually (for debugging)
docker-compose exec php bin/console messenger:consume user_embedding_updates -vv --time-limit=3600
```

### Step 6: Verify Setup

**Check RabbitMQ**:
```bash
# Access management UI: http://localhost:15672
# Login: guest / guest
# Navigate to Queues → should see "user_embedding_updates"
```

**Check Worker Logs**:
```bash
# View worker logs
docker-compose logs -f worker

# Expected output:
# [INFO] Worker started consuming from user_embedding_updates
# [INFO] Waiting for messages...
```

---

## Testing the Flow

### Manual Test 1: Search Event

**Trigger a search**:
```bash
# Via API (simulate user search)
curl -X POST http://localhost/api/user/12345/search \
  -H "Content-Type: application/json" \
  -d '{"query": "wireless bluetooth headphones", "device": "desktop"}'

# Expected response: HTTP 201
```

**Verify MySQL**:
```bash
docker-compose exec mysql mysql -u root -prootpassword myshop -e "
  SELECT id, user_id, event_type, search_phrase, occurred_at, processed_to_queue 
  FROM user_interactions 
  ORDER BY id DESC 
  LIMIT 1;
"

# Expected: New row with processed_to_queue = 1
```

**Verify RabbitMQ**:
```bash
# Check queue depth (should increment temporarily)
curl -u guest:guest http://localhost:15672/api/queues/%2F/user_embedding_updates | jq '.messages'

# Should show 1 message (or 0 if already processed)
```

**Verify MongoDB**:
```bash
docker-compose exec mongodb mongosh -u root -p rootpassword <<EOF
use myshop;
db.user_embeddings.findOne({user_id: 12345});
EOF

# Expected: Document with embedding array (1536 dimensions), event_count: 1
```

**Verify Worker Logs**:
```bash
docker-compose logs worker | grep "user_id.*12345"

# Expected output:
# [INFO] Processing message for user_id: 12345, event_type: search
# [INFO] Generated embedding for search phrase: "wireless bluetooth headphones"
# [INFO] Updated user embedding, event_count: 1, duration: 245ms
```

---

### Manual Test 2: Product Purchase Event

**Trigger a purchase**:
```bash
curl -X POST http://localhost/api/user/12345/product/456/purchase \
  -H "Content-Type: application/json" \
  -d '{"device": "mobile", "channel": "app"}'
```

**Verify Product Embedding Exists** (prerequisite):
```bash
docker-compose exec mongodb mongosh -u root -p rootpassword <<EOF
use myshop;
db.product_embeddings.findOne({product_id: 456});
EOF

# If not found, product embeddings need to be synced first (spec-010)
```

**Verify User Embedding Updated**:
```bash
docker-compose exec mongodb mongosh -u root -p rootpassword <<EOF
use myshop;
db.user_embeddings.findOne(
  {user_id: 12345},
  {event_count: 1, last_event_type: 1, "metadata.purchase_count": 1}
);
EOF

# Expected: event_count: 2, last_event_type: "product_purchase", purchase_count: 1
```

---

### Automated Test Suite

**Run Unit Tests**:
```bash
docker-compose exec php vendor/bin/phpunit tests/Unit/Application/UseCase/CalculateUserEmbeddingTest.php

# Expected: All tests pass
```

**Run Integration Tests**:
```bash
# Start test environment (separate RabbitMQ)
docker-compose -f docker-compose.test.yml up -d

# Run integration tests
docker-compose exec php vendor/bin/phpunit tests/Integration/Queue/

# Tests:
# - Publish message → consume → verify MongoDB update
# - Verify idempotency (duplicate messages)
# - Verify temporal decay calculation
# - Verify optimistic locking
```

**Run Contract Tests**:
```bash
docker-compose exec php vendor/bin/phpunit tests/Contract/EventMessageFormatTest.php

# Validates:
# - Message schema compliance
# - Serialization/deserialization
# - Message ID generation
```

---

## Monitoring & Debugging

### RabbitMQ Management UI

**Access**: http://localhost:15672  
**Login**: guest / guest

**Key Metrics**:
- **Queue Depth**: `user_embedding_updates` → Messages Ready
- **Publish Rate**: Overview → Message rates
- **Consumer Count**: Queues → `user_embedding_updates` → Consumers (should be 3)
- **Failed Messages**: Queues → `messenger_failed_messages` (DLQ)

**Debugging**:
- Click "Get Messages" → Manual message inspection
- View message payload and headers
- Check `x-death` headers for failure reasons

**RabbitMQ API Queries** (T068):
```bash
# Queue depth and rates
curl -s -u guest:guest http://localhost:15672/api/queues/%2F/user_embedding_updates \
  | jq '{messages: .messages, ready: .messages_ready, unacknowledged: .messages_unacknowledged, publish_rate: .message_stats.publish_details.rate, consume_rate: .message_stats.deliver_get_details.rate}'

# Consumer details
curl -s -u guest:guest http://localhost:15672/api/queues/%2F/user_embedding_updates \
  | jq '.consumers'

# Failed messages (DLQ)
curl -s -u guest:guest http://localhost:15672/api/queues/%2F/messenger_failed_messages \
  | jq '{messages: .messages, message_rate: .message_stats.publish_details.rate}'

# All queues overview
curl -s -u guest:guest http://localhost:15672/api/queues \
  | jq '.[] | {name: .name, messages: .messages, consumers: .consumers}'
```

---

### Worker Logs

**Real-Time Logs**:
```bash
# All workers
docker-compose logs -f worker

# Specific worker
docker-compose logs -f worker_1

# Filter by user
docker-compose logs worker | grep "user_id.*12345"

# Filter errors only
docker-compose logs worker | grep ERROR
```

**Structured Log Example**:
```json
{
  "timestamp": "2026-02-10T14:30:00+00:00",
  "level": "info",
  "message": "User embedding updated",
  "context": {
    "user_id": 12345,
    "event_type": "search",
    "message_id": "abc123...",
    "event_count": 47,
    "duration_ms": 245,
    "decay_days": 2
  }
}
```

**Worker Log Queries** (T070):
```bash
# Find slow message processing (>1000ms)
docker-compose logs worker 2>&1 | grep -oP '"processing_time_ms":\s*\K[0-9.]+' | awk '$1 > 1000'

# Count messages by event type
docker-compose logs worker 2>&1 | grep -oP '"event_type":\s*"\K[^"]+' | sort | uniq -c

# Find failed messages
docker-compose logs worker 2>&1 | grep -E 'error|ERROR|Failed' | tail -20

# Track user activity
docker-compose logs worker 2>&1 | grep '"user_id":12345' | tail -10

# Monitor idempotency cache hits
docker-compose logs worker 2>&1 | grep "already processed"

# Check temporal decay application
docker-compose logs worker 2>&1 | grep "days_since_last_update"
```

---

### MongoDB Queries

**Check User Embedding**:
```javascript
use myshop;

// Find specific user
db.user_embeddings.findOne({user_id: 12345});

//MongoDB Statistics Queries** (T069):
```javascript
// Embedding age distribution
db.user_embeddings.aggregate([
  {$project: {
    user_id: 1,
    days_old: {
      $divide: [
        {$subtract: [new Date(), "$last_updated"]},
        1000 * 60 * 60 * 24
      ]
    }
  }},
  {$bucket: {
    groupBy: "$days_old",
    boundaries: [0, 7, 30, 90, 365, Infinity],
    default: "ancient",
    output: {count: {$sum: 1}}
  }}
]);

// Users by version (detect optimistic locking frequency)
db.user_embeddings.aggregate([
  {$group: {
    _id: "$version",
    count: {$sum: 1}
  }},
  {$sort: {_id: 1}}
]);

// Top active users (most events)
db.user_embeddings.find().sort({event_count: -1}).limit(10);

// Embedding update frequency (last 24h)
db.user_embeddings.find({
  last_updated: {$gte: new Date(Date.now() - 24*60*60*1000)}
}).count();

// Check for corrupted embeddings (wrong dimensions)
db.user_embeddings.aggregate([
  {$project: {
    user_id: 1,
    embedding_size: {$size: "$embedding"}
  }},
  {$match: {embedding_size: {$ne: 1536}}}
]);
```

**Verify Embedding Normalization**:
```javascript
// Check if embedding is normalized (magnitude ≈ 1.0)
db.user_embeddings.aggregate([
  {
    $project: {
      user_id: 1,
      magnitude: {
        $sqrt: {
          $sum: {
            $map: {
              input: "$embedding",
              as: "val",
              in: {$multiply: ["$$val", "$$val"]}
            }
          }
        }
      }
    }
  },
  {$match: {magnitude: {$lt: 0.99, $gt: 1.01}}}  // Find non-normalized
]);
```erify Embedding Normalization**:
```javascript
// Check if embedding is normalized (magnitude ≈ 1.0)
db.user_embeddings.aggregate([
  {
    $project: {
      user_id: 1,
      magnitude: {
        $sqrt: {
          $sum: {
            $map: {
              input: "$embedding",
              as: "val",
              in: {$multiply: ["$$val", "$$val"]}
            }
          }
        }
      }
    }
  },
  {$match: {magnitude: {$lt: 0.99, $gt: 1.01}}}  // Find non-normalized
]);
```

---

### MySQL Event Log Queries

**Recent Events**:
```sql
-- Last 10 events
SELECT id, user_id, event_type, search_phrase, product_id, occurred_at, processed_to_queue
FROM user_interactions
ORDER BY id DESC
LIMIT 10;

-- Unprocessed events (queue publishing failed)
SELECT COUNT(*) FROM user_interactions WHERE processed_to_queue = 0;

-- Event type distribution
SELECT event_type, COUNT(*) as count
FROM user_interactions
GROUP BY event_type;
```

---

### Performance Metrics

**Queue Processing Rate**:
```bash
# Check queue depth over time
watch -n 5 'curl -s -u guest:guest http://localhost:15672/api/queues/%2F/user_embedding_updates | jq "{messages: .messages, rate: .messages_details.rate}"'
```

**Worker Performance**:
```bash
# Extract processing times from logs
docker-compose logs worker | grep "duration_ms" | awk -F'duration_ms.:' '{print $2}' | awk '{print $1}' | sort -n
```

---

## Common Issues

### Issue 1: Messages Not Being Consumed

**Symptoms**: Queue depth keeps growing, workers idle

**Diagnosis**:
```bash
# Check if workers are running
docker-compose ps worker

# Check worker logs for errors
docker-compose logs worker | tail -50
```

**Solutions**:
- **Workers not started**: `docker-compose up -d worker`
- **Connection error**: Verify `RABBITMQ_DSN` in `.env.local`
- **Worker crashed**: Check memory limit, restart workers
- **Queue binding wrong**: Verify queue exists and bindings are correct

---

### Issue 2: OpenAI API Failures

**Symptoms**: Search events fail, DLQ fills up

**Diagnosis**:
```bash
docker-compose logs worker | grep "OpenAI"
```

**Solutions**:
- **API key invalid**: Verify `OPENAI_API_KEY` in `.env.local`
- **Rate limit exceeded**: Implement exponential backoff or reduce search events
- **API outage**: Workers should log warning and skip embedding update (not fail message)

---

### Issue 3: MongoDB Connection Timeouts

**Symptoms**: Messages fail repeatedly, go to DLQ

**Diagnosis**:
```bash
# Check MongoDB is running
docker-compose ps mongodb

# Test connection
docker-compose exec php bin/console doctrine:mongodb:schema:validate
```

**Solutions**:
- **MongoDB down**: `docker-compose up -d mongodb`
- **Connection limit**: Increase MongoDB connection pool size
- **Slow queries**: Add indexes on `user_id`, `last_updated`

---

### Issue 4: Duplicate Messages Processed

**Symptoms**: `event_count` increases faster than expected

**Diagnosis**:
```bash
# Check for duplicate message IDs in logs
docker-compose logs worker | grep "message_id" | sort | uniq -d
```

**Solutions**:
- **Idempotency not working**: Verify `message_id` generation is deterministic
- **Worker didn't ack**: Check for worker crashes before acknowledgment
- **Clock skew**: Ensure all containers have synchronized time

---

### Issue 5: Embedding Not Updated

**Symptoms**: User embedding `last_updated` is old despite new events

**Diagnosis**:
```sql
-- Check if events are in MySQL
SELECT * FROM user_interactions WHERE user_id = 12345 ORDER BY id DESC LIMIT 5;

-- Check processed_to_queue flag
SELECT processed_to_queue, COUNT(*) FROM user_interactions GROUP BY processed_to_queue;
```

**Solutions**:
- **Events not published**: Check Doctrine listener is registered
- **Message validation failed**: Check worker logs for schema errors
- **Product embedding missing**: Sync product embeddings first (spec-010)

---

## Development Workflows

### Workflow 1: Add New Event Type

**1. Update EventType Enum**:
```php
// src/Domain/ValueObject/EventType.php
enum EventType: string
{
    // ... existing types ...
    case PRODUCT_WISHLIST = 'product_wishlist';
    
    public function weight(): float
    {
        return match($this) {
            // ... existing weights ...
            self::PRODUCT_WISHLIST => 0.4,
        };
    }
}
```

**2. Update MySQL Schema**:
```sql
ALTER TABLE user_interactions
MODIFY COLUMN event_type ENUM(
  'search', 'product_view', 'product_click', 'product_purchase', 'product_wishlist'
);
```

**3. Update Message Schema**:
- Edit `contracts/event-message.yaml`
- Add `product_wishlist` to enum
- Update validation rules

**4. Update Tests**:
- Add test cases for new event type
- Verify weighting is applied correctly

---

### Workflow 2: Replay Failed Messages

**Scenario**: MongoDB was down, 50 messages in DLQ

**1. Verify Issue Resolved**:
```bash
docker-compose ps mongodb  # Should be Up
```

**2. Replay from DLQ**:
```bash
# Replay all failed messages
docker-compose exec php bin/console messenger:failed:retry --force

# Replay specific message
docker-compose exec php bin/console messenger:failed:show
docker-compose exec php bin/console messenger:failed:retry 123  # Message ID
```

**3. Monitor Progress**:
```bash
# Watch DLQ depth decrease
watch -n 2 'docker-compose exec php bin/console messenger:failed:show | grep total'
```

---

### Workflow 3: Manual Recalculation

**Scenario**: Want to recalculate user embedding from scratch (e.g., changed decay lambda)

**1. Delete Existing Embedding**:
```bash
docker-compose exec mongodb mongosh -u root -p rootpassword <<EOF
use myshop;
db.user_embeddings.deleteOne({user_id: 12345});
EOF
```

**2. Replay Events from MySQL**:
```bash
# Create command: bin/console app:recalculate-user-embedding 12345
docker-compose exec php bin/console app:recalculate-user-embedding 12345

# Or replay all events for user
docker-compose exec php bin/console app:replay-user-events 12345
```

**3. Verify New Embedding**:
```bash
docker-compose exec mongodb mongosh -u root -p rootpassword <<EOF
use myshop;
db.user_embeddings.findOne({user_id: 12345});
EOF
```

---

### Workflow 4: Load Testing

**1. Generate Test Events**:
```bash
# Create load test script
docker-compose exec php bin/console app:generate-test-events --users=100 --events-per-user=50

# Expected: 5000 events created, messages published to RabbitMQ
```

**2. Monitor Queue Depth**:
```bash
watch -n 1 'curl -s -u guest:guest http://localhost:15672/api/queues/%2F/user_embedding_updates | jq ".messages"'
```

**3. Monitor Worker Performance**:
```bash
docker-compose logs -f worker | grep "duration_ms"
```

**4. Verify SLA**:
```bash
# Check if embeddings updated within 30 seconds
docker-compose exec mongodb mongosh -u root -p rootpassword <<EOF
use myshop;
db.user_embeddings.find({
  last_updated: {\$gt: ISODate("$(date -u -d '30 seconds ago' '+%Y-%m-%dT%H:%M:%SZ')")}
}).count();
EOF
```

---

## Next Steps

After completing this quickstart:

1. **Review [data-model.md](data-model.md)** for entity details
2. **Review [contracts/event-message.yaml](contracts/event-message.yaml)** for message format
3. **Run full test suite**: `vendor/bin/phpunit`
4. **Set up monitoring**: Configure Prometheus + Grafana (optional)
5. **Production checklist**: Review [tasks.md](tasks.md) for deployment tasks

---

## Helpful Commands Cheat Sheet

```bash
# Start everything
docker-compose up -d

# Stop workers (for debugging)
docker-compose stop worker

# Restart single worker
docker-compose restart worker_1

# View queue stats
curl -u guest:guest http://localhost:15672/api/queues/%2F/user_embedding_updates | jq

# Purge queue (DANGER: deletes all messages)
curl -u guest:guest -X DELETE http://localhost:15672/api/queues/%2F/user_embedding_updates/contents

# Connect to MongoDB
docker-compose exec mongodb mongosh -u root -p rootpassword myshop

# Connect to MySQL
docker-compose exec mysql mysql -u root -prootpassword myshop

# Check Symfony Messenger status
docker-compose exec php bin/console messenger:stats

# Tail all logs
docker-compose logs -f
```

---

**Quick Start Status**: ✅ **Complete** - Developers can now set up, test, and debug the system locally.
