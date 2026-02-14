# Phase 0: Research & Technical Decisions

**Feature**: User Embeddings Queue System  
**Date**: February 10, 2026  
**Status**: Complete

## Research Areas

This document captures technical research, decisions, and rationales for implementation choices in the user embeddings queue system.

---

## 1. Message Broker: RabbitMQ vs. Alternatives

### Decision: RabbitMQ with AMQP via Symfony Messenger

**Rationale**:
- **Dead-Letter Queue (DLQ) Support**: Native support for routing failed messages to DLQ after retries exhausted, critical for FR-015
- **Message TTL & Expiration**: Built-in message time-to-live and expiration policies prevent queue overflow
- **Advanced Routing**: Topic exchanges and routing keys enable future extensibility (e.g., priority queues for purchase events)
- **Management UI**: Web-based interface for monitoring queue depth, message rates, and worker status (meets FR-020)
- **Production-Ready**: Battle-tested in high-throughput systems, mature PHP AMQP clients available
- **Symfony Messenger Integration**: First-class support via `symfony/amqp-messenger` transport

**Alternatives Considered**:

| Alternative | Pros | Rejected Because |
|-------------|------|------------------|
| **Redis Pub/Sub** | Already in stack, simpler setup | No message persistence, no DLQ, no retries - messages lost if worker down |
| **Amazon SQS** | Fully managed, auto-scaling | Cloud vendor lock-in, adds external dependency, higher latency from PHP in Docker |
| **Kafka** | Higher throughput, event streaming | Overkill for use case, heavyweight (Zookeeper), steep learning curve, resource-intensive |
| **Database Polling** | No new infrastructure | CPU-intensive, poor scalability, high latency, doesn't scale to 1000 events/min |

**Implementation Notes**:
- Use `amqp://` DSN in Symfony Messenger configuration
- Configure durable queues (`durable: true`) and persistent messages (`persistent: true`)
- Set up `failed` transport for DLQ with manual consumption for debugging

---

## 2. Embedding Aggregation Strategy

### Decision: Incremental Weighted Average with Exponential Temporal Decay

**Rationale**:
- **Performance**: O(1) update complexity - no need to retrieve entire event history
- **Real-Time**: Embedding updates happen in single worker cycle without historical data fetch
- **Scalability**: Works efficiently for users with thousands of interactions
- **Accuracy**: Temporal decay ensures recent interactions weigh more heavily than old ones

**Algorithm**:
```
new_embedding = (current_embedding * decay_factor + event_embedding * event_weight) / (decay_factor + event_weight)

where:
  decay_factor = exp(-λ * days_since_last_update)
  λ = decay constant (e.g., 0.023 for 30-day half-life)
  event_weight = {purchase: 1.0, search: 0.7, click: 0.5, view: 0.3}
```

**Alternatives Considered**:

| Alternative | Pros | Rejected Because |
|-------------|------|------------------|
| **Recalculate from all events** | Perfect accuracy, no drift | O(N) complexity, requires fetching all events from MySQL, too slow for real-time |
| **Simple average (no decay)** | Simplest implementation | Stale profiles - user searches from 2 years ago equally weighted as yesterday |
| **Sliding window (last N events)** | Fixed memory, no old data | Requires storing N events per user, arbitrary cutoff loses long-term patterns |
| **Batch recomputation** | Can use optimized algorithms | Doesn't meet 30-second update requirement, complex scheduling |

**Implementation Notes**:
- Store `last_updated` timestamp in MongoDB user embedding document
- Calculate `days_since_last_update = (event_timestamp - last_updated) / 86400`
- Use configurable decay constant via environment variable (default: `EMBEDDING_DECAY_LAMBDA=0.023`)
- Log warnings if `days_since_last_update > 90` (possible drift accumulation)

---

## 3. Idempotency Strategy

### Decision: Event Hash + MongoDB Upsert with Timestamp Check

**Rationale**:
- **Message-Level Idempotency**: Hash event (user_id + event_type + reference + timestamp) to detect duplicates
- **Storage-Level Protection**: MongoDB `findOneAndUpdate` with `{$max: {last_updated: event_timestamp}}` ensures later events never overwrite newer embeddings
- **No External State**: Doesn't require separate idempotency key storage (Redis/DB table)
- **Natural Deduplication**: Events with identical content produce identical embeddings, cumulative effect is safe

**Algorithm**:
```json
{
  "message_id": "sha256(user_id + event_type + reference + timestamp)",
  "idempotency_window": 300,  // 5 minutes
  "mongodb_update": {
    "$set": {"embedding": new_embedding},
    "$max": {"last_updated": event_timestamp}
  }
}
```

