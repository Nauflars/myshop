# Tasks: Semantic Product Search with Symfony AI & OpenAI Embeddings

**Feature Branch**: `010-semantic-search`  
**Input**: Design documents from `/specs/010-semantic-search/`  
**Prerequisites**: spec.md (user stories and functional requirements), spec-002 (Virtual Assistant), spec-009 (Context Memory)

**Tech Stack**: Symfony PHP 8.3, MySQL, MongoDB, Redis, OpenAI API, Symfony AI Bundle, Domain-Driven Design (DDD)  
**Architecture**: Domain/Application/Infrastructure layers with event-driven sync

**Dependencies**: Builds on existing Product entity (MySQL), Virtual Assistant tools, Redis caching infrastructure

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

---

## Phase 0: Infrastructure Setup (Prerequisites for all user stories)

**Purpose**: Set up MongoDB, OpenAI client, and base embedding infrastructure

**Goal**: MongoDB running with vector index, OpenAI API connected, base domain classes created

**Independent Test**: Start MongoDB container, verify connection via Symfony console command, call OpenAI embeddings API with test text, receive 1536-dimension vector, store in MongoDB, retrieve by product_id.

### Implementation for Infrastructure

- [ ] T001 [P] Add MongoDB service to docker-compose.yml (image: mongo:7.0, port 27017, persistent volume)
- [ ] T002 [P] Add mongodb/mongodb package to composer.json (^1.17)
- [ ] T003 [P] Configure MongoDB connection in config/packages/mongodb.yaml (connection string, database name)
- [ ] T004 [P] Create ProductEmbedding entity in src/Domain/Entity/ProductEmbedding.php (product_id, embedding array, metadata)
- [ ] T005 [P] Create EmbeddingServiceInterface in src/Domain/Repository/EmbeddingServiceInterface.php
- [ ] T006 [P] Create OpenAIEmbeddingService implementation in src/Infrastructure/AI/Service/OpenAIEmbeddingService.php
- [ ] T007 [P] Create MongoDBEmbeddingRepository in src/Infrastructure/Repository/MongoDBEmbeddingRepository.php
- [ ] T008 Add OpenAI API configuration to .env (OPENAI_API_KEY, OPENAI_EMBEDDING_MODEL=text-embedding-3-small)
- [ ] T009 Create MongoDB vector index via console command in src/Command/CreateVectorIndexCommand.php
- [ ] T010 Create Symfony console command to test embedding generation in src/Command/TestEmbeddingCommand.php
- [ ] T011 Write unit tests for OpenAIEmbeddingService in tests/Unit/Infrastructure/AI/Service/

**Checkpoint**: MongoDB running, OpenAI API connected, embeddings can be generated and stored

---

## Phase 1: Product Embedding Synchronization (User Story 2 - Priority P1)

**Purpose**: Automatically sync product data to MongoDB embeddings when products change

**Goal**: Admin creates/updates/deletes product → embedding auto-generated → MongoDB synced

**Independent Test**: Create product "Gaming Laptop XYZ" via admin panel. Verify MySQL product table has record. Query MongoDB product_embeddings collection, confirm document exists with matching product_id and 1536-dimension embedding array. Update product description, verify MongoDB embedding updates. Delete product, verify MongoDB document deleted.

### Implementation for User Story 2

