# Tasks: Personalized User Recommendations with Vector Embeddings

**Feature Branch**: `013-user-recommendations`  
**Input**: Design documents from `/specs/013-user-recommendations/`  
**Prerequisites**: spec.md (user stories), plan.md (architecture), spec-010 (semantic search - product embeddings)

**Tech Stack**: Symfony PHP 8.3, MySQL, MongoDB, Redis, OpenAI API, Symfony AI Bundle, Domain-Driven Design (DDD)  
**Architecture**: Domain/Application/Infrastructure layers with event-driven profile updates

**Dependencies**: spec-010 (product embeddings in MongoDB), User/Order entities (MySQL), Redis caching

## Format: `[ID] [P?] [Story/Phase] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: User story (US1=Personalized Home,US2=Real-time Updates, US3=Transparency)
- **[Phase]**: Implementation phase number
- Include exact file paths in descriptions

---

## Phase 0: Prerequisites Verification

**Purpose**: Verify existing infrastructure is ready for recommendations

**Goal**: Confirm MongoDB with product embeddings, OpenAI API, User/Order entities exist

**Independent Test**: Query MongoDB product_embeddings collection, verify documents exist. Check MySQL users and orders tables have data. Call OpenAI embeddings API with test text.

### Verification Tasks

- [X] T000 Verify MongoDB is running and accessible from PHP container
- [X] T001 Verify product_embeddings collection exists in MongoDB with vector index
- [X] T002 Verify OpenAI API key is configured and embeddings endpoint is accessible
- [X] T003 Verify MySQL User entity has id, email, orders relationship
- [X] T004 Verify MySQL Order entity has user relationship, orderItems, completedAt timestamp
- [X] T005 Verify Redis is running and accessible for caching

**Checkpoint**: All dependencies confirmed, ready for implementation

---

## Phase 1: Core Infrastructure Setup (Days 1-2)

**Purpose**: Create user profile entity and MongoDB storage

**Goal**: Can create, store, and retrieve user profiles from MongoDB

**Independent Test**: Create UserProfile object with userId and embeddingVector array (1536 dimensions). Save to MongoDB user_profiles collection. Retrieve by userId. Verify data integrity.

### Domain Layer

- [X] T010 [P] [US1] [Phase1] Create UserProfile entity in `src/Domain/Entity/UserProfile.php`
  - Properties: userId (string), embeddingVector (array), lastUpdated (DateTimeImmutable)
  - Properties: dataSnapshot (array), metadata (array), createdAt, updatedAt
  - Methods: getUserId(), getEmbeddingVector(), getLastUpdated(), getDataSnapshot()
  - Validation: embeddingVector must be array of 1536 floats

- [X] T011 [P] [US1] [Phase1] Create ProfileSnapshot value object in `src/Domain/ValueObject/ProfileSnapshot.php`
  - Properties: recentPurchases (array), recentSearches (array), dominantCategories (array)
  - Methods: getRecentPurchases(), getRecentSearches(), getDominantCategories()
  - Immutable value object pattern

- [X] T012 [P] [US1] [Phase1] Create RecommendationResult value object in `src/Domain/ValueObject/RecommendationResult.php`
  - Properties: products (array of Product), similarityScores (array), generatedAt (DateTimeImmutable)
  - Methods: getProducts(), getScores(), count(), isEmpty()

- [X] T013 [P] [US1] [Phase1] Create UserProfileRepositoryInterface in `src/Domain/Repository/UserProfileRepositoryInterface.php`
  - Methods: findByUserId(string): ?UserProfile
  - Methods: save(UserProfile): void, delete(string $userId): void
  - Methods: findSimilarProducts(array $embedding, int $limit): array

###Infrastructure Layer

- [X] T014 [US1] [Phase1] Create MongoDBUserProfileRepository in `src/Infrastructure/Repository/MongoDBUserProfileRepository.php`
  - Implement UserProfileRepositoryInterface
  - Inject MongoDB client and database name
  - Collection: user_profiles
  - Method: findByUserId() - query by userId field
  - Method: save() - upsert profile document
  - Method: delete() - remove by userId

