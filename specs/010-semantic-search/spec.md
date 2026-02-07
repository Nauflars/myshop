# Feature Specification: Semantic Product Search with Symfony AI & OpenAI Embeddings

**Feature Branch**: `010-semantic-search`  
**Created**: February 7, 2026  
**Status**: Draft  
**Input**: User description: "Semantic Product Search with Symfony AI & OpenAI Embeddings - Enable semantic product search using vector embeddings to understand natural language queries, synonyms, and context beyond keyword matching"

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Semantic Search via Standard Search Interface (Priority: P1)

A customer searching for products can use natural language queries like "laptop for gaming" or "affordable phone for photography" and receive relevant results even if the exact words don't match product descriptions. The semantic search understands intent and meaning, not just keywords.

**Why this priority**: Core value proposition of the feature. Without this, the entire semantic search infrastructure serves no purpose. Delivers immediate user value by improving search quality and can be tested independently of other features.

**Independent Test**: Customer enters "budget laptop for students" in search bar. System generates embedding, queries MongoDB vector store, returns MacBook Air (described as "affordable portable computer for education") with high similarity score. Can be tested with search endpoint alone, without VA or mode switching.

**Acceptance Scenarios**:

1. **Given** products exist with descriptions, **When** user searches "waterproof phone", **Then** system returns phones with "water-resistant" or "IP68 rating" descriptions ranked by semantic similarity
2. **Given** user searches with typo "laптop", **When** query is processed, **Then** system still returns laptop products using semantic understanding
3. **Given** user searches "gifts for tech lovers", **When** semantic search runs, **Then** system returns electronics, gadgets, and accessories semantically related to technology
4. **Given** user searches in natural language "What's good for video editing?", **When** query is processed, **Then** system returns high-performance computers and relevant accessories
5. **Given** no products match semantically, **When** search completes, **Then** system returns empty results with suggestion to try different keywords

---

### User Story 2 - Automated Product Embedding Synchronization (Priority: P1)

When administrators create, update, or delete products via the admin panel, the system automatically generates embeddings and synchronizes them to MongoDB. Administrators do not need to manually trigger embedding generation or worry about data consistency.

**Why this priority**: Critical infrastructure requirement. Without automatic sync, embedding data becomes stale immediately after product changes, rendering semantic search unreliable. Must be implemented before semantic search can be production-ready.

**Independent Test**: Admin creates new product "Stellar Gaming Laptop" with description via admin panel. System auto-generates embedding via OpenAI API, stores in MongoDB with product_id reference. Query MongoDB, verify document exists with 1536-dimensional embedding vector and matches MySQL product data. Delete product from MySQL, verify MongoDB document is also deleted.

**Acceptance Scenarios**:

1. **Given** admin creates new product in MySQL, **When** product is saved, **Then** system generates OpenAI embedding and creates MongoDB document within 5 seconds
2. **Given** admin updates product description, **When** changes are saved, **Then** system regenerates embedding and updates MongoDB document atomically
3. **Given** admin deletes product, **When** deletion completes, **Then** corresponding MongoDB embedding document is also deleted
4. **Given** OpenAI API fails during embedding generation, **When** product is saved, **Then** system logs error, saves product to MySQL (source of truth), and queues retry for embedding generation
5. **Given** MongoDB is temporarily unavailable, **When** product operations occur, **Then** MySQL operations succeed (system remains operational) and embeddings sync when MongoDB recovers

---

### User Story 3 - Semantic Search in Virtual Assistant (Priority: P2)

Customers chatting with the AI Virtual Assistant can ask product questions in natural language like "show me something good for home office setup" and receive semantically relevant product recommendations. The assistant uses semantic search behind the scenes via AI tools.

**Why this priority**: High-value enhancement to existing VA feature (spec-002). Enables conversational product discovery which aligns with chatbot's natural language interface. Secondary to basic semantic search (P1) as it depends on that foundation.

**Independent Test**: Customer chats with VA and says "I need gear for streaming". VA calls semantic search tool with query embedding, retrieves relevant products (microphones, cameras, lighting), and presents them conversationally. Can be tested via chatbot interface independently from standard search UI.

**Acceptance Scenarios**:

