# Implementation Plan: Semantic Product Search with Symfony AI & OpenAI Embeddings

**Feature Branch**: `010-semantic-search`  
**Created**: February 7, 2026  
**Status**: Planning  
**Specification**: [spec.md](spec.md)  
**Tasks**: [tasks.md](tasks.md)

## Executive Summary

This feature introduces semantic product search capabilities powered by OpenAI embeddings and MongoDB vector store, enabling users to search products using natural language queries that understand intent and meaning rather than just keyword matching.

**Business Value**:
- Improved search relevance for ambiguous or synonym-based queries
- Better conversion rates through more accurate product discovery
- Enhanced customer experience with natural language search
- AI-powered Virtual Assistant gains semantic product recommendation capability

**Technical Scope**:
- OpenAI embeddings API integration (text-embedding-3-small, 1536 dimensions)
- MongoDB as vector database for similarity search
- Automatic MySQL ↔ MongoDB synchronization via Doctrine events
- Redis caching layer for query embeddings
- Symfony AI tool for Virtual Assistant integration
- Fallback to keyword search for reliability

---

## Technical Context

### Current System Architecture

**Database**: MySQL (products, orders, users, conversations)  
**Cache**: Redis (conversation context, cache)  
**Backend**: Symfony 7.3, PHP 8.3, Doctrine ORM  
**AI Infrastructure**: Symfony AI Bundle, OpenAI integration  
**Frontend**: Twig templates, Vanilla JavaScript  
**Containers**: Docker Compose (PHP-FPM, Nginx, MySQL, Redis, Postgres)

### Existing Dependencies

- **spec-002**: AI Shopping Assistant (Virtual Assistant customer chatbot)
- **spec-009**: Context Memory (conversation context management with Redis)
- **Product Entity**: `src/Domain/Entity/Product.php` - existing MySQL product catalog
- **Redis**: Already configured and operational for context storage
- **OpenAI API**: Already configured with API key for chatbot

### Technology Additions

- **MongoDB 7.0**: Vector database for embedding storage and similarity search
- **mongodb/mongodb PHP Driver**: ^1.17 for MongoDB connectivity
- **OpenAI Embeddings API**: text-embedding-3-small model (1536 dimensions, $0.02/1M tokens)
- **Vector Indexing**: MongoDB Atlas Search or native vector search capability

---

## Constitution Check

### Project Principles Alignment

**Domain-Driven Design (DDD)**:
- ✅ Embedding entities defined in Domain layer (`Domain/Entity/ProductEmbedding.php`)
- ✅ Use cases defined in Application layer (`Application/UseCase/SyncProductEmbedding.php`)
- ✅ Infrastructure implementations in Infrastructure layer (`Infrastructure/Repository/MongoDBEmbeddingRepository.php`)
- ✅ Clear separation: Domain logic independent of OpenAI/MongoDB

**Symfony Best Practices**:
- ✅ Service configuration via YAML files
- ✅ Environment variables for external services (OPENAI_API_KEY, MONGODB_URL)
- ✅ Symfony AI Bundle integration for embeddings and tools
- ✅ Symfony Messenger for async embedding sync jobs
- ✅ Console commands for admin operations

**Quality Standards**:
- ✅ Unit tests for business logic (embedding generation, similarity calculation)
- ✅ Integration tests for sync lifecycle (create, update, delete)
- ✅ Error handling with graceful degradation (fallback to keyword search)
- ✅ Comprehensive logging for debugging and monitoring

**Security & Privacy**:
- ✅ API keys stored in environment variables, never committed
- ✅ Product descriptions only (no user PII sent to OpenAI)
- ✅ MongoDB read-only from search endpoints (no user writes)
- ✅ Rate limiting on search endpoints to prevent abuse

### Red Flags Assessment

**❌ Potential Gate Breakers**:
1. **Cost Risk**: OpenAI API costs could exceed budget if not monitored
   - *Mitigation*: Redis caching (80% cache hit target), monitoring dashboard, cost alerts
   