- [ ] T012 [P] Create ProductEmbeddingSyncService in src/Application/Service/ProductEmbeddingSyncService.php
- [ ] T013 [P] Define SyncProductEmbedding use case in src/Application/UseCase/SyncProductEmbedding.php
- [ ] T014 Create Doctrine event listener PostPersist for Product in src/Infrastructure/Persistence/Listener/ProductEmbeddingListener.php
- [ ] T015 Create Doctrine event listener PostUpdate for Product in ProductEmbeddingListener.php
- [ ] T016 Create Doctrine event listener PostRemove for Product in ProductEmbeddingListener.php
- [ ] T017 [P] Implement embedding generation logic in ProductEmbeddingSyncService::generateEmbedding()
- [ ] T018 [P] Implement MongoDB create operation in ProductEmbeddingSyncService::createEmbedding()
- [ ] T019 [P] Implement MongoDB update operation in ProductEmbeddingSyncService::updateEmbedding()
- [ ] T020 [P] Implement MongoDB delete operation in ProductEmbeddingSyncService::deleteEmbedding()
- [ ] T021 Add error handling and retry logic for OpenAI API failures in ProductEmbeddingSyncService
- [ ] T022 Add error handling for MongoDB write failures (log error, continue with MySQL operation)
- [ ] T023 Create Symfony Messenger message for async embedding sync in src/Application/Message/SyncEmbeddingMessage.php
- [ ] T024 Create Messenger handler for async sync in src/Application/MessageHandler/SyncEmbeddingHandler.php
- [ ] T025 Add logging for all sync operations (success, failure, retry) using Symfony Logger
- [ ] T026 Create console command for manual re-sync in src/Command/SyncAllEmbeddingsCommand.php
- [ ] T027 Create console command for batch initial sync in src/Command/BatchSyncEmbeddingsCommand.php
- [ ] T028 Write integration tests for product sync lifecycle in tests/Integration/ProductEmbedding/
- [ ] T029 Write tests for sync failure scenarios (OpenAI down, MongoDB down, network timeout)

**Checkpoint**: Products auto-sync to MongoDB embeddings, manual re-sync command works, failures handled gracefully

---

## Phase 2: Semantic Search Service (User Story 1 - Priority P1)

**Purpose**: Implement semantic search that accepts natural language queries and returns ranked products

**Goal**: User searches "laptop for gaming" → query embedded → MongoDB vector search → results ranked by similarity

**Independent Test**: Send search request with query "affordable phone for photography". Service generates embedding via OpenAI, performs MongoDB $vectorSearch aggregation, returns products sorted by similarity score (0.0-1.0). Verify results include semantically similar products even if exact keywords don't match descriptions.

### Implementation for User Story 1

- [ ] T030 [P] Create SearchQuery value object in src/Domain/ValueObject/SearchQuery.php
- [ ] T031 [P] Create SearchResult value object in src/Domain/ValueObject/SearchResult.php (products, scores, mode)
- [ ] T032 [P] Create SemanticSearchService in src/Application/Service/SemanticSearchService.php
- [ ] T033 Implement query embedding generation in SemanticSearchService::generateQueryEmbedding()
- [ ] T034 Implement MongoDB vector similarity search in MongoDBEmbeddingRepository::searchBySimilarity()
- [ ] T035 Implement cosine similarity calculation for ranking in MongoDBEmbeddingRepository
- [ ] T036 Implement result enrichment with MySQL data in SemanticSearchService::enrichResults()
- [ ] T037 Add pagination support (limit, offset) in MongoDB search query
- [ ] T038 Add minimum similarity threshold filtering (e.g., score > 0.6) in SemanticSearchService
- [ ] T039 Add result deduplication logic (same product from multiple embeddings)
- [ ] T040 Create KeywordSearchService for traditional MySQL search in src/Application/Service/KeywordSearchService.php
- [ ] T041 Implement MySQL LIKE queries in KeywordSearchService::searchByKeyword()
- [ ] T042 Create SearchFacade to route between semantic/keyword modes in src/Application/Service/SearchFacade.php
- [ ] T043 Add search mode validation (keyword, semantic) in SearchFacade
- [ ] T044 Update ProductController with semantic search endpoint in src/Infrastructure/Controller/ProductController.php
- [ ] T045 Add search mode parameter (?mode=semantic or ?mode=keyword) to search endpoint
- [ ] T046 Add error handling for embedding generation failures (fallback to keyword search)
- [ ] T047 Add error handling for MongoDB unavailability (fallback to keyword search with warning)
- [ ] T048 Write integration tests for semantic search end-to-end in tests/Integration/Search/
- [ ] T049 Write integration tests for keyword search in tests/Integration/Search/
- [ ] T050 Write tests for search mode switching in tests/Integration/Search/

**Checkpoint**: Semantic search works end-to-end, keyword search works, mode switching functional, fallbacks handle errors

---

## Phase 3: Redis Caching for Query Embeddings (User Story 5 - Priority P3)

**Purpose**: Cache query embeddings in Redis to reduce OpenAI API calls and improve response time