**Alternatives Considered**:

| Alternative | Pros | Rejected Because |
|-------------|------|------------------|
| **Redis idempotency key cache** | Fast lookup, explicit deduplication | Adds Redis dependency for queue system, keys expire (lost history) |
| **MySQL processed_events table** | Permanent audit trail | Requires DB write for every message, slower, table grows indefinitely |
| **RabbitMQ message deduplication** | Broker-level deduplication | Limited to recent messages (10-minute window), not across restarts |
| **No idempotency** | Simplest | Violates FR-013, duplicate processing corrupts embeddings |

**Implementation Notes**:
- Generate `message_id` in publisher before sending to RabbitMQ
- Include `message_id` in message body for logging/debugging
- Use MongoDB `{upsert: true}` to handle first-time user (no existing embedding)
- Never use `last_updated < event_timestamp` check alone (race conditions with out-of-order messages)

---

## 4. Event Batching Strategy

### Decision: Time-Window Batching with User-Level Grouping

**Rationale**:
- **Reduces MongoDB Writes**: Multiple events for same user within 5 seconds → single embedding update
- **Preserves Ordering**: Events for same user processed in timestamp order
- **Balances Latency**: 5-second window acceptable (within 30-second SLA), avoids indefinite delays
- **Scales Well**: Batching only happens under high load, no overhead for normal traffic

**Algorithm**:
```
1. Worker receives message for user_X
2. Start 5-second timer for user_X
3. Collect all events for user_X during timer
4. On timer expiration:
   - Sort events by timestamp (oldest first)
   - Calculate combined embedding update
   - Single MongoDB write
5. New message for user_X restarts timer
```

**Alternatives Considered**:

| Alternative | Pros | Rejected Because |
|-------------|------|------------------|
| **No batching** | Simpler, lower latency | High MongoDB write volume, poor scalability, wasteful for burst traffic |
| **Count-based batching (N events)** | Predictable batch sizes | Unbounded latency if user doesn't reach N events, unfair (active users batched, inactive immediate) |
| **Global time-window batching** | Maximizes throughput | Loses per-user ordering, complex state management across all users |
| **Event type priority (purchases immediate)** | Better UX for high-value events | Adds complexity, batching still needed for searches, partial solution |

**Implementation Notes**:
- Use Symfony Messenger `BatchHandlerInterface` if available, otherwise custom timer
- Configure batch window via environment variable (default: `EMBEDDING_BATCH_WINDOW=5`)
- Set `EMBEDDING_BATCH_ENABLED=false` initially, enable after load testing
- Log batch statistics (events per batch, batch frequency) for optimization

---

## 5. Temporal Decay Function

### Decision: Exponential Decay with 30-Day Half-Life

**Rationale**:
- **Recent Bias**: Events from last week retain 90%+ weight, events from 30 days retain 50%, events from 6 months <6%
- **Smooth Degradation**: No sharp cutoffs, gradual reduction prevents embedding instability
- **Configurable**: Lambda constant adjustable for different business needs (faster decay for fashion, slower for electronics)
- **Mathematically Sound**: Well-studied in recommendation systems, used by Netflix, Spotify

**Formula**:
```
decay_weight = exp(-λ * age_in_days)

where:
  λ = ln(2) / half_life_days = 0.693 / 30 = 0.023
  
Examples:
  - 7 days ago:  exp(-0.023 * 7)  = 0.85 (85% weight)
  - 30 days ago: exp(-0.023 * 30) = 0.50 (50% weight)
  - 90 days ago: exp(-0.023 * 90) = 0.13 (13% weight)
```

**Alternatives Considered**:

| Alternative | Pros | Rejected Because |
|-------------|------|------------------|
| **Linear decay** | Simpler math | Too aggressive (30-day event = 0 weight at 60 days), sudden drops |
| **Step function (recent/old)** | Binary simplicity | Harsh cutoff, causes embedding jumps when events cross threshold |
| **Hyperbolic decay (1 / (1 + age))** | Never reaches zero | Too slow - ancient events retain significant weight indefinitely |
| **No decay** | Simplest | Violates FR-010, stale user profiles don't reflect current interests |

**Implementation Notes**:
- Store decay lambda in configuration: `EMBEDDING_DECAY_LAMBDA=0.023`
- For different half-lives: `λ = ln(2) / desired_half_life_days`
- Apply decay to `current_embedding` total weight, not individual past events
- Consider full recalculation if embedding is >6 months stale (accumulation error)