1. **Given** customer asks VA "What do you have for photography?", **When** VA processes request, **Then** VA uses semantic search tool to find cameras, lenses, and photography accessories
2. **Given** customer asks follow-up "show me budget options", **When** VA interprets context, **Then** VA filters semantic search results by price range and presents affordable photography equipment
3. **Given** customer asks "anything waterproof?", **When** VA searches semantically, **Then** VA returns products with water resistance features across categories
4. **Given** semantic search returns no results, **When** VA receives empty response, **Then** VA politely informs customer and suggests alternative queries
5. **Given** customer conversation context includes previous product mentions, **When** semantic search runs, **Then** VA enriches query with context for better results

---

### User Story 4 - Search Mode Switching (Priority: P2)

Users or administrators can choose between keyword-based search (fast, exact matching) and semantic search (intelligent, meaning-based) depending on their needs. The system supports both modes via a query parameter or configuration setting.

**Why this priority**: Provides flexibility and allows gradual rollout. Users who prefer traditional keyword search can keep using it, while others can opt into semantic search. Useful for testing and comparing search quality.

**Independent Test**: Send search request with `?mode=keyword&q=laptop` and verify MySQL LIKE query runs. Send search request with `?mode=semantic&q=laptop` and verify embedding generation + MongoDB vector search runs. Compare result sets, confirm different ranking and possibly different products.

**Acceptance Scenarios**:

1. **Given** user searches with `?mode=keyword`, **When** search executes, **Then** system uses traditional MySQL LIKE query and returns exact/partial keyword matches
2. **Given** user searches with `?mode=semantic`, **When** search executes, **Then** system generates query embedding and performs MongoDB vector similarity search
3. **Given** no mode parameter provided, **When** search executes, **Then** system defaults to keyword search (backward compatibility)
4. **Given** invalid mode parameter (e.g., `?mode=fuzzy`), **When** request is processed, **Then** system returns validation error or falls back to keyword mode
5. **Given** admin configures default search mode in settings, **When** users search without mode param, **Then** system respects configured default mode

---

### User Story 5 - Redis Caching for Embeddings (Priority: P3)

Frequently searched queries have their embeddings cached in Redis to avoid redundant OpenAI API calls. This reduces API costs and improves response time for popular searches.

**Why this priority**: Performance optimization that improves UX and reduces costs, but not essential for core functionality. Can be added after semantic search is proven and adopted. Represents an incremental improvement.

**Independent Test**: Search "laptop" with semantic mode, measure OpenAI API call and response time. Search "laptop" again immediately, verify no OpenAI API call (cache hit) and faster response time. Wait for TTL expiration (e.g., 1 hour), search "laptop" again, verify new API call and cache refresh.

**Acceptance Scenarios**:

1. **Given** query embedding not in cache, **When** semantic search runs, **Then** system generates embedding via OpenAI, caches in Redis with 1-hour TTL, and performs search
2. **Given** query embedding exists in cache, **When** same semantic search runs, **Then** system retrieves cached embedding, skips OpenAI call, and performs search
3. **Given** cached embedding TTL expires, **When** search runs, **Then** system regenerates embedding and updates cache
4. **Given** Redis is unavailable, **When** semantic search runs, **Then** system bypasses cache, calls OpenAI directly, and search still works (degraded mode)
5. **Given** popular queries create cache hotspots, **When** cache size grows, **Then** Redis evicts least-recently-used embeddings per configured policy

---

### Edge Cases

- What happens when OpenAI API rate limit is exceeded during high-traffic periods?
- How does system handle products with minimal or no description text for embedding generation?
- What occurs if MongoDB vector index is corrupted or not properly initialized?
- How does system behave when query embedding fails but product embeddings exist?
- What happens during MongoDB-MySQL sync when product is updated multiple times rapidly (race condition)?
- How does semantic search handle multi-language queries when products are in Spanish only?
- What occurs if embedding dimension changes (e.g., OpenAI model upgrade from 1536 to 3072 dimensions)?
- How does system handle extremely long product descriptions that exceed OpenAI token limits?
- What happens when user searches with special characters or emoji in semantic mode?

## Requirements *(mandatory)*

### Functional Requirements

#### Core Semantic Search

