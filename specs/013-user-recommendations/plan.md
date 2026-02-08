# Implementation Plan: Personalized User Recommendations with Vector Embeddings

**Feature Branch**: `013-user-recommendations`  
**Created**: February 8, 2026  
**Status**: Planning  
**Specification**: [spec.md](spec.md)  
**Tasks**: [tasks.md](tasks.md)

## Executive Summary

This feature introduces personalized product recommendations powered by user behavior embeddings, enabling the home page to display products tailored to each authenticated user's interests based on their purchase history, search queries, and browsing behavior.

**Business Value**:
- Increased customer engagement through personalized home page experience
- Higher conversion rates from relevant product recommendations
- Improved customer retention by demonstrating understanding of preferences
- Enhanced user experience with intelligent product discovery

**Technical Scope**:
- User profile embeddings using OpenAI embeddings API (text-embedding-3-small, 1536 dimensions)
- MongoDB storage for user profile vectors alongside product embeddings
- Real-time profile updates after purchases and searches
- Home page integration with vector similarity search
- Weighted aggregation: purchases (70%), searches (20%), views (10%)
- Graceful fallback to default product listing

---

## Technical Context

### Current System Architecture

**Database**: MySQL (users, orders, products, conversations)  
**Vector Database**: MongoDB (product embeddings, will add user profiles)  
**Cache**: Redis (conversation context, query cache)  
**Backend**: Symfony 7.3, PHP 8.3, Doctrine ORM  
**AI Infrastructure**: Symfony AI Bundle, OpenAI integration  
**Frontend**: Twig templates, Vanilla JavaScript  
**Containers**: Docker Compose (PHP-FPM, Nginx, MySQL, Redis, MongoDB)

### Existing Dependencies

- **spec-010**: Semantic Search (product embeddings in MongoDB, vector search infrastructure)
- **spec-002**: AI Shopping Assistant (conversation tracking, search queries)
- **spec-012**: Unified Conversation Memory (Redis-based context management)
- **User Entity**: `src/Domain/Entity/User.php` - existing MySQL user accounts
- **Order/OrderItem Entities**: MySQL purchase history data
- **Product Entity**: `src/Domain/Entity/Product.php` - MySQL product catalog
- **MongoDB**: Already configured with product embeddings collection

### Technology Additions

- **MongoDB user_profiles collection**: New collection for user embedding vectors
- **Doctrine Event Listeners**: PostPersist on Order entity for purchase tracking
- **Background Jobs**: Async user profile regeneration queue
- **Redis Caching**: User profile cache layer (1-hour TTL)

---

## Constitution Check

### Project Principles Alignment

**Domain-Driven Design (DDD)**:
- ✅ UserProfile entity defined in Domain layer (`Domain/Entity/UserProfile.php`)
- ✅ Use cases in Application layer (`Application/UseCase/GenerateUserProfileEmbedding.php`)
- ✅ Repository implementations in Infrastructure (`Infrastructure/Repository/MongoDBUserProfileRepository.php`)
- ✅ Clear separation: Domain logic independent of OpenAI/MongoDB

**Symfony Best Practices**:
- ✅ Service configuration via YAML
- ✅ Environment variables for configuration (MONGODB_URL, OPENAI_API_KEY)
- ✅ Symfony Messenger for async profile updates
- ✅ Event subscribers for order completion triggers
- ✅ Console commands for batch profile regeneration

**Quality Standards**:
- ✅ Unit tests for profile generation logic
- ✅ Integration tests for purchase → profile update flow
- ✅ Performance tests for home page rendering with recommendations
- ✅ Error handling with graceful fallback to default listing

**Security & Privacy**:
- ✅ User IDs only (no PII) in MongoDB embeddings
- ✅ Aggregated purchase/search data only (no sensitive details)
- ✅ GDPR compliance: user profile deletion on account deletion
- ✅ No personally identifiable information exposed in embeddings

### Red Flags Assessment

**❌ Potential Gate Breakers**:
1. **Cost Risk**: Generating embeddings for all users could be expensive
   - *Mitigation*: Lazy generation (only for active users), caching, batch regeneration only when needed
   
2. **Performance**: Home page could slow down with vector search
   - *Mitigation*: Redis caching (1-hour TTL), async profile generation, fallback to cached default listing
   