---

## 6. Search Phrase Embedding Generation

### Decision: OpenAI text-embedding-3-small via Existing Service

**Rationale**:
- **Already Integrated**: `OpenAIEmbeddingService` exists in codebase (spec-010 semantic search)
- **High Quality**: OpenAI embeddings tuned for semantic similarity, 1536 dimensions
- **Cost-Effective**: $0.02 per 1M tokens, search phrases typically 3-20 tokens
- **Redis Caching**: Existing Redis caching reduces API calls by ~80% for common searches
- **No Training Required**: Pre-trained model, no ML ops overhead

**Workflow**:
```
1. Worker receives search event with phrase "wireless headphones"
2. Check Redis cache: HGET embeddings:search "wireless headphones"
3. Cache miss → call OpenAI API (50ms avg latency)
4. Cache result in Redis with 1-hour TTL
5. Integrate embedding into user embedding with search weight (0.7)
```

**Alternatives Considered**:

| Alternative | Pros | Rejected Because |
|-------------|------|------------------|
| **Sentence-BERT (local model)** | No API costs, faster, offline-capable | Requires Python service, model hosting, lower quality than OpenAI, ops overhead |
| **CLIP embeddings** | Multimodal (text + images) | Overkill for text-only searches, larger model, slower inference |
| **Word2Vec/ FastText** | Small, fast, local | Outdated (2013 tech), poor handling of phrases, lower semantic quality |
| **TF-IDF keyword extraction** | No embedding needed | Not semantic, can't compare with product embeddings, defeats purpose |