- **FR-001**: System MUST generate vector embeddings for product name and description using OpenAI text-embedding-3-small or text-embedding-ada-002 models
- **FR-002**: System MUST store embeddings in MongoDB with product_id reference, embedding vector (1536 dimensions), and metadata (name, description, updated_at)
- **FR-003**: System MUST perform vector similarity search using MongoDB $vectorSearch aggregation or cosine similarity calculation
- **FR-004**: System MUST return search results ranked by similarity score (0.0 to 1.0) in descending order
- **FR-005**: System MUST enrich MongoDB search results with full product data from MySQL (price, stock, images, category)

#### Data Synchronization

- **FR-006**: System MUST automatically generate embeddings when products are created in MySQL admin panel
- **FR-007**: System MUST automatically regenerate embeddings when product name or description is updated in MySQL
- **FR-008**: System MUST automatically delete MongoDB embedding documents when products are deleted from MySQL
- **FR-009**: System MUST maintain MySQL as source of truth - all business operations (pricing, stock, orders) use MySQL only
- **FR-010**: System MUST handle sync failures gracefully - MySQL operations succeed even if MongoDB/OpenAI fails
- **FR-011**: System MUST log all sync operations (success, failure, retry) for debugging and monitoring

#### Search Modes

- **FR-012**: System MUST support keyword search mode using MySQL LIKE or FULLTEXT queries on name and description columns
- **FR-013**: System MUST support semantic search mode using embedding generation + MongoDB vector search
- **FR-014**: System MUST accept search mode via query parameter `mode=keyword` or `mode=semantic`
- **FR-015**: System MUST default to keyword search when mode parameter is omitted (backward compatibility)
- **FR-016**: System MUST validate mode parameter and return 400 error or fallback for invalid values

#### Virtual Assistant Integration

- **FR-017**: System MUST provide Symfony AI tool for semantic product search callable by Customer Virtual Assistant
- **FR-018**: Semantic search tool MUST accept natural language queries and return structured product results
- **FR-019**: VA MUST use customer conversation context (from spec-009) to enrich semantic search queries
- **FR-020**: VA MUST handle empty semantic search results gracefully with conversational fallback messages

#### Performance & Caching

- **FR-021**: System MUST cache query embeddings in Redis with configurable TTL (default 1 hour)
- **FR-022**: System MUST use cached embeddings when available to reduce OpenAI API calls and latency
- **FR-023**: System MUST function in degraded mode if Redis is unavailable (bypass cache, use OpenAI directly)
- **FR-024**: Semantic search response time MUST be under 2 seconds at 95th percentile for cached queries
- **FR-025**: Semantic search response time MUST be under 5 seconds at 95th percentile for uncached queries (includes OpenAI API call)

#### Error Handling

- **FR-026**: System MUST retry OpenAI API calls up to 3 times with exponential backoff on transient failures
- **FR-027**: System MUST queue failed embedding generation jobs for async retry when OpenAI API is unavailable
- **FR-028**: System MUST log detailed error context (product_id, query, API response) for all failures
- **FR-029**: System MUST return user-friendly error messages ("Search temporarily unavailable") instead of technical errors
- **FR-030**: System MUST monitor OpenAI API usage and alert when approaching rate limits or quota

#### Security & Validation

- **FR-031**: System MUST validate and sanitize search queries to prevent injection attacks
- **FR-032**: System MUST restrict MongoDB queries to read-only operations (no writes from search endpoints)
- **FR-033**: System MUST store OpenAI API key in environment variables, never in code or database
- **FR-034**: System MUST rate-limit semantic search requests per user/IP to prevent abuse (e.g., 60 requests/minute)

### Key Entities

- **Product (MySQL)**: Source of truth for business data. Attributes: id, name, description, price, stock, category, image_url, created_at, updated_at. Relationships: belongs to Category, has many OrderItems, has many CartItems.

- **ProductEmbedding (MongoDB)**: Vector representation of product semantics. Attributes: product_id (reference to MySQL), embedding (float array, 1536 dimensions), name (denormalized), description (denormalized), category (denormalized), metadata (json), updated_at (timestamp). Indexed: product_id (unique), embedding (vector index for similarity search).

- **SearchQuery (Redis Cache)**: Cached query embeddings to optimize performance. Key format: `search:embedding:{hash(query)}`. Value: JSON with embedding vector and generated_at timestamp. TTL: 3600 seconds (1 hour).