- [X] T015 [US1] [Phase1] Implement findSimilarProducts() in MongoDBUserProfileRepository
  - Use MongoDB $vectorSearch aggregation pipeline
  - Query product_embeddings collection
  - Match against user embeddingVector
  - Return top N products by cosine similarity
  - Include similarity scores in results

- [X] T016 [P] [US1] [Phase1] Configure MongoDB collection and indexes
  - Create user_profiles collection if not exists
  - Create unique index on userId: `db.user_profiles.createIndex({userId: 1}, {unique: true})`
  - Create index on lastUpdated: `db.user_profiles.createIndex({lastUpdated: 1})`
  - Create vector search index on embeddingVector field

- [X] T017 [P] [US1] [Phase1] Create console command CreateUserProfileIndexesCommand in `src/Infrastructure/Command/CreateUserProfileIndexesCommand.php`
  - Command name: `app:user-profile:create-indexes`
  - Create all required MongoDB indexes
  - Verify vector search index configuration
  - Output success/failure messages

### Testing

- [ ] T018 [P] [US1] [Phase1] Write unit tests for UserProfile entity in `tests/Unit/Domain/Entity/UserProfileTest.php`
  - Test entity creation with valid data
  - Test validation (embedding must be 1536 dimensions)
  - Test getters and setters

- [ ] T019 [P] [US1] [Phase1] Write integration tests for MongoDBUserProfileRepository in `tests/Integration/Infrastructure/Repository/MongoDBUserProfileRepositoryTest.php`
  - Test save() operation
  - Test findByUserId() with existing profile
  - Test findByUserId() with non-existent profile
  - Test delete() operation
  - Test findSimilarProducts() with mock embedding

**Checkpoint**: Can create and store user profiles in MongoDB, retrieve by userId, query similar products

---

## Phase 2: Profile Generation Service (Days 3-4)

**Purpose**: Aggregate user data and generate embeddings

**Goal**: Given userId, aggregate purchases/searches, generate embedding via OpenAI

**Independent Test**: Call `UserProfileService::refreshProfile(userId)` with user who has 5 purchases and 10 searches. Verify service: (1) fetches purchases from MySQL Orders, (2) fetches searches from conversation history, (3) builds text representation, (4) calls OpenAI API, (5) saves to MongoDB with correct weights.

### Application Layer

- [ ] T020 [P] [US2] [Phase2] Create ProfileAggregationService in `src/Application/Service/ProfileAggregationService.php`
  - Inject OrderRepository and ProductRepository
  - Method: aggregatePurchases(string $userId): array - fetch last 20 orders
  - Method: aggregateSearches(string $userId): array - fetch last 50 searches from Redis/MySQL
  - Method: buildTextRepresentation(array $purchases, array $searches): string
  - Apply weighted importance: purchases (70%), searches (20%), views (10%)

- [ ] T021 [US2] [Phase2] Implement aggregatePurchases() in ProfileAggregationService
  - Query MySQL: `SELECT p.name, p.description, p.category FROM orders o JOIN order_items oi ON o.id = oi.order_id JOIN products p ON oi.product_id = p.id WHERE o.user_id = ? ORDER BY o.completed_at DESC LIMIT 20`
  - Return array of [productName, category, purchasedAt]
  - Apply recency weight (more recent = higher weight)

- [ ] T022 [US2] [Phase2] Implement aggregateSearches() in ProfileAggregationService
  - Query Redis conversation history for user (from spec-012)
  - Extract search queries from user messages
  - Alternatively: query MySQL conversations table if persisted
  - Return array of [query, timestamp]
  - Limit to last 50 searches

- [ ] T023 [US2] [Phase2] Implement buildTextRepresentation() in ProfileAggregationService
  - Format: "User interests: [purchased: Product A, Product B, Product C] [searched: query 1, query 2, query 3] [categories: Category1, Category2]"
  - Weight purchases higher (repeat 3x for 70% weight)
  - Weight searches medium (repeat 1x for 20% weight)
  - Add dominant categories
  - Return concatenated string