**Implementation Notes**:
- Reuse `OpenAIEmbeddingService::embedText(string $text): array`
- Handle API failures gracefully: skip embedding update, log warning, ack message (don't retry forever)
- Set cache TTL via `EMBEDDING_CACHE_TTL=3600` (1 hour)
- Monitor OpenAI API rate limits and costs in production

---

## 7. Dead-Letter Queue Handling

### Decision: Separate DLQ with Manual Review + Replay Command

**Rationale**:
- **Prevents Queue Blocking**: Failed messages don't block processing of healthy messages
- **Audit Trail**: All failures preserved with error context for debugging
- **Manual Intervention**: Human review determines if message needs fixing or can be discarded
- **Replay Capability**: Fixed system issues can replay valid messages from DLQ

**Workflow**:
```
1. Message fails 5 times (network errors, MongoDB down, etc.)
2. RabbitMQ routes to user_embedding_dlq with headers:
   - x-death-count: 5
   - x-first-death-reason: "MongoConnectionException"
   - x-original-timestamp: ISO8601
3. Monitoring alert triggered (DLQ depth > 10)
4. Developer reviews DLQ via RabbitMQ management UI
5. After MongoDB restored: bin/console app:replay-dlq --max=100
```

**Alternatives Considered**:

| Alternative | Pros | Rejected Because |
|-------------|------|------------------|
| **Infinite retries** | No message loss | Blocks queue forever for persistent failures, wastes resources |
| **No DLQ (just log and drop)** | Simplest | Loses data, no recovery path, violates FR-015 |
| **Automatic DLQ replay** | No manual intervention | Dangerous - replays messages that failed for valid reasons (bad data) |
| **Store failed messages in MySQL** | SQL-queryable | Adds DB writes on failure path, couples queue to DB, harder to replay to RabbitMQ |

**Implementation Notes**:
- Configure Symfony Messenger failed transport:
  ```yaml
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
- Create console command: `bin/console messenger:failed:retry --force`
- Set up monitoring alert: `rabbitmq_queue_messages{queue="failed"} > 10`
- Document DLQ investigation playbook in runbook

---

## 8. Worker Scaling Strategy

### Decision: Horizontal Pod Autoscaling Based on Queue Depth

**Rationale**:
- **Elastic**: Workers scale up during traffic spikes, scale down during quiet periods
- **Cost-Efficient**: No over-provisioning, pay only for needed capacity
- **Simple Metric**: Queue depth is reliable signal for backlog (depth > 1000 → scale up)
- **Docker-Native**: Easy to implement with `docker-compose scale` or Kubernetes HPA

**Scaling Policy**:
```
- Base: 3 workers (handle 1000 events/min)
- Scale up: queue depth > 1000 messages sustained for 2 minutes
- Scale up increment: add 2 workers (max 10 total)
- Scale down: queue depth < 100 messages sustained for 10 minutes
- Scale down decrement: remove 1 worker (min 3)
```

**Alternatives Considered**:

| Alternative | Pros | Rejected Because |
|-------------|------|------------------|
| **Fixed worker count** | Simplest, predictable | Wastes resources at low traffic, overwhelmed at high traffic |
| **Time-based scaling (cron)** | Handles predictable patterns | Doesn't handle unexpected spikes, requires manual traffic analysis |
| **CPU-based autoscaling** | Standard metric | Worker CPU depends on MongoDB/OpenAI latency, not queue backlog |
| **Event rate-based** | Proactive scaling | Harder to measure accurately, queue depth is lagging but more reliable |

**Implementation Notes**:
- Use RabbitMQ Management API to fetch queue depth: `GET /api/queues/%2F/user_embedding`
- Set up Prometheus scraper for `rabbitmq_queue_messages` metric
- Configure alerting: queue depth > 5000 = critical (workers can't keep up)
- Test scaling: `docker-compose up -d --scale worker=5`

---

## 9. Monitoring & Observability

### Decision: Structured Logging + RabbitMQ Management UI + Prometheus Metrics

**Rationale**:
- **Multi-Layered**: Logs for debugging, UI for real-time ops, metrics for trends
- **Low Overhead**: Structured logging already in Symfony (Monolog), RabbitMQ UI included
- **Production-Ready**: Industry-standard stack, integrates with existing monitoring
- **Actionable**: Clear visibility into queue health, worker performance, error rates

**Key Metrics**:
```
- Queue Depth: rabbitmq_queue_messages{queue="user_embedding"}
- Publish Rate: events_published_total (counter)
- Consume Rate: messages_processed_total (counter)
- Processing Time: embedding_update_duration_seconds (histogram)
- Error Rate: messages_failed_total (counter)
- DLQ Depth: rabbitmq_queue_messages{queue="failed"}
- Worker Count: worker_instances_active (gauge)
```

**Alternatives Considered**:

| Alternative | Pros | Rejected Because |
|-------------|------|------------------|
| **Custom dashboard (Grafana)** | Prettier, customizable | Adds setup overhead, RabbitMQ UI sufficient for MVP |
| **APM (New Relic, Datadog)** | Full distributed tracing | Expensive, overkill for single async workflow, vendor lock-in |
| **Logs only** | Simplest, universal | Hard to aggregate real-time metrics, no visual dashboards |
| **No monitoring** | No overhead | Violates FR-020, can't debug production issues, no capacity planning |

**Implementation Notes**:
- Enable RabbitMQ management plugin in Docker:
  ```dockerfile
  FROM rabbitmq:3-management-alpine
  ```
- Access UI at `http://localhost:15672` (guest/guest)
- Log structured JSON: `$logger->info('embedding_updated', ['user_id' => $userId, 'duration_ms' => 45])`
- Export Prometheus metrics via RabbitMQ Prometheus plugin

---

## Summary of Key Decisions

| Area | Decision | Primary Reason |
|------|----------|----------------|
| **Message Broker** | RabbitMQ | DLQ support, management UI, production-ready |
| **Aggregation** | Incremental weighted average | O(1) complexity, real-time updates |
| **Idempotency** | Event hash + MongoDB upsert | No external state, natural deduplication |
| **Batching** | 5-second time-window per user | Reduces MongoDB writes, maintains ordering |
| **Temporal Decay** | Exponential (30-day half-life) | Recent bias, smooth degradation |
| **Search Embeddings** | OpenAI via existing service | Already integrated, high quality, cached |
| **DLQ Handling** | Separate queue + manual replay | Audit trail, human review, recovery path |
| **Worker Scaling** | Queue depth-based autoscaling | Elastic, cost-efficient, simple metric |
| **Monitoring** | Structured logs + RabbitMQ UI + metrics | Multi-layered, low overhead, actionable |

---

## Open Questions / Future Optimization

**For Initial Implementation**:
- ✅ All technical decisions made
- ✅ No blockers identified
- ✅ Ready for Phase 1 (design)

**For Future Iterations**:
1. **Local Embedding Model**: Replace OpenAI with Sentence-BERT for cost savings after analyzing API spend
2. **Event Prioritization**: Implement priority queues (purchases immediate, views batched longer)
3. **Multi-Tenant Isolation**: Separate queues per customer segment if scaling beyond single tenant
4. **Stream Processing**: Migrate to Kafka Streams if event volumes exceed 100K/minute
5. **ML Feature Store**: Integration with feature store (Feast, Tecton) for broader personalization

---

**Phase 0 Status**: ✅ **Complete** - All research questions answered, ready for data modeling and contract definition.