2. **Data Consistency**: MongoDB could become out-of-sync with MySQL
   - *Mitigation*: Sync via reliable Doctrine events, manual re-sync command, monitoring for gaps
   
3. **Performance**: Vector search could be slow for large catalogs (>100K products)
   - *Mitigation*: MongoDB vector indexes, result pagination, caching, performance tests before launch

4. **Dependency**: MongoDB and OpenAI outages break semantic search
   - *Mitigation*: Fallback to keyword search, circuit breakers, health checks, degraded mode

**Justification**: All risks have documented mitigations. Feature provides clear business value (better search) and can be rolled out incrementally. Fallback mechanisms ensure existing keyword search remains operational.

---

## Architecture Overview

### Data Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                          User Actions                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  1. PRODUCT SYNC (Admin Action)                                    │
│     Admin creates/updates/deletes product via admin panel          │
│                         ↓                                           │
│     Doctrine Event (PostPersist/PostUpdate/PostRemove)             │
│                         ↓                                           │
│     ProductEmbeddingSyncService                                    │
│                         ↓                                           │
│     OpenAI Embeddings API (generate 1536-dim vector)               │
│                         ↓                                           │
│     MongoDBEmbeddingRepository (store/update/delete)               │
│                         ↓                                           │
│     MongoDB product_embeddings collection                          │
│                                                                     │
│  2. SEMANTIC SEARCH (Customer Action)                              │
│     Customer searches "laptop for gaming" (natural language)       │
│                         ↓                                           │
│     SearchFacade (mode=semantic)                                   │
│                         ↓                                           │
│     Check Redis cache for query embedding                          │
│             ↓ cache miss              ↓ cache hit                  │
│     OpenAI Embeddings API      Use cached embedding                │
│             ↓                          ↓                            │
│     Cache in Redis          MongoDBEmbeddingRepository             │
│                         ↓                                           │
│     Vector similarity search (cosine similarity)                   │
│                         ↓                                           │
│     Ranked results (product_ids + similarity scores)               │
│                         ↓                                           │
│     Enrich with MySQL data (price, stock, images)                  │
│                         ↓                                           │
│     Return ranked product list to user                             │
│                                                                     │
│  3. VA SEMANTIC SEARCH (Chatbot Action)                            │
│     Customer asks VA "show me gear for streaming"                  │
│                         ↓                                           │
│     Symfony AI Agent processes message                             │
│                         ↓                                           │
│     VA calls SemanticProductSearchTool                             │
│                         ↓                                           │
│     Tool invokes SemanticSearchService                             │
│                         ↓                                           │
│     (same flow as #2 above)                                        │
│                         ↓                                           │
│     Structured results returned to VA                              │
│                         ↓                                           │
│     VA presents products conversationally                          │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Component Diagram

```
┌───────────────────────────────────────────────────────────────────┐
│                        Presentation Layer                         │
├───────────────────────────────────────────────────────────────────┤
│  ProductController        │  ChatbotController (VA)               │
│  - searchProducts()       │  - chat() [uses VA tools]            │
│  - ?mode=semantic         │                                       │
└───────────────────────────┴───────────────────────────────────────┘
                                    ↓
┌───────────────────────────────────────────────────────────────────┐
│                        Application Layer                          │
├───────────────────────────────────────────────────────────────────┤
│  SearchFacade             │  ProductEmbeddingSyncService          │
│  - routeSearch()          │  - generateEmbedding()                │
│                           │  - syncToMongoDB()                    │
│  SemanticSearchService    │                                       │
│  - searchSemanticaly()    │  EmbeddingCacheService                │
│  - enrichResults()        │  - get() / set()                      │
│                           │                                       │
│  KeywordSearchService     │  SyncProductEmbedding (Use Case)      │
│  - searchByKeyword()      │                                       │
└───────────────────────────┴───────────────────────────────────────┘
                                    ↓
┌───────────────────────────────────────────────────────────────────┐
│                        Domain Layer                               │
├───────────────────────────────────────────────────────────────────┤
│  Product (Entity)         │  ProductEmbedding (Entity)            │
│  - id, name, description  │  - product_id, embedding[], metadata  │
│                           │                                       │
│  SearchQuery (VO)         │  SearchResult (VO)                    │
│  - query, mode            │  - products[], scores[], mode         │
└───────────────────────────┴───────────────────────────────────────┘
                                    ↓
┌───────────────────────────────────────────────────────────────────┐
│                      Infrastructure Layer                         │
├───────────────────────────────────────────────────────────────────┤
│  OpenAIEmbeddingService   │  MongoDBEmbeddingRepository           │
│  - generateEmbedding()    │  - searchBySimilarity()               │
│  - API client             │  - MongoDB driver                     │
│                           │                                       │
│  ProductRepository        │  RedisCache                           │
│  - MySQL/Doctrine         │  - query embedding cache              │
│                           │                                       │
│  SemanticProductSearchTool│  ProductEmbeddingListener             │
│  - Symfony AI Tool        │  - Doctrine event listeners           │
└───────────────────────────┴───────────────────────────────────────┘
                                    ↓
┌───────────────────────────────────────────────────────────────────┐
│                        External Services                          │
├───────────────────────────────────────────────────────────────────┤
│  MySQL (Source of Truth) │  MongoDB (Vector Store)                │
│  - products table         │  - product_embeddings collection      │
│                           │                                       │
│  OpenAI API               │  Redis (Cache)                        │
│  - text-embedding-3-small │  - query embeddings                   │
└───────────────────────────┴───────────────────────────────────────┘
```

---

## Phase 0: Infrastructure & Foundation

### Goals
- MongoDB operational with vector index
- OpenAI embeddings API integrated
- Base domain entities and repositories created

### Key Decisions

**Decision 1: MongoDB vs. Specialized Vector DB**
- **Choice**: MongoDB 7.0 with vector search
- **Rationale**: Simpler architecture (one less service), sufficient performance for 10K-100K products, team familiar with MongoDB
- **Alternatives**: Pinecone (requires separate service, $$$), Qdrant (another container), Postgres pgvector (no team experience)
- **Trade-off**: MongoDB may be slower than specialized solutions at 1M+ products scale, but we're not there yet

**Decision 2: Embedding Model Selection**
- **Choice**: text-embedding-3-small (1536 dimensions)
- **Rationale**: Cost-efficient ($0.02/1M tokens vs $0.13/1M for large), sufficient quality for e-commerce product descriptions
- **Alternatives**: text-embedding-3-large (3072 dims, higher quality but 6.5x cost), ada-002 (older model)
- **Trade-off**: Slightly lower accuracy vs. large model, but acceptable for budget constraints

**Decision 3: Sync Strategy (Real-time vs. Batch)**
- **Choice**: Real-time sync via Doctrine event listeners + async queue for resilience
- **Rationale**: Keeps embeddings fresh immediately, users see new products in semantic search right away
- **Alternatives**: Batch sync every hour (stale data), webhook-based sync (more complexity)
- **Trade-off**: Slightly more complex error handling, but better UX

### Implementation Tasks

Refer to [tasks.md](tasks.md) Phase 0 (T001-T011):
- Docker Compose MongoDB service
- Composer packages (mongodb/mongodb)
- Configuration files (mongodb.yaml, .env)
- Domain entities (ProductEmbedding)
- Infrastructure (OpenAIEmbeddingService, MongoDBEmbeddingRepository)
- MongoDB vector index creation
- Test command for embedding generation

**Checkpoint**: Run `php bin/console app:test-embedding`, verify embedding generated and stored in MongoDB.

---

## Phase 1: Product Embedding Synchronization

### Goals
- Admin creates product → embedding auto-generated → MongoDB synced
- Admin updates product → embedding regenerated → MongoDB updated
- Admin deletes product → MongoDB document removed
- Failures handled gracefully (retry, logging, degraded mode)

### Key Decisions

**Decision 4: Event-Driven Sync vs. Repository Pattern**
- **Choice**: Doctrine event listeners (PostPersist, PostUpdate, PostRemove)
- **Rationale**: Automatic, no code changes needed in admin controllers, guaranteed to trigger on all product changes
- **Alternatives**: Manual sync calls in controllers (error-prone, easy to forget), repository decorators (more complex)
- **Trade-off**: Events are implicit (harder to trace), but sync is automatic and reliable

**Decision 5: Sync vs. Async Embedding Generation**
- **Choice**: Hybrid - sync by default, async queue for failures
- **Rationale**: Most operations succeed immediately (good UX), failures don't block admin operations (resilience)
- **Alternatives**: Always async (slower feedback), always sync (blocks on failures)
- **Trade-off**: Slightly more complexity (queue), but better UX and reliability

**Decision 6: Error Handling Strategy**
- **Choice**: MySQL operation always succeeds, embedding sync failures logged and queued
- **Rationale**: MySQL is source of truth, product CRUD must not fail due to external API issues
- **Alternatives**: Atomic sync (rollback MySQL if embedding fails - bad UX), ignore failures (data inconsistency)
- **Trade-off**: Temporary inconsistency between MySQL and MongoDB, but admin operations never blocked

### Implementation Tasks

Refer to [tasks.md](tasks.md) Phase 1 (T012-T029):
- ProductEmbeddingSyncService (core sync logic)
- Doctrine event listeners (PostPersist, PostUpdate, PostRemove)
- Embedding generation via OpenAI API
- MongoDB CRUD operations (create, update, delete)
- Error handling (retry, circuit breaker, logging)
- Symfony Messenger async queue
- Manual re-sync commands
- Integration tests

**Checkpoint**: Create product via admin panel, verify MongoDB document created. Update description, verify embedding updated. Delete product, verify MongoDB document removed.

---

## Phase 2: Semantic Search Service

### Goals
- User searches "laptop for gaming" → semantic search returns relevant products
- Results ranked by similarity score (0.0-1.0)
- Fallback to keyword search if semantic search fails
- Support both `?mode=semantic` and `?mode=keyword`

### Key Decisions

**Decision 7: Similarity Metric**
- **Choice**: Cosine similarity (normalized dot product)
- **Rationale**: Standard for embedding comparisons, MongoDB native support, range 0-1 easy to interpret
- **Alternatives**: Euclidean distance (harder to interpret), dot product (not normalized)
- **Trade-off**: None significant, cosine is industry standard

**Decision 8: Search Result Enrichment**
- **Choice**: MongoDB returns product_ids + scores → enrich with MySQL data
- **Rationale**: MongoDB only stores minimal data for search, MySQL has full product info (price, stock, images)
- **Alternatives**: Denormalize all data in MongoDB (data duplication, sync complexity)
- **Trade-off**: Extra MySQL query per search, but acceptable latency (<50ms for 50 products)

**Decision 9: Fallback Strategy**
- **Choice**: Automatic fallback to keyword search on semantic search failures
- **Rationale**: System remains operational even if MongoDB or OpenAI unavailable
- **Alternatives**: Return error to user (bad UX), retry indefinitely (bad UX)
- **Trade-off**: User might not know semantic search failed, but search still works

### Implementation Tasks

Refer to [tasks.md](tasks.md) Phase 2 (T030-T050):
- SearchQuery and SearchResult value objects
- SemanticSearchService (query embedding, MongoDB search, enrichment)
- KeywordSearchService (traditional MySQL LIKE queries)
- SearchFacade (route between semantic/keyword)
- ProductController endpoint updates
- Mode parameter handling (?mode=semantic)
- Error handling and fallbacks
- Integration tests

**Checkpoint**: Search "budget laptop for students", verify semantically similar products returned (even if description says "affordable computer for education"). Search ?mode=keyword, verify exact keyword matching works.

---

## Phase 3: Redis Caching

### Goals  
- Query embeddings cached to reduce OpenAI API calls
- Cache hit rate >80% for cost savings
- Redis failures don't break search (degraded mode)

### Key Decisions

**Decision 10: Cache Key Format**
- **Choice**: `search:embedding:{md5(query)}`
- **Rationale**: Short hash key, collision-resistant, easy to identify cache entries
- **Alternatives**: Full query as key (long keys), numeric ID (need mapping)
- **Trade-off**: MD5 not cryptographic, but fine for cache keys

**Decision 11: Cache TTL**
- **Choice**: 1 hour (3600 seconds)
- **Rationale**: Balance between freshness and cost savings, product catalogs don't change that frequently
- **Alternatives**: 24 hours (more savings, stale data), 5 minutes (fresh but low hit rate)
- **Trade-off**: May cache outdated embeddings if query patterns change, but acceptable

### Implementation Tasks

Refer to [tasks.md](tasks.md) Phase 3 (T051-T062):
- EmbeddingCacheService (get, set, TTL management)
- Cache check before OpenAI API call
- Cache write after embedding generation
- Redis error handling (bypass cache, degrade gracefully)
- Cache metrics (hit rate, miss rate)
- Tests

**Checkpoint**: Search "laptop" twice quickly, verify second query doesn't call OpenAI API (cache hit logged).

---

## Phase 4: Virtual Assistant Integration

### Goals
- VA can use semantic search via Symfony AI tool
- Customer context enriches semantic queries
- Results formatted conversationally

### Key Decisions

**Decision 12: Tool Design**
- **Choice**: Single SemanticProductSearchTool with query parameter
- **Rationale**: Simple for VA to use, flexible query input, returns structured results
- **Alternatives**: Multiple specialized tools (SearchLaptopTool, SearchPhoneTool - too specific)
- **Trade-off**: VA must formulate good queries, but that's what LLMs do well

**Decision 13: Context Enrichment**
- **Choice**: Pass customer context (from spec-009) to enrich semantic queries
- **Rationale**: "Show me similar items" should understand what customer was looking at
- **Alternatives**: No context (VA can't do follow-ups), full conversation history (too expensive)
- **Trade-off**: Slightly more complex query construction, but much better conversational experience

### Implementation Tasks

Refer to [tasks.md](tasks.md) Phase 4 (T063-T073):
- SemanticProductSearchTool (Symfony AI tool implementation)
- Tool description, parameters, execute() method
- Context enrichment logic
- Result formatting for VA
- VA agent configuration updates
- Integration tests

**Checkpoint**: Chat with VA, say "I need headphones for running". Verify VA calls SemanticProductSearchTool, receives results, presents them conversationally.

---

## Phase 5-8: Production Readiness

### Performance Optimization (Phase 5)
- Profiling, metrics collection
- MongoDB query optimization (indexes, projections)
- Batch embedding generation for initial sync
- Cost monitoring dashboards

### Error Handling & Reliability (Phase 6)
- Retry logic, circuit breakers
- Fallbacks, degraded mode
- Dead letter queues
- Alerting and monitoring

### Testing & Documentation (Phase 7)
- Unit, integration, E2E tests
- Test fixtures and quality tests
- Admin and developer documentation
- API documentation

### Production Deployment (Phase 8)
- Production configuration (MongoDB Atlas, OpenAI production key)
- Initial embedding sync
- Monitoring and alerting setup
- Feature flag rollout
- Post-launch review

Refer to [tasks.md](tasks.md) Phases 5-8 (T074-T137) for full task breakdown.

---

## Success Metrics (from spec.md)

### Must Achieve (Launch Blockers)

- **SC-001**: Natural language queries return semantically relevant results (manual test on 50 sample queries)
- **SC-002**: Average similarity score >0.7 for relevant queries (measured on test dataset)
- **SC-003**: 99.9% MySQL-MongoDB sync consistency (automated monitoring)
- **SC-008**: Zero data loss during sync over 30 days (automated monitoring)

### Should Achieve (Post-Launch Optimization)

- **SC-004**: 95% of semantic searches <5s response time (p95 monitoring)
- **SC-005**: 80% Redis cache hit rate (logging and dashboard)
- **SC-009**: OpenAI costs <$50/month (billing dashboard and alerts)
- **SC-010**: VA semantic tool success rate >95% (tool call logs)

### Nice to Have (Future Iteration)

- **SC-006**: User satisfaction surveys (70% rate search as "relevant")
- **SC-007**: 100 concurrent requests without degradation (load testing)

---

## Risk Mitigation

| Risk | Likelihood | Impact | Mitigation |
|------|-----------|--------|------------|
| OpenAI API cost overrun | Medium | High | Redis caching (80% hit rate), cost alerts at $40, usage dashboard |
| MongoDB out-of-sync | Low | Medium | Automated consistency checks, manual re-sync command, monitoring |
| Poor search quality | Medium | High | Test with curated query dataset, tune similarity threshold (0.6-0.7) |
| OpenAI API outage | Low | Medium | Fallback to keyword search, circuit breaker, retry queue |
| MongoDB performance issues | Low | Medium | Vector indexes, query optimization, pagination (max 50 results) |
| Embedding dimension changes | Very Low | High | Document model version in MongoDB, plan migration path |

---

## Rollout Plan

### Week 1-2: Infrastructure (Phase 0)
- Set up MongoDB container
- Integrate OpenAI embeddings API
- Create base domain and repository classes
- **Gate**: Embedding generation test command passes

### Week 3-4: Product Sync (Phase 1)
- Implement Doctrine event listeners
- Sync service with error handling
- Async queue for failures
- Manual re-sync commands
- **Gate**: Create/update/delete product triggers sync successfully

### Week 5-6: Semantic Search (Phase 2)
- Semantic search service
- MongoDB vector similarity search
- Result enrichment from MySQL
- Search mode switching
- **Gate**: Semantic search returns relevant results for test queries

### Week 7: Caching & VA Integration (Phases 3-4)
- Redis query embedding cache
- Symfony AI tool for VA
- Context enrichment
- **Gate**: Cache hit rate >50%, VA semantic tool works

### Week 8: Testing & Production Prep (Phases 5-7)
- Performance optimization
- Error handling hardening
- Comprehensive testing
- Documentation
- **Gate**: All tests pass, docs complete

### Week 9: Production Deployment (Phase 8)
- MongoDB Atlas setup
- Initial embedding sync
- Monitoring and alerting
- Gradual rollout (10% → 50% → 100%)
- **Gate**: Production stable, metrics within SLA

---

## Open Questions

1. **MongoDB Hosting**: Self-hosted or MongoDB Atlas? (Atlas recommended for vector search features)
2. **Initial Catalog Size**: How many products need initial embedding sync? (Affects deployment timeline)
3. **Feature Flag Strategy**: Which tool for gradual rollout? (LaunchDarkly, Unleash, or custom?)
4. **Search UI Updates**: Does frontend need redesign for semantic search mode toggle? (Out of spec scope currently)
5. **Analytics Integration**: Track semantic vs keyword search usage in GA4 or similar? (Nice to have)

---

## Appendix: MongoDB Schema

```javascript
// Collection: product_embeddings
{
  "_id": ObjectId("..."),
  "product_id": 123,  // Reference to MySQL products.id
  "embedding": [0.123, -0.456, ...],  // 1536 floats for text-embedding-3-small
  "name": "Gaming Laptop XYZ",  // Denormalized for debugging
  "description": "High-performance portable computer...",  // Denormalized
  "category": "Electronics > Computers",  // Denormalized
  "metadata": {
    "model": "text-embedding-3-small",
    "model_version": "v3",
    "dimensions": 1536,
    "generated_at": ISODate("2026-02-07T14:00:00Z")
  },
  "updated_at": ISODate("2026-02-07T14:00:00Z")
}

// Indexes
db.product_embeddings.createIndex({ "product_id": 1 }, { unique: true })
db.product_embeddings.createIndex({ "embedding": "vectorSearch" })
db.product_embeddings.createIndex({ "updated_at": -1 })
```

---

**Approval Required Before Proceeding to Implementation**  
Review by: Product Owner, Tech Lead, DevOps Engineer  
Estimated Review Date: February 8, 2026  
Target Implementation Start: February 10, 2026