- [ ] T024 [P] [US2] [Phase2] Create UserProfileService in `src/Application/Service/UserProfileService.php`
  - Inject ProfileAggregationService, UserProfileRepository, OpenAIEmbeddingService
  - Method: refreshProfile(string $userId): UserProfile
  - Method: getRecommendations(string $userId): RecommendationResult
  - Method: generateEmbedding(string $text): array

- [ ] T025 [US2] [Phase2] Implement refreshProfile() in UserProfileService
  - Call ProfileAggregationService to get user data
  - Build text representation
  - Generate embedding via OpenAIEmbeddingService (reuse from spec-010)
  - Create or update UserProfile entity
  - Save to MongoDB via UserProfileRepository
  - Return UserProfile

- [ ] T026 [US2] [Phase2] Implement generateEmbedding() in UserProfileService
  - Call OpenAIEmbeddingService::generateEmbedding($text)
  - Verify result is array of 1536 floats
  - Handle errors (API timeout, invalid response)
  - Log embedding generation events

- [ ] T027 [P] [US2] [Phase2] Add recency decay logic for old purchases
  - Purchases older than 90 days have reduced weight
  - Formula: weight = base_weight * (1 - age_in_days / 180)
  - Apply in buildTextRepresentation()

### Testing

- [ ] T028 [P] [US2] [Phase2] Write unit tests for ProfileAggregationService in `tests/Unit/Application/Service/ProfileAggregationServiceTest.php`
  - Test aggregatePurchases() with mock data
  - Test aggregateSearches() with mock data
  - Test buildTextRepresentation() output format
  - Test recency decay calculation

- [ ] T029 [P] [US2] [Phase2] Write unit tests for UserProfileService in `tests/Unit/Application/Service/UserProfileServiceTest.php`
  - Test refreshProfile() workflow
  - Test error handling (OpenAI API failure)
  - Test embedding validation

- [ ] T030 [P] [US2] [Phase2] Write integration tests for profile generation in `tests/Integration/UserProfile/ProfileGenerationTest.php`
  - Test full workflow: userId → aggregation → embedding → MongoDB save
  - Test with user having purchases only
  - Test with user having searches only
  - Test with user having no history (should create profile with empty snapshot)

**Checkpoint**: Can generate user profile embeddings from MySQL/Redis data, save to MongoDB

---

## Phase 3: Real-time Profile Updates (Days 5-6)

**Purpose**: Trigger profile regeneration on user actions

**Goal**: Order completion or search triggers async profile refresh within 5 seconds

**Independent Test**: Complete an order for a user. Verify Doctrine PostPersist event fires. Verify async message dispatched to Messenger. Verify handler executes within 5s. Verify MongoDB profile updated with new purchase reflected in dataSnapshot.

### Event Subscribers

- [ ] T031 [P] [US2] [Phase3] Create OrderEventSubscriber in `src/Application/EventSubscriber/OrderEventSubscriber.php`
  - Listen to Doctrine PostPersist event for Order entity
  - Filter only completed orders (check $order->getStatus() === 'completed')
  - Dispatch RefreshUserProfileMessage to Messenger
  - Log event for monitoring

- [ ] T032 [P] [US2] [Phase3] Create SearchEventSubscriber in `src/Application/EventSubscriber/SearchEventSubscriber.php`
  - Listen to custom SearchPerformedEvent (create if needed)
  - Extract userId from event
  - Dispatch RefreshUserProfileMessage to Messenger
  - Throttle: only trigger if profile is stale (> 1 hour old)

### Async Messaging

- [ ] T033 [P] [US2] [Phase3] Create RefreshUserProfileMessage in `src/Application/Message/RefreshUserProfileMessage.php`
  - Property: userId (string)
  - Readonly, immutable message class
  - Method: getUserId()

