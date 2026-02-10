# Implementation Plan: User Embeddings Queue System

**Branch**: `014-user-embeddings-queue` | **Date**: February 10, 2026 | **Spec**: [spec.md](spec.md)
**Input**: Feature specification from `/specs/014-user-embeddings-queue/spec.md`

## Summary

This feature implements an asynchronous queue-based system for updating user semantic embeddings in MongoDB based on user interactions (searches, product views, clicks, purchases). Events are published to RabbitMQ from the Symfony API, then consumed by worker processes that generate/retrieve embeddings, apply temporal decay and event weighting, and update user profiles without impacting API response times.

## Technical Context

**Language/Version**: PHP 8.3  
**Primary Dependencies**: Symfony 7.3 (Framework Bundle, Messenger), RabbitMQ (AMQP), MongoDB PHP Library 2.0, OpenAI PHP Client (for embeddings), Doctrine ORM (for MySQL)  
**Storage**: MySQL 8.0 (source of truth for events), MongoDB 7.0 (user/product embeddings), Redis 7 (caching)  
**Testing**: PHPUnit 10.0, Symfony PHPUnit Bridge  
**Target Platform**: Linux Docker containers (PHP-FPM, dedicated worker containers)
**Project Type**: Web backend with async workers  
**Performance Goals**: 1000 events/minute with 3 workers, embedding updates within 30 seconds under normal load  
**Constraints**: API response time <200ms (async publishing), 99.9% message processing success rate, queue depth <5000 messages  
**Scale/Scope**: High-volume event processing (thousands of user interactions daily), concurrent worker processing, horizontal scalability up to 10 workers

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Project Principles Alignment

**Domain-Driven Design (DDD)**:
- ✅ Event domain models in Domain layer (`Domain/Event/UserInteractionEvent.php`)
- ✅ Use cases in Application layer (`Application/UseCase/UpdateUserEmbedding.php`)
- ✅ Infrastructure implementations isolated (`Infrastructure/Queue/RabbitMQPublisher.php`)
- ✅ Domain logic independent of RabbitMQ/messaging implementation details

**Symfony Best Practices**:
- ✅ Symfony Messenger component for queue abstraction
- ✅ AMQP transport configuration via messenger.yaml
- ✅ Environment variables for RabbitMQ connection (RABBITMQ_DSN)
- ✅ Console commands for worker management
- ✅ Service autowiring and dependency injection

**Quality Standards**:
- ✅ Unit tests for embedding calculation logic (weighting, decay)
- ✅ Integration tests for message publishing and consumption
- ✅ Contract tests for message formats and queue behavior
- ✅ Idempotency tests to prevent duplicate processing
- ✅ Error handling with DLQ for failed messages
- ✅ Comprehensive logging for debugging and auditing

**Security & Privacy**:
- ✅ User interactions already stored in MySQL (no new PII to external services)
- ✅ RabbitMQ credentials in environment variables, never committed
- ✅ Message validation to prevent injection attacks
- ✅ Rate limiting on event publication to prevent queue flooding

### Red Flags Assessment

**✅ GATE PASSED - No Critical Blockers**

**⚠️ Monitored Risks**:

1. **Message Loss Risk**: RabbitMQ failure could lose in-flight events
   - *Mitigation*: Durable queues, persistent messages, transaction logging in MySQL as source of truth, manual replay capability from MySQL logs
   
2. **Data Consistency**: User embeddings could become stale if workers fall behind
   - *Mitigation*: Queue depth monitoring, auto-scaling workers, priority queues for purchase events, maximum age alerting
   
3. **Computational Cost**: OpenAI embedding API calls for every search could be expensive
   - *Mitigation*: Batch similar searches, cache search phrase embeddings in Redis, consider local embedding model for searches
   
4. **Complexity**: Adding RabbitMQ introduces new infrastructure dependency
   - *Mitigation*: Symfony Messenger abstraction allows transport swapping, graceful degradation if queue unavailable, comprehensive Docker setup with management UI

5. **Worker Scaling**: Determining optimal worker count and resource allocation
   - *Mitigation*: Start with 3 workers, monitor queue depth and processing time, horizontal scaling based on metrics, load testing before production

## Project Structure

### Documentation (this feature)