3. **Data Staleness**: User profiles could become outdated
   - *Mitigation*: Real-time updates after purchases/searches, background refresh for inactive users
   
4. **Cold Start Problem**: New users have no history for personalization
   - *Mitigation*: Graceful fallback to popular products or category-based recommendations

**Justification**: All risks have mitigations. Feature provides clear business value (personalization) and can be rolled out incrementally to small user groups first. Fallback ensures no degradation of existing experience.

---

## Architecture Overview

### Data Flow

```
┌─────────────────────────────────────────────────────────────────────┐
│                          User Actions                               │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  1. USER PROFILE GENERATION (Initial/Update)                       │
│     Customer completes order OR performs search                    │
│                         ↓                                           │
│     Doctrine Event (PostPersist on Order) OR Search event          │
│                         ↓                                           │
│     UserProfileService::refreshProfile(userId)                     │
│                         ↓                                           │
│     Aggregate user data:                                           │
│       - Last 20 purchases (MySQL Orders/OrderItems)                │
│       - Last 50 searches (Redis conversation history)              │
│       - Recently viewed products (session/Redis)                   │
│                         ↓                                           │
│     Build text representation:                                     │
│       "User interests: [purchased: X, Y, Z] [searched: A, B, C]"  │
│                         ↓                                           │
│     OpenAI Embeddings API (generate 1536-dim vector)               │
│                         ↓                                           │
│     MongoDBUserProfileRepository::save()                           │
│                         ↓                                           │
│     MongoDB user_profiles collection                               │
│                         ↓                                           │
│     Redis cache (key: user_profile:{userId}, TTL: 1h)              │
│                                                                     │
│  2. HOME PAGE RECOMMENDATIONS (Customer Visit)                     │
│     Customer visits http://localhost:8080/ (authenticated)         │
│                         ↓                                           │
│     HomeController::index()                                        │
│                         ↓                                           │
│     Check if user is authenticated                                 │
│             ↓ NO                       ↓ YES                        │
│     Return default                UserProfileService::             │
│     product listing              getRecommendations(userId)        │
│                                            ↓                        │
│                                  Check Redis cache for profile     │
│                              ↓ cache miss     ↓ cache hit           │
│                         Load from MongoDB   Use cached embedding   │
│                                            ↓                        │
│                         If no profile exists → default listing     │
│                                            ↓                        │
│                         MongoDB vector similarity search:          │
│                         $vectorSearch on product_embeddings        │
│                         against user profile embedding             │
│                                            ↓                        │
│                         Top 20 products by cosine similarity       │
│                                            ↓                        │
│                         Enrich with MySQL data (price, images)     │
│                                            ↓                        │
│                         Render home.html.twig with recommendations │
│                                                                     │
│  3. BACKGROUND PROFILE REFRESH (Nightly Cron)                      │
│     Cron job triggers console command                              │
│                         ↓                                           │
│     php bin/console app:refresh-user-profiles                      │
│                         ↓                                           │
│     Select all users active in last 90 days                        │
│                         ↓                                           │
│     For each user:                                                 │
│       - Check if profile is stale (> 7 days old)                   │
│       - Apply recency decay to old purchases                       │
│       - Regenerate embedding                                       │
│       - Update MongoDB                                             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Component Diagram

```
┌───────────────────────────────────────────────────────────────────┐
│                        Presentation Layer                         │
├───────────────────────────────────────────────────────────────────┤
│  HomeController                │  CartController                   │
│  - index() [personalized]      │  - checkout() [trigger profile]   │
│  - getRecommendations()        │                                   │
└───────────────────────────────┴───────────────────────────────────┘
                                    ↓
┌───────────────────────────────────────────────────────────────────┐
│                        Application Layer                          │
├───────────────────────────────────────────────────────────────────┤
│  UserProfileService            │  OrderEventSubscriber             │
│  - getRecommendations()        │  - onOrderCompleted()             │
│  - refreshProfile()            │  - triggerProfileUpdate()         │
│  - generateEmbedding()         │                                   │
│                                │  SearchEventSubscriber            │
│  ProfileAggregationService     │  - onSearchPerformed()            │
│  - aggregatePurchases()        │                                   │
│  - aggregateSearches()         │                                   │
│  - buildTextRepresentation()   │  ProfileCacheService              │
│                                │  - get() / set() / invalidate()   │
└───────────────────────────────┴───────────────────────────────────┘
                                    ↓