- [ ] T034 [US2] [Phase3] Create RefreshUserProfileHandler in `src/Application/MessageHandler/RefreshUserProfileHandler.php`
  - Inject UserProfileService
  - Method: __invoke(RefreshUserProfileMessage $message)
  - Call UserProfileService::refreshProfile($message->getUserId())
  - Handle errors gracefully (log and continue)
  - Add retry logic (3 attempts with exponential backoff)

- [ ] T035 [US2] [Phase3] Configure Messenger routing in `config/packages/messenger.yaml`
  - Route RefreshUserProfileMessage to 'async' transport
  - Configure retry strategy: max_retries: 3, delay: 1000, multiplier: 2
  - Configure transport DSN from env var

- [ ] T036 [P] [US2] [Phase3] Create SearchPerformedEvent (if not exists) in `src/Domain/Event/SearchPerformedEvent.php`
  - Property: userId, query, timestamp
  - Dispatch from SearchFacade after search execution

### Testing

- [ ] T037 [P] [US2] [Phase3] Write integration test for order → profile update in `tests/Integration/UserProfile/OrderToProfileUpdateTest.php`
  - Create order for user
  - Trigger PostPersist event manually
  - Verify message dispatched
  - Consume message synchronously (test mode)
  - Verify MongoDB profile updated

- [ ] T038 [P] [US2] [Phase3] Write integration test for search → profile update in `tests/Integration/UserProfile/SearchToProfileUpdateTest.php`
  - Perform search for authenticated user
  - Verify event dispatched
  - Verify message consumed
  - Verify profile updated

**Checkpoint**: Profile automatically refreshes within 5 seconds after purchases or searches

---

## Phase 4: Recommendation Retrieval Service (Days 7-8)

**Purpose**: Query MongoDB for similar products based on user profile

**Goal**: Given userId, retrieve personalized product recommendations

**Independent Test**: Call `RecommendationService::getRecommendationsForUser(userId)`. Verify: (1) fetches user profile from MongoDB or cache, (2) queries product embeddings via vector search, (3) enriches with MySQL data (price, images, stock), (4) returns RecommendationResult with 20 products and similarity scores.

### Application Layer

- [ ] T040 [P] [US1] [Phase4] Create RecommendationService in `src/Application/Service/RecommendationService.php`
  - Inject UserProfileRepository, RedisProfileCacheRepository, ProductRepository
  - Method: getRecommendationsForUser(string $userId): RecommendationResult
  - Method: enrichWithMySQLData(array $productIds): array
  - Method: getFallbackRecommendations(): array

- [ ] T041 [US1] [Phase4] Implement getRecommendationsForUser() in RecommendationService
  - Check Redis cache first: `getCachedRecommendations($userId)`
  - If cache miss: fetch UserProfile from MongoDB
  - If no profile exists: return fallback (popular products)
  - Call UserProfileRepository::findSimilarProducts($embedding, 20)
  - Enrich results with MySQL Product data
  - Cache results in Redis (TTL: 30 minutes)
  - Return RecommendationResult

- [ ] T042 [US1] [Phase4] Implement enrichWithMySQLData() in RecommendationService
  - Receive array of product IDs from MongoDB
  - Query MySQL: `SELECT * FROM products WHERE id IN (...)` WITH id ordering preserved
  - Attach Product entities to result
  - Include: name, description, price, images, stock, category
  - Return array of enriched products

- [ ] T043 [US1] [Phase4] Implement getFallbackRecommendations() in RecommendationService
  - Return popular products: `SELECT * FROM products ORDER BY view_count DESC LIMIT 20`
  - Or return featured products: `WHERE featured = 1`
  - Or return random sample: `ORDER BY RAND() LIMIT 20`
  - Used when user has no profile or profile is empty

### Caching Layer

- [ ] T044 [P] [US1] [Phase4] Create RedisProfileCacheRepository in `src/Infrastructure/Repository/RedisProfileCacheRepository.php`
  - Inject Predis\Client
  - Method: getCachedProfile(string $userId): ?UserProfile
  - Method: setCachedProfile(string $userId, UserProfile $profile): void
  - Method: getCachedRecommendations(string $userId): ?array
  - Method: setCachedRecommendations(string $userId, array $productIds): void
  - Method: invalidateUserCache(string $userId): void

