# Feature Specification: Personalized User Recommendations with Vector Embeddings

**Feature Branch**: `013-user-recommendations`  
**Created**: February 8, 2026  
**Status**: Draft  
**Input**: User description: "Generate user embedding vectors based on purchase history and search behavior to display personalized product recommendations on home page using MongoDB vector search"

## User Scenarios & Testing

### User Story 1 - Personalized Home Page Experience (Priority: P1)

As a **returning customer**, I want to see product recommendations tailored to my interests when I visit the home page, so I can quickly discover products I'm likely to enjoy without manual searching.

**Why this priority**: This is the core value proposition - delivering immediate personalized value to users upon arrival.

**Independent Test**: Can be fully tested by logging in with a user who has purchase history, visiting the home page, and verifying that displayed products are relevant to past purchases.

**Acceptance Scenarios**:

1. **Given** I am an authenticated user with previous purchases of "Gaming Mouse" and "Mechanical Keyboard"  
   **When** I visit the home page  
   **Then** I should see gaming peripherals and computer accessories ranked higher than unrelated categories like "Kitchen Appliances"

2. **Given** I am an authenticated user who has searched for "wireless headphones" multiple times  
   **When** I visit the home page  
   **Then** I should see audio products (headphones, speakers, audio accessories) prominently displayed

3. **Given** I am a new user with no purchase or search history  
   **When** I visit the home page  
   **Then** I should see a default product listing (popular products, trending items, or general recommendations)

---

### User Story 2 - Real-time Profile Updates (Priority: P2)

As a **customer**, I want my product recommendations to update as I shop and search, so the system learns my preferences and becomes more accurate over time.

**Why this priority**: Enables dynamic learning from user behavior, improving recommendation quality progressively.

**Independent Test**: Make a purchase or search, then immediately check the home page to verify recommendations have adapted.

**Acceptance Scenarios**:

1. **Given** I just purchased a "DSLR Camera"  
   **When** I return to the home page after checkout  
   **Then** I should see photography-related products (lenses, tripods, camera bags) in my recommendations

2. **Given** I have performed 5 searches for "organic tea"  
   **When** I visit the home page  
   **Then** my user profile should reflect interest in organic/health products, and recommendations should include similar items

3. **Given** I add products to cart but don't purchase  
   **When** I return to the home page  
   **Then** recommendations should include complementary products or alternatives to items in my cart

---

### User Story 3 - Transparent Recommendation Logic (Priority: P3)

As a **customer**, I want to understand why products are being recommended to me, so I can trust the system and discover relevant connections.

**Why this priority**: Builds user trust and transparency, enhancing the overall user experience without being critical for MVP.

**Independent Test**: View a recommended product and see an explanation like "Based on your purchase of [Product X]" or "Because you searched for [Query Y]".

**Acceptance Scenarios**:

1. **Given** I see a recommended product on the home page  
   **When** I hover over or click for details  
   **Then** I should see a brief explanation like "Recommended because you bought: Gaming Mouse"

2. **Given** I have diverse purchase history (electronics, clothing, books)  
   **When** I view my recommendations  
   **Then** I should see products from multiple categories with clear reasoning for each

---

### Edge Cases

- What happens when a user has purchased products from conflicting categories (e.g., baby products and gaming gear)?
  - **Answer**: System should maintain diversity and show recommendations from all interest areas, weighted by recency and frequency.

- How does the system handle users who share accounts (family members)?
  - **Answer**: Profile reflects combined interests. Future enhancement could support multiple user profiles per account.

- What if a user's embedding vector becomes too specialized (only recommends very narrow product types)?
  - **Answer**: Apply diversity constraints to ensure recommendations include variety while maintaining relevance.

- How quickly should the user profile update after new behavior?
  - **Answer**: Near real-time (within seconds) for immediate actions (purchases, searches). Profiles regenerate after each significant event.

- What happens if MongoDB embedding search fails or is slow?
  - **Answer**: Graceful fallback to default product listing (popular products or category-based recommendations).

## Requirements

### Functional Requirements

- **FR-001**: System MUST generate a unique embedding vector for each authenticated user based on their behavioral data (purchases, searches, and browsing)

- **FR-002**: System MUST update user embedding vectors in near real-time (within 5 seconds) after:
  - Completing a purchase
  - Performing a product search
  - Adding items to cart

- **FR-003**: System MUST store user embedding vectors in MongoDB alongside product embeddings for efficient vector similarity search

- **FR-004**: User profiles MUST incorporate multiple data sources with weighted importance:
  - Purchase history (70% weight)
  - Search queries (20% weight)
  - Products viewed/added to cart (10% weight)

- **FR-005**: Home page MUST display products ordered by vector similarity to the authenticated user's embedding (descending order, most similar first)

- **FR-006**: System MUST limit home page recommendations to top 20 products by similarity score (configurable)

- **FR-007**: For unauthenticated users or users without history, system MUST display default product listing (popular products or featured items)

- **FR-008**: System MUST use OpenAI embeddings API (via Symfony AI) to generate user profile vectors, maintaining consistency with product embedding model

- **FR-009**: User embedding generation MUST aggregate purchase and search data into a text representation before generating the vector

- **FR-010**: System MUST handle cases where user has no recent activity (older than 90 days) by applying recency decay to weights

- **FR-011**: System MUST provide an API endpoint or service to regenerate user embeddings on-demand (for admin maintenance)

### Key Entities

