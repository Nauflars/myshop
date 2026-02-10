# Feature Specification: User Embeddings Queue System

**Feature Branch**: `014-user-embeddings-queue`  
**Created**: February 10, 2026  
**Status**: Draft  
**Input**: User description: "Especificación Funcional: Cola y Proceso de Actualización de Embeddings de Usuario con RabbitMQ - Sistema asíncrono para actualizar embeddings semánticos de usuario en MongoDB basado en sus interacciones (búsquedas, vistas, clics, compras) usando RabbitMQ como cola de mensajes"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Search Event Processing (Priority: P1)

When a user performs a search on the platform, their semantic profile is updated to reflect their search interests, enabling better future recommendations without impacting search response time.

**Why this priority**: Search is the most frequent user interaction and the primary signal for understanding user intent. Processing search events establishes the foundation for the queue infrastructure and demonstrates immediate value.

**Independent Test**: Can be fully tested by triggering a search event, verifying the message is published to RabbitMQ, and confirming the user's embedding in MongoDB is updated with the search phrase embedding within a reasonable time window.

**Acceptance Scenarios**:

1. **Given** a logged-in user with an existing embedding in MongoDB, **When** they search for "wireless headphones", **Then** a search event message is published to RabbitMQ with user ID, search phrase, and timestamp
2. **Given** a search event message in the queue, **When** a worker processes it, **Then** the search phrase embedding is generated and the user's embedding in MongoDB is updated with weighted aggregation
3. **Given** a new user without an existing embedding, **When** they perform their first search, **Then** an initial user embedding is created in MongoDB based solely on that search phrase

---

### User Story 2 - Product Interaction Processing (Priority: P2)

When a user views, clicks, or purchases a product, their semantic profile is updated to reflect product preferences, enabling personalized recommendations based on actual shopping behavior.

**Why this priority**: Product interactions (especially purchases) are high-value signals for recommendations. This builds on the search event infrastructure while adding product embedding retrieval logic.

**Independent Test**: Can be fully tested by triggering a product view/click/purchase event, verifying the message reaches RabbitMQ, and confirming the user's embedding incorporates the product's existing embedding from MongoDB with appropriate weighting.

**Acceptance Scenarios**:

1. **Given** a user viewing a product, **When** the product view event occurs, **Then** a message is published to RabbitMQ with user ID, product ID, event type "view", and timestamp
2. **Given** a product interaction message in the queue, **When** a worker processes it, **Then** the product's existing embedding is retrieved from MongoDB and integrated into the user's embedding with event-type-specific weighting (purchase > click > view)
3. **Given** multiple product events for the same user within 5 seconds, **When** batching is enabled, **Then** these events are processed together in a single embedding update operation

---

### User Story 3 - Temporal Decay Application (Priority: P2)

User embeddings reflect recent interests more strongly than older interactions, ensuring recommendations remain relevant to current user needs rather than outdated behavior.

**Why this priority**: Without temporal decay, user profiles become stale and less accurate over time. This is critical for recommendation quality but can be implemented after basic event processing works.

**Independent Test**: Can be fully tested by creating a user with historical interaction timestamps, triggering a new event, and verifying that the updated embedding gives reduced weight to older events based on their age.

**Acceptance Scenarios**:

1. **Given** a user with interactions from 30 days ago, **When** a new event is processed, **Then** the older interactions receive reduced weighting based on an exponential decay function
2. **Given** a user with interactions from various time periods, **When** their embedding is recalculated, **Then** events from the last 7 days have full weight, events from 30 days have 50% weight, and events older than 90 days have minimal weight

---

### User Story 4 - Fault Tolerance and Retry Logic (Priority: P1)

When temporary failures occur (network issues, MongoDB unavailable, NLP service down), events are automatically retried to ensure no user interaction data is lost, maintaining system reliability.

**Why this priority**: Without retry logic, the system loses critical user data during outages. This must be implemented from the start to ensure production readiness and data integrity.

**Independent Test**: Can be fully tested by simulating MongoDB downtime, publishing events to the queue, and verifying events are retried automatically and eventually processed when MongoDB recovers, with failed events routed to a dead-letter queue after exhausting retries.

**Acceptance Scenarios**:

1. **Given** MongoDB is temporarily unavailable, **When** a worker attempts to process an event, **Then** the event is not acknowledged and is automatically requeued for retry after a delay
2. **Given** an event has failed processing 5 times, **When** the 5th retry fails, **Then** the event is moved to a dead-letter queue and an error is logged for manual review
3. **Given** the same event is delivered twice due to network issues, **When** the worker processes it the second time, **Then** idempotency checks prevent duplicate embedding updates

---

### User Story 5 - Queue Monitoring and Observability (Priority: P3)

System operators can monitor queue health, message processing rates, worker performance, and error rates through RabbitMQ's management interface to ensure smooth operations and quickly identify issues.

**Why this priority**: While important for production operations, monitoring can be added after core functionality works. The RabbitMQ management interface provides baseline visibility by default.

**Independent Test**: Can be fully tested by accessing the RabbitMQ management interface, publishing various events, and verifying that queue depth, message rates, consumer counts, and error metrics are visible and accurate.

**Acceptance Scenarios**:

1. **Given** RabbitMQ management interface is enabled, **When** an operator accesses it, **Then** they can see current queue depth, message publish/consume rates, and active worker connections
2. **Given** events are being processed with some failures, **When** viewing the management interface, **Then** failed message counts and dead-letter queue contents are visible
3. **Given** multiple workers are processing events, **When** checking the interface, **Then** the number of active consumers and message distribution across workers is displayed

---

### Edge Cases

- **What happens when a user generates hundreds of events per second?** The system should implement rate limiting or batching to prevent overwhelming the queue and worker capacity. Events should be aggregated when possible to reduce redundant processing.

- **What happens when an event references a product that doesn't exist in MongoDB?** The worker should log a warning, skip the embedding update for that event, and acknowledge the message to prevent infinite retries. A monitoring alert should be triggered for data consistency investigation.

- **What happens when the RabbitMQ queue grows faster than workers can process?** The system should scale horizontally by adding more worker instances. Queue depth metrics should trigger alerts when thresholds are exceeded.

- **What happens when a user's embedding calculation results in invalid vector dimensions?** The worker should log the error, send the message to the dead-letter queue, and preserve the user's previous valid embedding rather than corrupting their profile.

- **What happens when the same event is published multiple times?** Idempotency checks using event timestamps and/or unique message IDs should detect duplicates and skip redundant processing.

- **What happens to events already in the queue when a worker crashes?** Unacknowledged messages should remain in RabbitMQ and be automatically delivered to other active workers, ensuring no data loss.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST publish an event message to RabbitMQ whenever a user performs a search, product view, product click, or product purchase
- **FR-002**: Event messages MUST contain user ID, event type (search/view/click/purchase), reference data (search phrase or product ID), ISO 8601 timestamp, and optional metadata
- **FR-003**: System MUST persist all user events to MySQL as the source of truth before publishing to RabbitMQ
- **FR-004**: Worker processes MUST consume messages from RabbitMQ and process them asynchronously without blocking the API
- **FR-005**: Worker MUST validate message structure and data integrity before processing
- **FR-006**: For search events, worker MUST generate a text embedding of the search phrase using the configured NLP model
- **FR-007**: For product events, worker MUST retrieve the product's existing embedding from MongoDB
- **FR-008**: Worker MUST retrieve the user's current embedding from MongoDB, or create an initial embedding if none exists
- **FR-009**: Worker MUST calculate the updated user embedding by applying event-type-specific weighting (purchase: 1.0, click: 0.5, view: 0.3, search: 0.7 as default values)
- **FR-010**: Worker MUST apply temporal decay to previous interactions based on event age when calculating the updated embedding
- **FR-011**: Worker MUST persist the updated user embedding to MongoDB
- **FR-012**: Worker MUST acknowledge (ack) messages only after successful processing and MongoDB update
- **FR-013**: System MUST implement idempotency to ensure processing the same event multiple times does not corrupt user embeddings
- **FR-014**: System MUST automatically retry failed message processing up to a maximum of 5 attempts with exponential backoff
- **FR-015**: System MUST route messages to a dead-letter queue after exhausting all retry attempts
- **FR-016**: System MUST log all errors with sufficient context for debugging and auditing
- **FR-017**: RabbitMQ MUST be configured with durable queues and persistent messages to survive service restarts
- **FR-018**: System MUST support multiple concurrent worker processes for horizontal scaling
- **FR-019**: System MUST implement optional event batching to process multiple events for the same user together when they arrive within a 5-second window
- **FR-020**: RabbitMQ management interface MUST be accessible for queue monitoring and debugging

### Key Entities *(include if feature involves data)*

- **Event Message**: Represents a user interaction to be processed. Contains user identifier, event type enumeration (search/view/click/purchase), reference data (string for searches, product identifier for products), UTC timestamp, and optional metadata dictionary. Published to RabbitMQ and stored in MySQL.

- **User Embedding**: Semantic vector representation of a user's interests and behavior stored in MongoDB. A high-dimensional vector (typically 384 or 768 dimensions) calculated from weighted aggregation of interaction embeddings with temporal decay applied. Updated asynchronously by workers.

- **Product Embedding**: Pre-computed semantic vector representation of a product stored in MongoDB. Assumed to already exist in the system. Retrieved by workers during product event processing.

- **Dead-Letter Message**: An event message that failed processing after all retry attempts. Contains the original message payload plus error information and retry history. Stored in a separate RabbitMQ queue for manual investigation.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: User interactions (search, view, click, purchase) do not experience increased response time due to embedding updates - API response time remains under 200ms
- **SC-002**: User embeddings are updated within 30 seconds of the interaction occurring under normal load conditions
- **SC-003**: System successfully processes at least 1000 events per minute with 3 worker instances running
- **SC-004**: Message processing has 99.9% success rate, with failed messages properly routed to dead-letter queue
- **SC-005**: System maintains zero data loss during planned RabbitMQ or worker restarts due to durable queues and persistent messages
- **SC-006**: Worker processes can be scaled horizontally, with throughput increasing linearly up to 10 concurrent workers
- **SC-007**: Duplicate event processing does not corrupt user embeddings due to idempotency implementation
- **SC-008**: Queue depth remains below 5000 messages during normal operations, indicating workers keep up with event generation rate