- [ ] T045 [US1] [Phase4] Implement caching methods in RedisProfileCacheRepository
  - Key format: `user_profile:{userId}` (TTL: 3600s / 1 hour)
  - Key format: `user_recommendations:{userId}` (TTL: 1800s / 30 minutes)
  - Serialize UserProfile to JSON before storing
  - Deserialize JSON to UserProfile when retrieving
  - Handle Redis connection errors gracefully (bypass cache)

- [ ] T046 [P] [US1] [Phase4] Add cache configuration in `.env`
  - `USER_PROFILE_CACHE_TTL=3600` (1 hour)
  - `RECOMMENDATIONS_CACHE_TTL=1800` (30 minutes)
  - Configure in services.yaml

### Testing

- [ ] T047 [P] [US1] [Phase4] Write unit tests for RecommendationService in `tests/Unit/Application/Service/RecommendationServiceTest.php`
  - Test getRecommendationsForUser() with existing profile
  - Test fallback behavior when profile doesn't exist
  - Test cache hit scenario
  - Test cache miss scenario

- [ ] T048 [P] [US1] [Phase4] Write integration test for recommendation retrieval in `tests/Integration/UserProfile/RecommendationRetrievalTest.php`
  - Create user with profile in MongoDB
  - Call getRecommendationsForUser()
  - Verify returns 20 products
  - Verify products are enriched with MySQL data
  - Verify similarity scores are present

- [ ] T049 [P] [US1] [Phase4] Write performance test for recommendation retrieval
  - Measure time to retrieve recommendations
  - Target: < 500ms
  - Test with cache hit (should be < 50ms)
  - Test with cache miss (should be < 500ms)

**Checkpoint**: Can retrieve personalized recommendations for any user, cache working, fallback graceful

---

## Phase 5: Home Page Integration (Days 9-10)

**Purpose**: Display personalized recommendations on home page

**Goal**: Authenticated users see "Recommended For You" section on home page

**Independent Test**: Login as user with purchase history. Navigate to http://localhost:8080/. Verify home page displays "Recommended For You" section. Verify products are relevant to user's interests. Measure page load time (< 1s).

### Controller Layer

- [ ] T050 [US1] [Phase5] Modify HomeController in `src/Infrastructure/Controller/HomeController.php`
  - Inject RecommendationService
  - In index() method: check if user is authenticated
  - If authenticated: call $recommendationService->getRecommendationsForUser($userId)
  - If not authenticated: use default product listing
  - Pass recommendations to template via $recommendations variable

- [ ] T051 [US1] [Phase5] Add error handling in HomeController
  - Wrap recommendation service call in try-catch
  - On error: log exception, use fallback products
  - Ensure home page always renders (never throw exception to user)
  - Add flash message: "Unable to load personalized recommendations"

### Template Layer

- [ ] T052 [US1] [Phase5] Update home.html.twig template in `templates/home.html.twig`
  - Add conditional block: {% if is_granted('IS_AUTHENTICATED_FULLY') and recommendations is defined %}
  - Add "Recommended For You" heading section
  - Display products in grid layout (reuse existing product card component)
  - Show similarity indicators (optional): "{{ (score * 100)|round }}% match"
  - Add fallback for unauthenticated users: "Popular Products" section

- [ ] T053 [P] [US1] [Phase5] Add loading state for recommendations
  - Add skeleton loader while fetching recommendations
  - Use CSS animation for smooth loading experience
  - Timeout: show fallback after 2 seconds if recommendations don't load

- [ ] T054 [P] [US3] [Phase5] Add recommendation explanations (P3 - optional)
  - Display reason for recommendation: "Based on your purchase: Gaming Mouse"
  - Or: "Because you searched for: wireless headphones"
  - Extract from ProfileSnapshot data
  - Show on hover or below product card

### Testing

- [ ] T055 [P] [US1] [Phase5] Write functional test for home page rendering in `tests/Functional/HomePageTest.php`
  - Test authenticated user sees recommendations
  - Test unauthenticated user sees default listing
  - Test error fallback behavior
  - Test page renders without throwing exceptions

