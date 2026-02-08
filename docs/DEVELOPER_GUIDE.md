# Developer Guide: Semantic Product Search

**Feature**: Spec-010 Semantic Product Search  
**Version**: 1.0  
**Audience**: Backend Developers, Technical Architects

## Table of Contents

1. [Architecture Overview](#architecture-overview)
2. [Domain Model](#domain-model)
3. [Service Layer](#service-layer)
4. [Integration Points](#integration-points)
5. [Extending the Feature](#extending-the-feature)
6. [Testing Strategy](#testing-strategy)
7. [Performance Optimization](#performance-optimization)

---

## Architecture Overview

### Layer Structure (DDD)

```
┌─────────────────────────────────────────────────────────────┐
│ Presentation Layer                                           │
│ - ProductController (search endpoints)                       │
│ - AdminMetricsController (dashboard)                         │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ Application Layer                                            │
│ - SearchFacade (orchestration)                               │
│ - SemanticSearchService                                      │
│ - KeywordSearchService                                       │
│ - ProductEmbeddingSyncService                                │
│ - EmbeddingCacheService                                      │
│ - SearchMetricsCollector                                     │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ Domain Layer          │
│ - Product (entity)                                           │
│ - ProductEmbedding (entity)
│ - SearchQuery (value object)                                 │
│ - SearchResult (value object)                                │
└─────────────────────────────────────────────────────────────┘
                          ↓
┌─────────────────────────────────────────────────────────────┐
│ Infrastructure Layer                                         │
│ - MongoDBEmbeddingRepository                                 │
│ - OpenAIEmbeddingService                                     │
│ - ProductEmbeddingListener (Doctrine events)                 │
│ - SemanticProductSearchTool (AI tool)                        │
└─────────────────────────────────────────────────────────────┘
```

### Data Flow

**Product Sync**:
```
Admin creates product
  → Doctrine PostPersist event
  → ProductEmbeddingListener
  → ProductEmbeddingSyncService::syncCreate()
  → OpenAIEmbeddingService::generate() (1536-dim vector)
  → MongoDBEmbeddingRepository::create()
  → MongoDB product_embeddings collection
```

**Semantic Search**:
```
Customer searches "laptop for gaming"
  → ProductController::search(?mode=semantic)
  → SearchFacade::search()
  → SemanticSearchService::search()
  → EmbeddingCacheService::get() (check Redis)
     ├─ Cache HIT → Use cached embedding
     └─ Cache MISS → OpenAIEmbeddingService::generate()
                   → EmbeddingCacheService::set() (store in Redis)
  → MongoDBEmbeddingRepository::searchBySimilarity()
  → MongoDB $vectorSearch aggregation
  → Enrich with MySQL product data
  → SearchResult (products + scores)
```

---

## Domain Model

### searchQuery Value Object

**Location**: `src/Domain/ValueObject/SearchQuery.php`

```php
class SearchQuery
{
    public function __construct(
        private readonly string $query,        // User query text
        private readonly int $limit = 10,      // Max results (1-100)
        private readonly int $offset = 0,      // Pagination offset
        private readonly float $minSimilarity = 0.6,  // Threshold (0.0-1.0)
        private readonly ?string $category = null     // Optional filter
    ) {
        $this->validate();
    }
}
```

**Validation Rules**:
- Query: 2-500 characters
- Limit: 1-100 products
- Offset: ≥0
- minSimilarity: 0.0-1.0

**Usage**:
```php
$query = new SearchQuery(
    query: 'gaming laptop',
    limit: 20,
    minSimilarity: 0.7,
    category: 'electronics'
);
```

### SearchResult Value Object

**Location**: `src/Domain/ValueObject/SearchResult.php`

```php
class SearchResult
{
    public function __construct(
        private readonly array $products,      // Product[]
        private readonly array $scores,        // ['product_id' => similarity_score]
        private readonly string $mode,         // 'semantic' or 'keyword'
        private readonly int $totalResults,    // Total matches (pagination)
        private readonly float $executionTimeMs  // Performance tracking
    ) {}
    
    public function toArray(): array;  // For API responses
}
```

### ProductEmbedding Entity

**Location**: `src/Domain/Entity/ProductEmbedding.php`

```php
class ProductEmbedding
{
    private string $productId;      // UUID reference to MySQL Product
    private array $embedding;       // 1536-dimension float array
    private string $name;           // Denormalized for search
    private string $description;    // Denormalized for search
    private string $category;       // Denormalized for filtering
    private array $metadata;        // Extensible JSON field
    private \DateTimeImmutable $updatedAt;
}
```

**MongoDB Collection**: `product_embeddings`  
**Indexes**:
- `product_id`: unique
- `embedding`: vector index (cosine similarity)
- `category`: regular B-tree index

---

## Service Layer

### SemanticSearchService

**Purpose**: Orchestrate semantic search end-to-end

**Key Methods**:
```php
public function search(SearchQuery $query): SearchResult
{
    // 1. Generate/retrieve query embedding
    $embedding = $this->getOrGenerateEmbedding($query->getQuery());
    
    // 2. Vector similarity search in MongoDB
    $results = $this->embeddingRepository->searchBySimilarity(
        $embedding,
        $query->getLimit(),
        $query->getOffset(),
        $query->getMinSimilarity()
    );
    
    // 3. Enrich with MySQL data (price, stock, images)
    $products = $this->enterprichResults($results);
    
    // 4. Track metrics
    $this->metricsCollector->recordSearch($executionTime, 'semantic', count($products));
    
    return new SearchResult($products, $scores, 'semantic', $total, $executionTime);
}
```

**Dependencies**:
- `OpenAIEmbeddingService`: Generate embeddings
- `EmbeddingCacheService`: Cache layer
- `MongoDBEmbeddingRepository`: Vector search
- `ProductRepository`: Enrich with MySQL data
- `SearchMetricsCollector`: Performance tracking

### ProductEmbeddingSyncService

**Purpose**: Keep MongoDB embeddings in sync with MySQL products

**Lifecycle Hooks**:
```php
// Doctrine event listeners
public function syncCreate(Product $product): void
{
    $embedding = $this->openAI->generate(
        $product->getName() . ' ' . $product->getDescription()
    );
    
    $this->repository->create(
        new ProductEmbedding(
            productId: $product->getId(),
            embedding: $embedding,
            name: $product->getName(),
            description: $product->getDescription(),
            category: $product->getCategory()
        )
    );
}

public function syncUpdate(Product $product): void { /* ... */ }
public function syncDelete(string $productId): void { /* ... */ }
```

**Error Handling**:
- OpenAI API failure → Queue retry via Symfony Messenger
- MongoDB unavailable → Log error, MySQL operation continues
- Circuit breaker → Fallback after 3 consecutive failures

### EmbeddingCacheService

**Purpose**: Redis caching layer for query embeddings

**Cache Strategy**:
```php
public function get(string $query): ?array
{
    $cacheKey = $this->generateCacheKey($query);  // MD5 hash
    $cached = $this->cache->get($cacheKey);
    
    if ($cached) {
        $this->cacheHits++;
        return json_decode($cached, true);  // Deserialize
    }
    
    $this->cacheMisses++;
    return null;
}

public function set(string $query, array $embedding): bool
{
    $cacheKey = $this->generateCacheKey($query);
    $this->cache->set($cacheKey, json_encode($embedding), $this->ttl);
}
```

**Key Format**: `search:embedding:{md5(query)}`  
**TTL**: Configurable (default: 3600s)  
**Eviction**: LRU policy in Redis

---

## Integration Points

### AI Virtual Assistant Integration

**Tool**: `SemanticProductSearchTool`  
**Location**: `src/Infrastructure/AI/Tool/SemanticProductSearchTool.php`

```php
#[AsTool('semantic_product_search', description: 'Search products by natural language query')]
class SemanticProductSearchTool
{
    public function __invoke(
        string $query,
        ?int $limit = 10,
        ?string $category = null
    ): string {
        $searchQuery = new SearchQuery($query, $limit, 0, 0.6, $category);
        $result = $this->searchService->search($searchQuery);
        
        return $this->formatForVA($result);  // Conversational format
    }
}
```

**VA Usage Example**:
```
Customer: "Show me laptops good for video editing"
VA: *calls semantic_product_search("laptops video editing", 5)*
VA: "I found 3 great options for video editing. The Dell XPS 15 
     has a powerful processor..."
```

### REST API Endpoints

**Search Endpoint**:
```http
GET /api/products/search?q=laptop&mode=semantic&limit=20&category=electronics

Response:
{
  "products": [
    {
      "id": "uuid",
      "name": "Gaming Laptop",
      "description": "...",
      "price": {"amount": 99999, "currency": "USD"},
      "similarity_score": 0.92
    }
  ],
  "metadata": {
    "mode": "semantic",
    "total_results": 45,
    "returned_results": 20,
    "execution_time_ms": 234.5
  }
}
```

**Supported Parameters**:
- `q`: Query string (required)
- `mode`: `semantic` or `keyword` (default: `keyword`)
- `limit`: 1-100 (default: 10)
- `offset`: ≥0 (default: 0)
- `category`: Optional category filter

---

## Extending the Feature

### Adding New Embedding Models

1. **Update Configuration**:
```env
OPENAI_EMBEDDING_MODEL=text-embedding-3-large  # Or new model
EMBEDDING_DIMENSIONS=3072  # New dimension count
```

2. **Update Validation**:
```php
// src/Application/Service/ProductEmbeddingSyncService.php
private const EXPECTED_DIMENSIONS = 3072;  // Update constant
```

3. **Migration Required**:
- Regenerate all embeddings with new model
- Update MongoDB vector index for new dimensions
- Clear Redis cache

```bash
bin/console app:embedding:migrate-model --new-model=text-embedding-3-large
```

### Custom Similarity Metrics

Currently uses cosine similarity. To add alternatives:

**1. Create Interface**:
```php
// src/Domain/Repository/SimilarityMetricInterface.php
interface SimilarityMetricInterface
{
    public function calculate(array $vector1, array $vector2): float;
}
```

**2. Implement Metric**:
```php
class EuclideanDistanceMetric implements SimilarityMetricInterface
{
    public function calculate(array $v1, array $v2): float
    {
        $sum = 0;
        for ($i = 0; $i < count($v1); $i++) {
            $sum += pow($v1[$i] - $v2[$i], 2);
        }
        return sqrt($sum);
    }
}
```

**3. Configure**:
```yaml
# config/services.yaml
parameters:
    search.similarity_metric: 'cosine'  # or 'euclidean'
```

### Multi-Language Support

Currently Spanish only. To add English:

**1. Detect Query Language**:
```php
$language = $this->languageDetector->detect($query);  // 'es' or 'en'
```

**2. Filter Products by Language**:
```php
$results = $this->repository->searchBySimilarity(
    $embedding,
    $limit,
    $offset,
    $minSimilarity,
    ['language' => $language]  // New filter
);
```

**3. Denormalize Language Field**:
```php
class ProductEmbedding
{
    private string $language;  // 'es' or 'en'
}
```

---

## Testing Strategy

### Unit Tests (`tests/Unit/`)

**Value Objects**:
- Test validation rules (min/max lengths, ranges)
- Test immutability
- Test serialization

**Services** (with mocked dependencies):
- Test business logic in isolation
- Test error handling (API failures, timeouts)
- Test caching logic (hits, misses, evictions)

### Integration Tests (`tests/Integration/`)

**Product Sync**:
- Test PostPersist → embedding created
- Test PostUpdate → embedding updated
- Test PostRemove → embedding deleted
- Test sync failures → retries queued

**Search Flow**:
- Test semantic search end-to-end
- Test keyword search fallback
- Test mode switching
- Test cache integration (Redis)

**VA Integration**:
- Test tool invocation
- Test result formatting
- Test context enrichment

### Performance Tests (`tests/Performance/`)

**Load Testing**:
```php
public function testConcurrentSearchRequests(): void
{
    $promises = [];
    for ($i = 0; $i < 100; $i++) {
        $promises[] = $this->searchAsync("query$i");
    }
    
    $results = Promise\all($promises)->wait();
    
    $this->assertLessThan(5000, $this->getP95ResponseTime($results));
}
```

**Benchmark Scenarios**:
- 1K products: P95 <2s
- 10K products: P95 <5s
- 100K products: P95 <10s

---

## Performance Optimization

### Caching Strategy

**Three-Layer Cache**:
1. **Query Embedding Cache** (Redis, TTL 1h): Avoid OpenAI API calls
2. **Result Cache** (Redis, TTL 15min): Cache full search results
3. **Product Data Cache** (APCu, TTL 5min): Cache enriched product data

### MongoDB Optimization

**Indexes**:
```javascript
// Vector index for similarity search
db.product_embeddings.createIndex(
  { "embedding": "hnsw" },  // Hierarchical Navigable Small World
  {
    name: "vector_index",
    similarity: "cosine",
    dimensions: 1536
  }
);

// Compound index for filtered searches
db.product_embeddings.createIndex({ "category": 1, "updatedAt": -1 });
```

**Projection** (reduce data transfer):
```php
$cursor = $collection->find(
    ['similarity_score' => ['$gte' => 0.6]],
    [
        'projection' => [
            'product_id' => 1,
            'name' => 1,
            'similarity_score' => 1
        ]
    ]
);
```

### Query Optimization

**Batch Processing**:
```php
// Enrich products in single MySQL query instead of N queries
$productIds = array_column($searchResults, 'product_id');
$products = $this->productRepository->findByIds($productIds);
```

**Connection Pooling**:
```yaml
# config/packages/mongodb_pooling.yaml
mongodb:
  client:
    maxPoolSize: 50
    minPoolSize: 10
    maxIdleTimeMS: 60000
```

---

## Code Examples

### Custom Search Filter

```php
// Add price range filtering
class PriceRangeFilter implements SearchFilterInterface
{
    public function apply(SearchQuery $query, array $results): array
    {
        if (!$query->hasPriceRange()) {
            return $results;
        }
        
        return array_filter($results, function ($product) use ($query) {
            $price = $product->getPrice()->getAmountInCents();
            return $price >= $query->getMinPrice() 
                && $price <= $query->getMaxPrice();
        });
    }
}

// Register in services.yaml
services:
    App\Application\Filter\PriceRangeFilter:
        tags: ['search.filter']
```

### Custom Metrics Collector

```php
class CustomMetricsCollector
{
    public function onSearchCompleted(SearchCompletedEvent $event): void
    {
        $this->influxDB->write([
            'measurement' => 'semantic_search',
            'fields' => [
                'response_time' => $event->getResponseTime(),
                'results_count' => $event->getResultsCount(),
                'cache_hit' => $event->isCacheHit(),
            ],
            'tags' => [
                'mode' => $event->getMode(),
                'category' => $event->getCategory(),
            ]
        ]);
    }
}
```

---

## Additional Resources

- **Spec**: `specs/010-semantic-search/spec.md`
- **Tasks**: `specs/010-semantic-search/tasks.md`
- **API Docs**: `docs/API.md`
- **Admin Guide**: `docs/ADMIN_GUIDE.md`
- **Performance Guide**: `specs/010-semantic-search/PERFORMANCE.md`
- **Error Handling**: `specs/010-semantic-search/ERROR_HANDLING.md`

---

**Document Version**: 1.0  
**Last Updated**: February 7, 2026  
**Maintained By**: myshop Development Team