┌───────────────────────────────────────────────────────────────────┐
│                        Domain Layer                               │
├───────────────────────────────────────────────────────────────────┤
│  UserProfile (Entity)          │  ProfileSnapshot (VO)             │
│  - userId, embeddingVector     │  - purchases, searches, metadata  │
│  - lastUpdated, dataSnapshot   │                                   │
│                                │  RecommendationResult (VO)        │
│  User (Entity - existing)      │  - products[], scores[], reason   │
│  - id, email, orders           │                                   │
└───────────────────────────────┴───────────────────────────────────┘
                                    ↓
┌───────────────────────────────────────────────────────────────────┐
│                      Infrastructure Layer                         │
├───────────────────────────────────────────────────────────────────┤
│  MongoDBUserProfileRepository  │  OpenAIEmbeddingService           │
│  - findByUserId()              │  - generateEmbedding()            │
│  - save() / update()           │  (from spec-010)                  │
│  - findSimilarProducts()       │                                   │
│                                │  MongoDBProductRepository         │
│  RedisProfileCacheRepository   │  (from spec-010)                  │
│  - getCachedProfile()          │                                   │
│  - setCachedProfile()          │  ConsoleCommand\                  │
│                                │  RefreshUserProfilesCommand       │
└───────────────────────────────┴───────────────────────────────────┘
```

---

## Database Schema

### MongoDB - user_profiles Collection

```json
{
  "_id": ObjectId("..."),
  "userId": "UUID-from-mysql-user-id",
  "embeddingVector": [0.123, -0.456, 0.789, ...], // 1536 dimensions
  "lastUpdated": ISODate("2026-02-08T14:30:00Z"),
  "dataSnapshot": {
    "recentPurchases": [
      {"productId": 123, "name": "Gaming Mouse", "category": "Electronics", "purchasedAt": "2026-02-01"},
      {"productId": 456, "name": "Mechanical Keyboard", "category": "Electronics", "purchasedAt": "2026-01-15"}
    ],
    "recentSearches": [
      {"query": "wireless headphones", "timestamp": "2026-02-07"},
      {"query": "gaming monitor", "timestamp": "2026-02-05"}
    ],
    "dominantCategories": ["Electronics", "Gaming", "Accessories"]
  },
  "metadata": {
    "totalPurchases": 15,
    "totalSearches": 42,
    "accountAgedays": 120,
    "lastActivityDate": ISODate("2026-02-08T12:00:00Z"),
    "generationVersion": "1.0"
  },
  "createdAt": ISODate("2026-01-01T00:00:00Z"),
  "updatedAt": ISODate("2026-02-08T14:30:00Z")
}
```

**Indexes**:
- `{"userId": 1}` - Unique index for fast user lookup
- `{"embeddingVector": "vectorSearch"}` - Vector search index (MongoDB Atlas Search or native)
- `{"lastUpdated": 1}` - For stale profile identification
- `{"metadata.lastActivityDate": 1}` - For active user filtering

### Redis Cache Keys

```
user_profile:{userId}  →  JSON(UserProfile)      [TTL: 3600s / 1 hour]
user_recommendations:{userId}  →  JSON([Product IDs])   [TTL: 1800s / 30 min]
```

---

## Implementation Phases

### Phase 1: Core Infrastructure (Days 1-2)

**Goal**: Set up user profile entity and MongoDB storage

**Tasks**:
- [ ] Create `Domain/Entity/UserProfile.php` entity
- [ ] Create `Domain/ValueObject/ProfileSnapshot.php` VO
- [ ] Create `Infrastructure/Repository/MongoDBUserProfileRepository.php`
- [ ] Configure MongoDB user_profiles collection
- [ ] Create vector search index on embeddingVector field
- [ ] Unit tests for UserProfile entity
- [ ] Integration test for MongoDB repository CRUD operations

**Validation**: Can manually create and retrieve user profiles from MongoDB

---

### Phase 2: Profile Generation Service (Days 3-4)

**Goal**: Build service to aggregate user data and generate embeddings

**Tasks**:
- [ ] Create `Application/Service/ProfileAggregationService.php`
  - [ ] Method: `aggregatePurchases(userId): array`
  - [ ] Method: `aggregateSearches(userId): array`
  - [ ] Method: `buildTextRepresentation(purchases, searches): string`
- [ ] Create `Application/Service/UserProfileService.php`
  - [ ] Method: `refreshProfile(userId): UserProfile`
  - [ ] Method: `generateEmbedding(textRepresentation): array`
- [ ] Integrate with OpenAI embeddings service (from spec-010)
- [ ] Implement weighted aggregation (purchases 70%, searches 20%, views 10%)
- [ ] Unit tests for aggregation logic
- [ ] Integration test for full profile generation workflow

**Validation**: Can generate user profile embedding from MySQL purchase/search data

---

### Phase 3: Real-time Profile Updates (Days 5-6)

**Goal**: Trigger profile regeneration on user actions

**Tasks**:
- [ ] Create `Application/EventSubscriber/OrderEventSubscriber.php`
  - [ ] Listen to Order PostPersist event
  - [ ] Trigger async profile refresh job
- [ ] Create `Application/EventSubscriber/SearchEventSubscriber.php`
  - [ ] Listen to search events (from chatbot/search controller)
  - [ ] Trigger async profile refresh job
- [ ] Configure Symfony Messenger for async job processing
- [ ] Create `Application/Message/RefreshUserProfileMessage.php`
- [ ] Create `Application/MessageHandler/RefreshUserProfileHandler.php`
- [ ] Integration test: Order completion → Profile updated
- [ ] Integration test: Search performed → Profile updated

**Validation**: User profile automatically updates within 5 seconds after purchase

---

### Phase 4: Recommendation Retrieval (Days 7-8)

**Goal**: Query MongoDB for similar products based on user profile

**Tasks**:
- [ ] Extend `MongoDBUserProfileRepository` with:
  - [ ] Method: `findSimilarProducts(userEmbedding, limit=20): array`
- [ ] Create `Application/Service/RecommendationService.php`
  - [ ] Method: `getRecommendationsForUser(userId): RecommendationResult`
  - [ ] Method: `enrichWithMySQLData(productIds): array`
- [ ] Create `Infrastructure/Repository/RedisProfileCacheRepository.php`
  - [ ] Cache user profiles with 1-hour TTL
  - [ ] Cache recommendation results with 30-minute TTL
- [ ] Implement fallback to default product listing
- [ ] Unit tests for recommendation logic
- [ ] Performance test: Recommendation retrieval < 500ms

**Validation**: Can retrieve personalized product recommendations for authenticated user

---

### Phase 5: Home Page Integration (Days 9-10)

**Goal**: Display personalized recommendations on home page

**Tasks**:
- [ ] Modify `src/Infrastructure/Controller/HomeController.php`
  - [ ] Inject `RecommendationService`
  - [ ] Check if user is authenticated
  - [ ] If authenticated: fetch personalized recommendations
  - [ ] If not authenticated or no profile: default product listing
- [ ] Update `templates/home.html.twig`
  - [ ] Add section for "Recommended For You"
  - [ ] Display products with similarity indicators (optional)
- [ ] Add loading state for async recommendation fetching
- [ ] Implement error handling and fallback UI
- [ ] Frontend performance test: Home page loads < 1s
- [ ] E2E test: Authenticated user sees personalized home page

**Validation**: Home page displays personalized recommendations for logged-in users

---

### Phase 6: Background Maintenance (Days 11-12)

**Goal**: Batch refresh stale profiles and handle inactive users

**Tasks**:
- [ ] Create `src/Infrastructure/Command/RefreshUserProfilesCommand.php`
  - [ ] Option: `--all` (refresh all users)
  - [ ] Option: `--stale-only` (only profiles > 7 days old)
  - [ ] Option: `--user-id=UUID` (refresh specific user)
- [ ] Implement recency decay for old purchases (older than 90 days)
- [ ] Configure cron job in Docker or Symfony scheduler
- [ ] Add progress bar for batch processing
- [ ] Add logging for profile generation stats
- [ ] Integration test: Batch refresh command
- [ ] Documentation: Admin guide for profile management

**Validation**: Cron job successfully refreshes stale user profiles nightly

---

### Phase 7: Monitoring & Observability (Days 13-14)

**Goal**: Add monitoring, logging, and admin tools

**Tasks**:
- [ ] Add Monolog logging for:
  - [ ] Profile generation events
  - [ ] Recommendation retrieval performance
  - [ ] Fallback triggers
  - [ ] OpenAI API errors
- [ ] Create metrics:
  - [ ] `user_profile_generation_duration` (histogram)
  - [ ] `recommendations_cache_hit_rate` (counter)
  - [ ] `recommendations_retrieval_duration` (histogram)
- [ ] Create admin command: `php bin/console app:user-profile:stats`
  - [ ] Show total profiles, average age, cache hit rate
- [ ] Add health check endpoint: `/api/health/recommendations`
- [ ] Performance monitoring dashboard (or logs)
- [ ] Documentation: Troubleshooting guide

**Validation**: Can monitor recommendation system health and performance

---

### Phase 8: Testing & Refinement (Days 15-16)

**Goal**: Comprehensive testing and optimization

**Tasks**:
- [ ] Load testing: 100 concurrent home page requests
- [ ] A/B test preparation: Track recommendation CTR vs default listing
- [ ] Edge case testing:
  - [ ] User with 1 purchase
  - [ ] User with 100+ purchases
  - [ ] User with diverse interests (conflicting categories)
  - [ ] User with no recent activity (90+ days old)
- [ ] Similarity score tuning: Adjust weights (purchases, searches, views)
- [ ] Cache optimization: Fine-tune TTL values
- [ ] Documentation: User guide, admin guide, troubleshooting

**Validation**: System handles all edge cases gracefully, performance meets SLA

---

## File Structure

```
src/
├── Application/
│   ├── EventSubscriber/
│   │   ├── OrderEventSubscriber.php          [NEW]
│   │   └── SearchEventSubscriber.php         [NEW]
│   ├── Message/
│   │   └── RefreshUserProfileMessage.php     [NEW]
│   ├── MessageHandler/
│   │   └── RefreshUserProfileHandler.php     [NEW]
│   ├── Service/
│   │   ├── ProfileAggregationService.php     [NEW]
│   │   ├── RecommendationService.php         [NEW]
│   │   └── UserProfileService.php            [NEW]
│   └── UseCase/
│       └── GenerateUserProfileEmbedding.php  [NEW]
├── Domain/
│   ├── Entity/
│   │   ├── User.php                          [EXISTING - no changes]
│   │   └── UserProfile.php                   [NEW]
│   ├── Repository/
│   │   └── UserProfileRepositoryInterface.php[NEW]
│   └── ValueObject/
│       ├── ProfileSnapshot.php               [NEW]
│       └── RecommendationResult.php          [NEW]
└── Infrastructure/
    ├── Command/
    │   └── RefreshUserProfilesCommand.php    [NEW]
    ├── Controller/
    │   └── HomeController.php                [MODIFY]
    └── Repository/
        ├── MongoDBUserProfileRepository.php  [NEW]
        └── RedisProfileCacheRepository.php   [NEW]