- [ ] T056 [P] [US1] [Phase5] Write E2E test for personalized home page
  - Login as user with purchase history
  - Navigate to home page
  - Assert "Recommended For You" section exists
  - Assert products are displayed
  - Assert similarity scores are shown

- [ ] T057 [P] [US1] [Phase5] Perform frontend performance test
  - Measure home page load time with recommendations
  - Target: < 1 second total page load
  - Target: < 500ms for recommendation retrieval
  - Use browser dev tools or automated test

**Checkpoint**: Home page displays personalized recommendations for authenticated users, fallback works

---

## Phase 6: Background Maintenance (Days 11-12)

**Purpose**: Batch refresh stale profiles periodically

**Goal**: Nightly cron job refreshes profiles older than 7 days for active users

**Independent Test**: Run `php bin/console app:user-profile:refresh --stale-only`. Verify profiles older than 7 days are regenerated. Verify inactive users (> 90 days) are skipped. Verify progress bar shows completion. Check MongoDB for updated timestamps.

### Console Commands

- [ ] T060 [P] [US2] [Phase6] Create RefreshUserProfilesCommand in `src/Infrastructure/Command/RefreshUserProfilesCommand.php`
  - Command name: `app:user-profile:refresh`
  - Option: `--all` (refresh all users)
  - Option: `--stale-only` (only profiles > 7 days old)
  - Option: `--user-id=UUID` (refresh specific user)
  - Inject UserProfileService, UserRepository

- [ ] T061 [US2] [Phase6] Implement command execution logic in RefreshUserProfilesCommand
  - Query MySQL for users based on options
  - Filter active users (last activity within 90 days)
  - Loop through users, call UserProfileService::refreshProfile()
  - Display progress bar using Symfony Console ProgressBar
  - Log stats: total processed, successful, failed
  - Output summary at end

- [ ] T062 [P] [US2] [Phase6] Implement recency decay for old purchases
  - In ProfileAggregationService, apply decay to purchases older than 90 days
  - Formula: weight = base_weight * (1 - days_old / 180)
  - Purchases older than 180 days are excluded
  - Document formula in code comments

- [ ] T063 [P] [US2] [Phase6] Create CleanupStaleProfilesCommand in `src/Infrastructure/Command/CleanupStaleProfilesCommand.php`
  - Command name: `app:user-profile:cleanup`
  - Delete profiles for users deleted from MySQL
  - Delete profiles older than 1 year with no recent activity
  - Option: `--dry-run` (show what would be deleted)

### Cron Configuration

- [ ] T064 [US2] [Phase6] Configure cron job for nightly profile refresh
  - Add to crontab: `0 2 * * * php /var/www/html/bin/console app:user-profile:refresh --stale-only`
  - Runs at 2 AM daily
  - Log output to file: `>> /var/www/html/var/log/cron-profile-refresh.log 2>&1`
  - Document in README.md or deployment guide

### Testing

- [ ] T065 [P] [US2] [Phase6] Write integration test for RefreshUserProfilesCommand
  - Create users with stale profiles in test database
  - Execute command with --stale-only
  - Verify profiles are refreshed
  - Verify inactive users are skipped

- [ ] T066 [P] [US2] [Phase6] Test recency decay calculation
  - Create user with purchases at different ages
  - Generate profile
  - Verify recent purchases have full weight
  - Verify old purchases have reduced weight

**Checkpoint**: Batch refresh command works, cron job configured, recency decay implemented

---

## Phase 7: Monitoring & Observability (Days 13-14)

**Purpose**: Add logging, metrics, and admin tools

**Goal**: Can monitor profile generation, cache hit rates, recommendation performance

**Independent Test**: Run `php bin/console app:user-profile:stats`. Verify displays: total profiles, average age, cache hit rate, recommendation latency. Check log files for profile generation events.

### Logging