**Goal**: Repeated query "laptop" uses cached embedding instead of calling OpenAI API

**Independent Test**: Search "laptop" with ?mode=semantic, measure OpenAI API call (verify via logs). Search "laptop" again within cache TTL, verify no OpenAI API call (cache hit). Check Redis for key `search:embedding:{hash}` with embedding value. Wait for TTL expiration, search again, verify new API call and cache refresh.

### Implementation for User Story 5

- [ ] T051 [P] Create EmbeddingCacheService in src/Application/Service/EmbeddingCacheService.php
- [ ] T052 [P] Define cache key format: `search:embedding:{md5(query)}` in EmbeddingCacheService
- [ ] T053 Implement cache check before OpenAI API call in SemanticSearchService::generateQueryEmbedding()
- [ ] T054 Implement cache write after successful embedding generation in EmbeddingCacheService::set()
- [ ] T055 Add configurable TTL for embedding cache in .env (EMBEDDING_CACHE_TTL=3600)
- [ ] T056 Add cache serialization (JSON encode embedding array) in EmbeddingCacheService
- [ ] T057 Add cache deserialization (JSON decode to array) in EmbeddingCacheService
- [ ] T058 Add Redis connection error handling (bypass cache, continue with OpenAI) in EmbeddingCacheService
- [ ] T059 Add cache invalidation logic (optional: clear cache on command) in src/Command/ClearEmbeddingCacheCommand.php
- [ ] T060 Add cache hit/miss metrics logging for monitoring in EmbeddingCacheService
- [ ] T061 Write unit tests for EmbeddingCacheService in tests/Unit/Application/Service/
- [ ] T062 Write integration tests for cache behavior in tests/Integration/Search/

**Checkpoint**: Query embeddings cached in Redis, cache hits reduce API calls, Redis failures handled gracefully

---

## Phase 4: Virtual Assistant Integration (User Story 3 - Priority P2)

**Purpose**: Enable VA to use semantic search via AI tool for conversational product discovery

**Goal**: Customer asks VA "show me gear for streaming" → VA calls semantic search tool → returns relevant products

**Independent Test**: Start chat with VA, send message "I need something for home office". Verify VA calls SemanticProductSearchTool with query. Tool performs semantic search, returns results. VA presents products conversationally. Check that customer context (spec-009) enriches search if applicable.

### Implementation for User Story 3

- [ ] T063 [P] Create SemanticProductSearchTool in src/Infrastructure/AI/Tool/SemanticProductSearchTool.php
- [ ] T064 Implement tool description and parameters for Symfony AI Agent in SemanticProductSearchTool
- [ ] T065 Implement tool execute() method that calls SemanticSearchService in SemanticProductSearchTool
- [ ] T066 Add context enrichment: pass customer context to semantic search in SemanticProductSearchTool
- [ ] T067 Format search results for VA consumption (structured product list) in SemanticProductSearchTool
- [ ] T068 Add empty results handling (return friendly message to VA) in SemanticProductSearchTool
- [ ] T069 Update VA agent configuration to include SemanticProductSearchTool in config/packages/ai.yaml
- [ ] T070 Add tool call logging for debugging in SemanticProductSearchTool
- [ ] T071 Update CustomerContextManager to track semantic search usage in context updates
- [ ] T072 Write integration tests for VA calling semantic search tool in tests/Integration/AI/
- [ ] T073 Write tests for context-enriched semantic search in tests/Integration/AI/

**Checkpoint**: VA successfully uses semantic search tool, context enrichment works, results formatted correctly

---

## Phase 5: Performance Optimization & Monitoring (Enhancements)

**Purpose**: Optimize search performance and add monitoring for production readiness

**Goal**: Semantic search <2s for cached queries, <5s for uncached, monitoring in place

**Independent Test**: Run load test with 100 concurrent semantic search requests. Measure p95 response time (should be <5s). Monitor OpenAI API usage, verify rate limits not exceeded. Check MongoDB query performance, verify vector index used. Review logs for errors or slow queries.

### Implementation for Performance