templates/
└── home.html.twig                            [MODIFY]

tests/
├── Integration/
│   └── UserProfileGenerationTest.php         [NEW]
└── Unit/
    ├── ProfileAggregationServiceTest.php     [NEW]
    └── UserProfileServiceTest.php            [NEW]

config/
├── packages/
│   └── messenger.yaml                        [MODIFY - add profile refresh routing]
└── services.yaml                             [MODIFY - register new services]
```

---

## Configuration Changes

### config/services.yaml

```yaml
services:
    # User Profile Services
    App\Application\Service\UserProfileService:
        arguments:
            $profileRepository: '@App\Infrastructure\Repository\MongoDBUserProfileRepository'
            $aggregationService: '@App\Application\Service\ProfileAggregationService'
            $embeddingService: '@App\Infrastructure\AI\Service\OpenAIEmbeddingService'
            
    App\Application\Service\RecommendationService:
        arguments:
            $profileRepository: '@App\Infrastructure\Repository\MongoDBUserProfileRepository'
            $cacheRepository: '@App\Infrastructure\Repository\RedisProfileCacheRepository'
            $productRepository: '@App\Domain\Repository\ProductRepositoryInterface'
            
    App\Infrastructure\Repository\MongoDBUserProfileRepository:
        arguments:
            $mongoClient: '@mongodb.client'
            $databaseName: '%env(MONGODB_DATABASE)%'
            
    App\Infrastructure\Repository\RedisProfileCacheRepository:
        arguments:
            $redis: '@Predis\Client'