- [ ] T070 [P] [US2] [Phase7] Add comprehensive logging to UserProfileService
  - Log profile generation start/end with duration
  - Log OpenAI API calls and response times
  - Log errors with context (userId, error message, stack trace)
  - Use Monolog with dedicated channel: `user_recommendations`

- [ ] T071 [P] [US1] [Phase7] Add logging to RecommendationService
  - Log recommendation retrieval start/end
  - Log cache hits/misses
  - Log fallback triggers
  - Log MongoDB query performance

- [ ] T072 [P] [US2] [Phase7] Configure log rotation in `config/packages/monolog.yaml`
  - Create separate log file: `var/log/user_recommendations.log`
  - Rotate daily, keep 30 days
  - Log level: INFO for production, DEBUG for development

### Metrics

- [ ] T073 [P] [Phase7] Create UserProfileStatsCommand in `src/Infrastructure/Command/UserProfileStatsCommand.php`
  - Command name: `app:user-profile:stats`
  - Display: Total profiles in MongoDB
  - Display: Average profile age
  - Display: Profiles updated today, this week, this month
  - Display: Cache hit rate from Redis (if trackable)
  - Display: Average recommendation retrieval time

- [ ] T074 [P] [Phase7] Add metrics collection in RecommendationService
  - Track cache hits: increment counter
  - Track cache misses: increment counter
  - Track recommendation retrieval duration: record histogram
  - Store in Redis or in-memory for stats command

### Health Checks

- [ ] T075 [P] [Phase7] Create recommendation health check endpoint
  - Route: `/api/health/recommendations`
  - Check MongoDB connectivity
  - Check Redis connectivity
  - Check OpenAI API status (optional: call with dummy text)
  - Return JSON: {status: "healthy", checks: {...}}

### Documentation

- [ ] T076 [P] [Phase7] Create admin guide in `specs/013-user-recommendations/admin-guide.md`
  - Document console commands
  - Document cron job setup
  - Document troubleshooting steps
  - Document performance tuning

- [ ] T077 [P] [Phase7] Create troubleshooting guide
  - Issue: Recommendations not appearing
  - Issue: Slow home page loading
  - Issue: Profile generation errors
  - Include diagnostic commands and solutions

**Checkpoint**: Monitoring and logging in place, admin tools available, documentation complete

---

## Phase 8: Testing & Refinement (Days 15-16)

**Purpose**: Comprehensive testing and optimization

**Goal**: System handles edge cases, performance meets SLA, ready for production

**Independent Test**: Run full test suite. Execute load test (100 concurrent home page requests). Verify all tests pass. Verify performance targets met (home page < 1s, recommendations < 500ms, cache hit rate > 80%).

### Load Testing

- [ ] T080 [P] [Phase8] Create load test script for home page
  - Tool: Apache Bench or Symfony HttpClient with concurrent requests
  - Test: 100 concurrent authenticated users loading home page
  - Measure: Response time, throughput, error rate
  - Target: < 1 second 95th percentile response time

- [ ] T081 [P] [Phase8] Profile MongoDB vector search performance
  - Measure query time for findSimilarProducts()
  - Test with different vector index configurations
  - Optimize index if queries > 200ms
  - Document optimal configuration

### Edge Case Testing

- [ ] T082 [P] [Phase8] Test user with minimal history (1 purchase)
  - Verify profile generates successfully
  - Verify recommendations returned (even if generic)
  - No errors or crashes

- [ ] T083 [P] [Phase8] Test user with extensive history (100+ purchases)
  - Verify aggregation handles large datasets
  - Verify performance doesn't degrade
  - Verify text representation doesn't exceed OpenAI token limit (8191 tokens)

- [ ] T084 [P] [Phase8] Test user with conflicting interests (e.g., baby toys + gaming)
  - Verify profile captures both categories
  - Verify recommendations show diversity
  - No bias toward single category

- [ ] T085 [P] [Phase8] Test user with no recent activity (90+ days old)
  - Verify recency decay applied
  - Verify profile still generates
  - Verify recommendations don't include outdated products

### Optimization