- [ ] T074 [P] Add Symfony Stopwatch profiling to semantic search in SemanticSearchService
- [ ] T075 [P] Create SearchMetricsCollector service in src/Application/Service/SearchMetricsCollector.php
- [ ] T076 Add response time tracking (p50, p95, p99) in SearchMetricsCollector
- [ ] T077 Add OpenAI API call counter and cost estimation in SearchMetricsCollector
- [ ] T078 Add MongoDB query performance tracking in MongoDBEmbeddingRepository
- [ ] T079 Optimize MongoDB query with projection (only return needed fields) in MongoDBEmbeddingRepository
- [ ] T080 Add query result limit (max 50 products) to prevent large result sets in SemanticSearchService
- [ ] T081 Implement connection pooling for MongoDB in config/packages/mongodb.yaml
- [ ] T082 Add OpenAI API rate limit monitoring and alerting in OpenAIEmbeddingService
- [ ] T083 Add batch embedding generation for initial catalog sync in BatchSyncEmbeddingsCommand
- [ ] T084 Optimize description text before embedding (remove HTML, truncate to token limit) in ProductEmbeddingSyncService
- [ ] T085 Add database indexes on Product.updated_at for sync queries in migration
- [ ] T086 Implement circuit breaker pattern for OpenAI API failures in OpenAIEmbeddingService
- [ ] T087 Add health check endpoint for MongoDB and OpenAI connectivity in src/Infrastructure/Controller/HealthController.php
- [ ] T088 Write performance tests (load testing) in tests/Performance/
- [ ] T089 Create admin dashboard widget showing search metrics in templates/admin/dashboard.html.twig
- [ ] T090 Document performance benchmarks and tuning recommendations in specs/010-semantic-search/PERFORMANCE.md

**Checkpoint**: Search performance meets SLA (<5s p95), monitoring dashboards functional, production-ready

---

## Phase 6: Error Handling & Reliability (Production Hardening)

**Purpose**: Ensure system degrades gracefully under failure conditions

**Goal**: System remains operational even when MongoDB or OpenAI unavailable

**Independent Test**: Stop MongoDB container, attempt semantic search. Verify fallback to keyword search with user-friendly error. Stop OpenAI API access (simulate network failure), create product. Verify MySQL save succeeds, embedding queued for retry. Restart MongoDB/OpenAI, verify queued jobs process successfully.

### Implementation for Reliability

- [ ] T091 [P] Implement retry logic with exponential backoff for OpenAI API in OpenAIEmbeddingService
- [ ] T092 [P] Implement circuit breaker for MongoDB failures in MongoDBEmbeddingRepository
- [ ] T093 Add fallback to keyword search when semantic search fails in SearchFacade
- [ ] T094 Add user-friendly error messages (no technical details) in ProductController
- [ ] T095 Implement dead letter queue for failed embedding sync jobs in config/packages/messenger.yaml
- [ ] T096 Add alerting for high failure rates (>10% failures in 5 minutes) using logging
- [ ] T097 Create admin command to retry failed jobs in src/Command/RetryFailedEmbeddingsCommand.php
- [ ] T098 Add detailed error logging (product_id, query, API response, stack trace)
- [ ] T099 Implement request timeout for OpenAI API calls (5 seconds) in OpenAIEmbeddingService
- [ ] T100 Implement request timeout for MongoDB queries (3 seconds) in MongoDBEmbeddingRepository
- [ ] T101 Add validation for embedding dimensions (must be 1536) in OpenAIEmbeddingService
- [ ] T102 Add validation for product description length (max 8191 tokens) before embedding
- [ ] T103 Write tests for all failure scenarios in tests/Integration/ErrorHandling/
- [ ] T104 Document error handling strategy in specs/010-semantic-search/ERROR_HANDLING.md

**Checkpoint**: System degrades gracefully, errors logged properly, retry mechanisms work, production-hardened

---

## Phase 7: Testing & Documentation (Final Validation)

**Purpose**: Comprehensive testing and documentation for production deployment

**Goal**: All acceptance criteria met, tests passing, documentation complete

**Independent Test**: Run full test suite (unit, integration, e2e). Execute manual test scenarios from spec.md acceptance criteria. Deploy to staging environment, run smoke tests. Verify all 10 success criteria (SC-001 to SC-010) met. Review documentation completeness.

### Implementation for Validation