```text
specs/014-user-embeddings-queue/
├── plan.md              # This file
├── spec.md              # Feature specification
├── research.md          # Phase 0: Technology research and decisions
├── data-model.md        # Phase 1: Event and embedding data models
├── quickstart.md        # Phase 1: Developer setup and testing guide
├── contracts/           # Phase 1: Message schemas and API contracts
│   ├── event-message.yaml
│   └── user-embedding.yaml
└── checklists/
    └── requirements.md  # Quality checklist (completed)
```

### Source Code (Symfony project root)

```text
src/
├── Application/
│   ├── Message/
│   │   └── UpdateUserEmbeddingMessage.php    # Async message for RabbitMQ
│   ├── MessageHandler/
│   │   └── UpdateUserEmbeddingHandler.php    # Worker message consumer
│   └── UseCase/
│       ├── PublishUserInteractionEvent.php   # Publish to queue
│       └── CalculateUserEmbedding.php        # Embedding calculation logic
├── Domain/
│   ├── Event/
│   │   └── UserInteractionEvent.php          # Domain event model
│   ├── ValueObject/
│   │   ├── EventType.php                     # Enum: search/view/click/purchase
│   │   ├── UserEmbedding.php                 # User embedding value object
│   │   └── EmbeddingWeights.php              # Event weighting configuration
│   └── Repository/
│       └── UserEmbeddingRepositoryInterface.php
├── Entity/
│   └── UserInteraction.php                   # MySQL entity for event log
├── Infrastructure/
│   ├── Queue/
│   │   ├── RabbitMQPublisher.php             # RabbitMQ message publisher
│   │   └── MessageSerializer.php             # JSON message serialization
│   ├── Persistence/
│   │   ├── MongoDB/
│   │   │   └── UserEmbeddingRepository.php   # MongoDB user embeddings
│   │   └── MySQL/
│   │       └── UserInteractionRepository.php # MySQL event log
│   └── AI/
│       └── Service/
│           └── OpenAIEmbeddingService.php    # Existing embedding service
└── Command/
    ├── ConsumeUserEmbeddingQueueCommand.php  # Worker start command
    └── ReplayUserEventsCommand.php           # Manual replay from MySQL

tests/
├── Unit/
│   ├── Application/UseCase/CalculateUserEmbeddingTest.php
│   ├── Domain/ValueObject/EmbeddingWeightsTest.php
│   └── Domain/ValueObject/UserEmbeddingTest.php
├── Integration/
│   ├── Queue/RabbitMQPublishConsumeTest.php
│   ├── Persistence/UserEmbeddingRepositoryTest.php
│   └── MessageHandler/UpdateUserEmbeddingHandlerTest.php
└── Contract/
    └── EventMessageFormatTest.php

config/
├── packages/
│   └── messenger.yaml                        # RabbitMQ transport configuration
└── services/
    └── queue.yaml                            # Queue services configuration

docker/
├── rabbitmq/
│   └── Dockerfile                            # RabbitMQ with management plugin
└── worker/
    └── Dockerfile                            # Dedicated worker container
```

**Structure Decision**: This follows Symfony's standard directory structure with DDD-inspired layering (Application, Domain, Infrastructure). The project uses Symfony Messenger for queue abstraction, allowing RabbitMQ to be swapped for another transport (Redis, SQS, etc.) if needed. Worker processes run as separate Docker containers executing the Symfony console command for message consumption.

## Complexity Tracking

> **No violations to justify** - Constitution Check passed without introducing unjustified complexity.

This feature integrates cleanly with existing Symfony architecture:
- Uses established Symfony Messenger component (already familiar to team)
- Follows existing DDD patterns in codebase
- Leverages existing OpenAIEmbeddingService infrastructure
- RabbitMQ is industry-standard message broker, not custom solution
- Adds minimal new concepts: message publishing and async consumption

**Simpler alternatives considered and rejected**:
- **Synchronous embedding updates**: Would block API response and violate <200ms constraint
- **Cron job batch processing**: Would introduce unacceptable latency (updates every N minutes vs. 30 seconds)
- **Database polling**: CPU-intensive, inefficient, doesn't scale
- **Redis queue instead of RabbitMQ**: Redis lacks advanced features (DLQ, message TTL, routing) needed for production reliability