- [ ] T086 [P] [Phase8] Tune cache TTL values
  - Experiment with different TTLs for profiles (30min, 1hr, 2hr)
  - Experiment with recommendation cache TTLs (15min, 30min, 1hr)
  - Measure cache hit rates
  - Document optimal values in .env.example

- [ ] T087 [P] [Phase8] Optimize similarity score weights
  - Test different weights: purchases (70%, 80%, 60%)
  - Test different weights: searches (20%, 15%, 30%)
  - Measure recommendation relevance (manual review or A/B test)
  - Document final weights in plan.md

- [ ] T088 [P] [Phase8] Add diversity constraints to recommendations
  - Ensure top 20 products span at least 3 categories
  - Avoid returning 20 products from same brand or category
  - Implement category balance logic in RecommendationService

### Final Testing

- [ ] T089 [Phase8] Run full integration test suite
  - All tests in tests/Integration/
  - Verify 100% pass rate
  - Fix any failing tests

- [ ] T090 [Phase8] Run full unit test suite
  - All tests in tests/Unit/
  - Verify code coverage > 80%
  - Add missing tests for uncovered code

- [ ] T091 [Phase8] Perform manual QA testing
  - Test as different user personas
  - Test all edge cases manually
  - Verify UI/UX is intuitive
  - Document any issues found

**Checkpoint**: All tests passing, performance optimized, edge cases handled, ready for deployment

---

## Deployment Checklist

### Pre-deployment

- [ ] D001 Verify MongoDB user_profiles collection created in production
- [ ] D002 Verify vector search index exists and configured correctly
- [ ] D003 Verify environment variables set in production .env
- [ ] D004 Verify Redis cache cleared before deployment
- [ ] D005 Verify Symfony Messenger worker configured and running
- [ ] D006 Verify cron job configured for nightly profile refresh
- [ ] D007 Run database migrations (if any)
- [ ] D008 Generate initial user profiles for existing users (batch command)

### Deployment

- [ ] D009 Deploy code to production server
- [ ] D010 Clear Symfony cache: `php bin/console cache:clear --env=prod`
- [ ] D011 Restart PHP-FPM: `systemctl restart php-fpm`
- [ ] D012 Restart Messenger workers: `systemctl restart messenger-worker`
- [ ] D013 Verify MongoDB connection: `php bin/console app:user-profile:stats`
- [ ] D014 Verify OpenAI API connectivity: `php bin/console app:test-embedding`
- [ ] D015 Test home page with authenticated test user
- [ ] D016 Verify recommendations displayed correctly

### Post-deployment

- [ ] D017 Monitor logs for errors: `tail -f var/log/user_recommendations.log`
- [ ] D018 Monitor profile generation metrics: `php bin/console app:user-profile:stats` (every hour for first 24hrs)
- [ ] D019 Monitor cache hit rates in Redis
- [ ] D020 Monitor home page performance (response times, error rates)
- [ ] D021 Check OpenAI API usage and costs
- [ ] D022 Collect user feedback on recommendations
- [ ] D023 Schedule post-deployment review meeting (1 week after launch)

---

## Summary

**Total Tasks**: 91 implementation tasks + 23 deployment checklist items = 114 total  
**Estimated Duration**: 16 days (2 weeks + 2 days buffer)  
**Parallelization**: Tasks marked [P] can run concurrently  
**Critical Path**: Phase 1 → Phase 2 → Phase 4 → Phase 5 (minimum for MVP)

**MVP Definition** (Phases 1, 2, 4, 5):
- User profiles generated from purchase history
- Personalized recommendations on home page
- Graceful fallback for users without history
- Basic caching for performance

**Optional Enhancements** (Phases 3, 6, 7, 8):
- Real-time profile updates (Phase 3)
- Background maintenance (Phase 6)
- Monitoring/observability (Phase 7)
- Advanced testing/optimization (Phase 8)

**Success Criteria**:
- Home page displays personalized recommendations < 1s
- Profile updates within 5s after purchase
- Top 5 recommendations have similarity > 0.7
- Cache hit rate > 80%
- CTR on recommendations 25% higher than default listing