- [ ] T105 [P] Write unit tests for all domain value objects in tests/Unit/Domain/ValueObject/
- [ ] T106 [P] Write unit tests for all application services in tests/Unit/Application/Service/
- [ ] T107 [P] Write integration tests for product sync flow in tests/Integration/ProductEmbedding/
- [ ] T108 [P] Write integration tests for search flow in tests/Integration/Search/
- [ ] T109 [P] Write integration tests for VA integration in tests/Integration/AI/
- [ ] T110 Write end-to-end tests simulating user journeys in tests/E2E/
- [ ] T111 Create test data fixtures (sample products with embeddings) in tests/Fixtures/
- [ ] T112 Write tests for edge cases documented in spec.md in tests/Unit/EdgeCases/
- [ ] T113 Implement search quality tests (expected results for curated queries) in tests/Functional/
- [ ] T114 Create admin documentation for semantic search features in docs/ADMIN_GUIDE.md
- [ ] T115 Create developer documentation for extending semantic search in docs/DEVELOPER_GUIDE.md
- [ ] T116 Document API endpoints and parameters in docs/API.md
- [ ] T117 Document MongoDB schema and indexes in docs/DATABASE_SCHEMA.md
- [ ] T118 Document OpenAI API usage and cost estimation in docs/COST_ESTIMATION.md
- [ ] T119 Create troubleshooting guide for common issues in docs/TROUBLESHOOTING.md
- [ ] T120 Update main README.md with semantic search feature description

**Checkpoint**: All tests green, documentation complete, ready for production deployment

---

## Phase 8: Production Deployment & Monitoring (Launch)

**Purpose**: Deploy to production and establish monitoring

**Goal**: Feature live in production, metrics tracked, costs monitored

**Independent Test**: Deploy to production, verify all services running (MongoDB, Redis, OpenAI connectivity). Run initial embedding sync for all products. Enable semantic search in production. Monitor first 1000 queries, verify response times meet SLA. Check OpenAI API costs, confirm under budget ($50/month). Review error logs, confirm <1% error rate.

### Implementation for Deployment

- [ ] T121 Create production environment configuration in .env.prod
- [ ] T122 Set up MongoDB Atlas cluster or production MongoDB instance
- [ ] T123 Configure MongoDB replication and backups for production
- [ ] T124 Set up OpenAI API production key with appropriate rate limits
- [ ] T125 Configure Redis persistence for production (AOF or RDB)
- [ ] T126 Run initial batch embedding sync for all existing products
- [ ] T127 Verify MongoDB vector index created successfully in production
- [ ] T128 Set up monitoring dashboards (Grafana, DataDog, or similar)
- [ ] T129 Configure alerting for critical metrics (error rate, response time, API costs)
- [ ] T130 Set up log aggregation (ELK stack or similar) for centralized logging
- [ ] T131 Configure rate limiting for search endpoints (60 req/min per user)
- [ ] T132 Run smoke tests in production after deployment
- [ ] T133 Enable feature flag for gradual rollout (10% → 50% → 100% traffic)
- [ ] T134 Monitor search quality metrics (CTR, user satisfaction surveys)
- [ ] T135 Set up cost tracking dashboard for OpenAI API usage
- [ ] T136 Document production runbook (deployment, rollback, incident response)
- [ ] T137 Schedule post-launch review meeting to gather feedback

**Checkpoint**: Production deployment successful, monitoring operational, feature live and stable

---

## Summary

**Total Tasks**: 137  
**Parallel Tasks**: ~40 (marked with [P])  
**Estimated Effort**: 6-8 weeks for complete implementation (assuming 1 developer)

**Critical Path**: Phase 0 → Phase 1 → Phase 2 → Phase 6 → Phase 7  
**Can be done in parallel**: Phase 3 (caching), Phase 4 (VA integration), Phase 5 (monitoring)

**Key Risks**:
- OpenAI API rate limits or cost overruns
- MongoDB vector search performance at scale
- Product catalog sync consistency issues
- Complex debugging for embedding quality problems

**Next Steps**:
1. Review and approve task breakdown
2. Estimate time for each task
3. Assign tasks to developers
4. Start with Phase 0 infrastructure setup
5. Implement phases incrementally with testing after each checkpoint