- **EmbeddingJob (Queue - optional)**: Async job for embedding generation when sync fails. Attributes: product_id, operation (create/update/delete), retry_count, error_message, created_at. Used for reliability and resilience.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Users can search with natural language queries and receive relevant results that would not match with keyword search (e.g., "budget phone" returns products described as "affordable smartphone")
- **SC-002**: Semantic search returns results with average similarity score above 0.7 for relevant queries (measured on test dataset)
- **SC-003**: Product catalog synchronization maintains 99.9% consistency between MySQL and MongoDB (measured by comparing counts and random samples)
- **SC-004**: 95% of semantic search queries complete in under 5 seconds end-to-end (including embedding generation)
- **SC-005**: 80% of semantic search queries hit Redis cache (reducing OpenAI API usage and costs)
- **SC-006**: Search quality improvement measured by user satisfaction: 70% of users rate semantic search results as "relevant" or "very relevant" in A/B test
- **SC-007**: System handles 100 concurrent semantic search requests without degradation or errors
- **SC-008**: Zero data loss during MySQL-MongoDB synchronization over 30-day period (all products in MySQL have corresponding embeddings in MongoDB)
- **SC-009**: OpenAI API costs remain under $50/month for estimated 10,000 products and 50,000 searches/month
- **SC-010**: Virtual Assistant successfully uses semantic search tool in 95% of product-related queries (measured by tool call success rate)

## Assumptions *(optional)*

- Products have meaningful descriptions in Spanish suitable for embedding generation (minimum 10 words)
- OpenAI API remains available with current pricing and rate limits (500 requests/minute for embeddings)
- MongoDB supports vector search operations (requires MongoDB Atlas or self-hosted with vector search enabled)
- Average product catalog size is 10,000 products, requiring ~10,000 embedding generations
- Average search query length is 5-15 words, suitable for embedding without truncation
- Redis has sufficient memory for caching ~10,000 unique query embeddings (approx. 60MB)
- Network latency to OpenAI API is under 200ms for acceptable search response times
- Product descriptions do not contain sensitive or personal data that should not be sent to OpenAI

## Dependencies *(optional)*

- **Spec-002 (AI Shopping Assistant)**: Virtual Assistant must exist for FR-017 to FR-020 (VA integration)
- **Spec-009 (Context Memory)**: Customer conversation context required for context-enriched semantic search (FR-019)
- **Docker Compose**: MongoDB container must be added to existing docker-compose.yml infrastructure
- **OpenAI API Account**: Valid API key with sufficient quota for embeddings API
- **Symfony AI Bundle**: Framework support for embeddings and vector operations
- **MongoDB PHP Driver**: `mongodb/mongodb` package for PHP-MongoDB communication
- **MongoDB Atlas or Self-Hosted**: MongoDB instance with vector search capability enabled

## Out of Scope *(optional)*

- Complex queries with boolean operators (AND, OR, NOT) - use keyword search for these
- Multi-language semantic search (products and queries in different languages) - future enhancement
- Image-based semantic search (visual similarity) - requires different embedding model
- Faceted search combining semantic + filters (price, category) - can be added later
- Real-time search suggestions/autocomplete using embeddings - separate feature
- Semantic search for other entities (orders, customers, categories) - limited to products only
- Fine-tuning OpenAI models on custom product data - use pre-trained models only
- Hybrid search combining keyword + semantic scores - future optimization
- Vector database alternatives (Pinecone, Weaviate, Qdrant) - MongoDB only for now

## Notes for Implementation *(optional)*

- Use OpenAI `text-embedding-3-small` (1536 dimensions) for cost-efficiency, upgrade to `text-embedding-3-large` (3072 dimensions) if accuracy insufficient
- MongoDB vector index creation: `db.product_embeddings.createIndex({ "embedding": "vectorSearch" })`
- Consider batch embedding generation for initial product catalog (process 100 products at a time to respect rate limits)
- Implement webhook or event listener on Product entity lifecycle (postPersist, postUpdate, postRemove) for automatic sync
- Use Symfony Messenger for async embedding generation queue (reliability during high traffic)
- Monitor OpenAI API usage via logging and alerting to avoid unexpected costs
- Provide admin command for manual re-sync: `php bin/console app:sync-embeddings --force`
- Consider truncating very long descriptions (>8191 tokens) before sending to OpenAI API
- Test semantic search quality using curated query dataset with expected results
- Document embedding model version in MongoDB for future migrations if model changes