```

### config/packages/messenger.yaml

```yaml
framework:
    messenger:
        transports:
            async: '%env(MESSENGER_TRANSPORT_DSN)%'
            
        routing:
            App\Application\Message\RefreshUserProfileMessage: async
```

### .env

```env
# User Profile Configuration
USER_PROFILE_CACHE_TTL=3600          # 1 hour
RECOMMENDATIONS_CACHE_TTL=1800       # 30 minutes
PROFILE_REFRESH_THRESHOLD=604800     # 7 days in seconds
USER_ACTIVITY_THRESHOLD=7776000      # 90 days in seconds
```

---

## Testing Strategy

### Unit Tests

- ProfileAggregationService: Test data aggregation logic
- UserProfileService: Test embedding generation
- RecommendationService: Test fallback behavior
- ProfileSnapshot: Test value object immutability

### Integration Tests

- Order completion triggers profile refresh
- Search triggers profile refresh
- Profile retrieval from MongoDB
- Cache hit/miss scenarios
- Vector similarity search accuracy

### Performance Tests

- Home page load time < 1s with recommendations
- Recommendation retrieval < 500ms
- Profile generation < 5s
- 100 concurrent requests without degradation

### E2E Tests

- New user → no recommendations → default listing
- User makes purchase → profile generates → recommendations appear
- User searches → profile updates → recommendations adjust

---

## Deployment Checklist

### Pre-deployment

- [ ] MongoDB user_profiles collection created
- [ ] Vector search index configured
- [ ] Environment variables set
- [ ] Redis cache cleared
- [ ] Messenger worker running for async jobs
- [ ] Cron job configured for nightly refresh

### Deployment

- [ ] Deploy code to production
- [ ] Run database migrations (if any)
- [ ] Verify MongoDB connection
- [ ] Verify OpenAI API connectivity
- [ ] Test home page with authenticated user

### Post-deployment

- [ ] Monitor logs for errors
- [ ] Check profile generation metrics
- [ ] Verify cache hit rates
- [ ] Monitor home page performance
- [ ] Collect user feedback on recommendations

---

## Risk Mitigation

### Cost Control

- **Risk**: High OpenAI API costs
- **Mitigation**: 
  - Lazy generation (only for active users)
  - Redis caching (1-hour profile TTL)
  - Batch regeneration only when needed
  - Monitor API usage daily

### Performance

- **Risk**: Slow home page load times
- **Mitigation**:
  - Redis caching layer
  - Async profile generation
  - Fallback to cached default listing
  - Vector index optimization

### Data Quality

- **Risk**: Inaccurate recommendations
- **Mitigation**:
  - Weighted aggregation (purchases > searches > views)
  - Recency decay for old data
  - Diversity constraints (future enhancement)
  - A/B testing to validate improvements

### Availability

- **Risk**: MongoDB or OpenAI downtime
- **Mitigation**:
  - Graceful fallback to default listing
  - Circuit breaker pattern
  - Health checks and monitoring
  - Degraded mode with keyword search

---

## Success Metrics

### Technical Metrics

- Profile generation time: < 5 seconds (target: 2 seconds)
- Home page load time: < 1 second with recommendations
- Cache hit rate: > 80% for user profiles
- Recommendation retrieval: < 500ms (target: 200ms)
- System uptime: > 99.5%

### Business Metrics

- Click-through rate (CTR) on recommendations: 25% higher than default listing
- Conversion rate: 15% increase for users with personalized home page
- User engagement: 20% increase in products viewed per session
- Customer satisfaction: 80% find recommendations relevant (survey)

---

## Future Enhancements

- **Collaborative Filtering**: Recommend based on similar users
- **Real-time Diversity**: Ensure category balance in recommendations
- **A/B Testing Framework**: Test different recommendation algorithms
- **Preference Controls**: Allow users to customize or disable personalization
- **Explanation UI**: Show why products are recommended (spec-013 P3)
- **Cross-device Sync**: Unified profile across devices
- **Seasonal Boost**: Prioritize trending or seasonal products
- **Inventory Integration**: Avoid recommending out-of-stock items