- **UserProfile**: Represents the personalized profile for recommendations
  - Attributes: userId (reference to MySQL User), embeddingVector (array of floats), lastUpdated (timestamp), dataSnapshot (JSON of recent purchases/searches)
  - Storage: MongoDB (same collection as product embeddings or separate `user_profiles` collection)
  - Relationships: Links to MySQL User via userId

- **PurchaseHistory**: Aggregated view of user's completed orders
  - Attributes: productIds, productNames, categories, purchaseDates
  - Source: MySQL Orders and OrderItems tables
  - Used for: Generating weighted input for user embedding

- **SearchHistory**: Record of user's search queries
  - Attributes: queries (text), timestamps, resultCounts
  - Storage: Could be stored in MongoDB or tracked in MySQL conversations
  - Used for: Understanding user intent and interests

## Success Criteria

### Measurable Outcomes

- **SC-001**: Authenticated users with purchase history see personalized home page recommendations within 500ms of page load

- **SC-002**: User embedding vectors update within 5 seconds of completing a purchase or search action

- **SC-003**: Top 5 recommended products have average similarity score > 0.7 to user profile embedding (cosine similarity)

- **SC-004**: Click-through rate (CTR) on home page recommendations is 25% higher than default product listing for users with history

- **SC-005**: System handles 100 concurrent home page requests with personalized recommendations without performance degradation

- **SC-006**: For new users (no history), system gracefully falls back to default listing with no errors or delays

- **SC-007**: User satisfaction: 80% of users with history find at least 3 relevant products in top 10 recommendations (to be measured via survey or analytics)

## Assumptions

- OpenAI embedding model (text-embedding-ada-002 or newer) is already used for product embeddings
- MongoDB vector search index is already configured for product embeddings
- User authentication is required to track individual profiles
- Purchase and search data is readily accessible from existing MySQL tables and logs
- Text aggregation approach (combining product names/descriptions and search queries) is sufficient for user profile representation
- System can tolerate eventual consistency for embedding updates (5-second delay is acceptable)

## Dependencies

- **MongoDB**: Requires vector search capabilities with existing product embeddings
- **OpenAI API**: Embeddings endpoint via Symfony AI Bundle
- **MySQL**: User, Order, OrderItem, Product tables
- **Symfony AI Bundle**: Existing integration for embeddings generation
- **Redis** (optional): For caching user embeddings to reduce MongoDB load
- **spec-010**: Semantic search infrastructure (product embeddings in MongoDB) must be fully functional

## Out of Scope

- Multi-profile support per account (e.g., separate profiles for family members)
- Collaborative filtering (recommendations based on similar users)
- A/B testing framework for recommendation strategies
- User preference settings or opt-out from personalization
- Recommendation explanations UI (marked as P3, can be deferred)
- Historical trending analysis
- Cross-device profile synchronization beyond basic login

## Technical Considerations

### Database Schema

**MongoDB - user_profiles collection**:
```json
{
  "_id": ObjectId,
  "userId": "UUID-from-mysql",
  "embeddingVector": [0.123, -0.456, ...], // 1536 dimensions for ada-002
  "lastUpdated": ISODate,
  "dataSnapshot": {
    "recentPurchases": ["Product A", "Product B"],
    "recentSearches": ["query 1", "query 2"],
    "categories": ["Electronics", "Gaming"]
  },
  "metadata": {
    "totalPurchases": 15,
    "totalSearches": 42,
    "accountAge": 120 // days
  }
}
```

**Index requirements**:
- Vector index on `embeddingVector` for similarity search
- Standard index on `userId` for fast lookups
- TTL index on `lastUpdated` for auto-cleanup of stale profiles (optional)

### Embedding Generation Strategy

1. **Aggregate user data**:
   - Fetch last 20 purchases (product names + descriptions)
   - Fetch last 50 search queries
   - Weight by recency (more recent = higher weight)

2. **Create text representation**:
   ```
   "User interests: [purchased: Gaming Mouse, Mechanical Keyboard, HDMI Cable...] [searched: wireless headphones, gaming monitor, ergonomic chair...]"
   ```

3. **Generate embedding**:
   - Send aggregated text to OpenAI embeddings API
   - Store resulting vector in MongoDB

4. **Update triggers**:
   - Post-purchase: Immediately after order completion
   - Post-search: After user submits search query
   - Batch update: Nightly cron job for all active users (recency decay)

### Performance Optimizations

- Cache user embeddings in Redis with 1-hour TTL
- Lazy regeneration: Only update if user profile is stale (> 1 hour old) and user is actively browsing
- Limit aggregation queries: Use indexed timestamps to fetch only recent data
- Async processing: Queue embedding generation jobs for background processing if real-time isn't feasible

### Fallback Behavior

If any step fails:
1. MongoDB unavailable → Default product listing
2. OpenAI API timeout → Use cached embedding or default listing
3. No user history → Default listing (popular products, trending, or random selection)
4. Embedding generation error → Log error, return default listing, retry in background

## Privacy and Security

- User profiles are tied only to authenticated user IDs
- Embedding vectors do not expose raw purchase/search data (they are transformed representations)
- Comply with existing privacy policies
- Consider GDPR: Allow users to request deletion of their recommendation profile
- No personally identifiable information (PII) stored in embeddings or snapshots

## Future Enhancements

- Real-time diversity constraints to avoid "filter bubble"
- Collaborative filtering layer (users similar to you also liked...)
- Seasonal/trending boost for recommendations
- Category balance controls
- Admin dashboard to view user profile distributions
- A/B testing framework for recommendation algorithms
- Integration with product inventory (avoid recommending out-of-stock items)
