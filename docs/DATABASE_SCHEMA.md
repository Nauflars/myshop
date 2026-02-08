# Database Schema Documentation

**Feature**: Spec-010 Semantic Search  
**Version**: 1.0

## Overview

Semantic search uses dual-database architecture:
- **MySQL**: Source of truth for business data (products, orders, users)
- **MongoDB**: Vector database for embeddings (semantic search only)

## MySQL Schema

### products (Existing)

Primary product catalog. Source of truth.

```sql
CREATE TABLE products (
    id BINARY(16) PRIMARY KEY,           -- UUID
    name VARCHAR(255) NOT NULL,
    description LONGTEXT NOT NULL,
    price_in_cents INT NOT NULL,
    currency VARCHAR(3) NOT NULL DEFAULT 'USD',
    stock INT NOT NULL DEFAULT 0,
    category VARCHAR(100) NOT NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    name_es VARCHAR(255),                -- Spanish translation
    INDEX idx_category (category),
    INDEX idx_stock (stock),
    INDEX idx_updated_at (updated_at),   -- For sync queries
    INDEX idx_category_updated_at (category, updated_at)  -- Composite
);
```

**Indexes**:
- `PRIMARY`: Product lookup by ID
- `idx_category`: Filter by category
- `idx_updated_at`: Incremental sync queries
- `idx_category_updated_at`: Filtered sync

---

## MongoDB Schema

### product_embeddings Collection

Stores vector embeddings for semantic search.

**Collection Name**: `product_embeddings`

**Document Structure**:
```javascript
{
  "_id": ObjectId("507f1f77bcf86cd799439011"),
  "product_id": "550e8400-e29b-41d4-a716-446655440000",  // UUID reference to MySQL
  "embedding": [0.123, -0.456, 0.789, ...],              // 1536 floats
  "name": "Gaming Laptop",                                // Denormalized
  "description": "High-performance laptop...",            // Denormalized
  "category": "electronics",                              // Denormalized
  "metadata": {
    "model_version": "text-embedding-3-small",
    "dimensions": 1536,
    "generated_at": ISODate("2026-02-07T10:30:00Z")
  },
  "updated_at": ISODate("2026-02-07T10:30:00Z")
}
```

**Field Descriptions**:
| Field | Type | Description | Indexed |
|-------|------|-------------|---------|
| `_id` | ObjectId | MongoDB internal ID | Yes (auto) |
| `product_id` | string | UUID reference to MySQL | Yes (unique) |
| `embedding` | array[float] | 1536-dimension vector | Yes (vector) |
| `name` | string | Denormalized product name | No |
| `description` | string | Denormalized description | No |
| `category` | string | Denormalized category | Yes |
| `metadata` | object | Embedding generation metadata | No |
| `updated_at` | date | Last sync timestamp | Yes |

**Why Denormalization?**
- MongoDB queries can return results without joining MySQL
- Improves performance (single query vs. N+1)
- Acceptable staleness (embeddings regenerated on product update)

---

## MongoDB Indexes

### Vector Index (Critical for Performance)

```javascript
db.product_embeddings.createIndex(
  { "embedding": "hnsw" },
  {
    name: "vector_index_cosine",
    similarity: "cosine",
    dimensions: 1536,
    m: 16,                   // HNSW parameter (neighbors)
    efConstruction: 200      // HNSW parameter (build quality)
  }
);
```

**Index Type**: HNSW (Hierarchical Navigable Small World)  
**Similarity Metric**: Cosine similarity  
**Performance**: O(log N) approximate nearest neighbor search  

**Tuning Parameters**:
- `m`: Number of bi-directional links (16 = balanced)
- `efConstruction`: Build time vs. quality (200 = high quality)

### Additional Indexes

```javascript
// Unique constraint on product_id (prevent duplicates)
db.product_embeddings.createIndex(
  { "product_id": 1 },
  { unique: true }
);

// Category filtering
db.product_embeddings.createIndex(
  { "category": 1 }
);

// Sync monitoring (find stale embeddings)
db.product_embeddings.createIndex(
  { "updated_at": -1 }
);

// Compound index for filtered + sorted queries
db.product_embeddings.createIndex(
  { "category": 1, "updated_at": -1 }
);
```

---

## Data Synchronization

### Sync Triggers (Doctrine Events)

| MySQL Event | MongoDB Action | Handler |
|-------------|----------------|---------|
| `INSERT INTO products` | `db.product_embeddings.insertOne()` | PostPersist |
| `UPDATE products` | `db.product_embeddings.updateOne()` | PostUpdate |
| `DELETE FROM products` | `db.product_embeddings.deleteOne()` | PostRemove |

### Consistency Guarantees

- **MySQL → MongoDB**: Eventually consistent
- **Sync Latency**: <5 seconds (typical)
- **Failure Handling**: Retries with exponential backoff
- **Data Loss Prevention**: MySQL always succeeds, MongoDB sync queued if fails

### Consistency Check Query

```javascript
// Find products in MySQL without MongoDB embeddings
SELECT p.id, p.name
FROM products p
LEFT JOIN (
    SELECT product_id FROM mongodb.product_embeddings
) e ON p.id = e.product_id
WHERE e.product_id IS NULL;
```

**Resolution**:
```bash
bin/console app:embedding:sync <product-id>
```

---

## Redis Schema (Cache)

### Query Embedding Cache

**Key Pattern**: `search:embedding:{md5(query)}`  
**Value**: JSON-encoded array of 1536 floats  
**TTL**: 3600 seconds (1 hour)  
**Eviction**: LRU (Least Recently Used)

**Example**:
```
Key:   search:embedding:5f4dcc3b5aa765d61d8327deb882cf99
Value: [0.123,-0.456,0.789,...] (1536 floats)
TTL:   3600
```

### Search Metrics Cache

**Key Pattern**: `search:metrics:{period}`  
**Value**: JSON-encoded metrics object  
**TTL**: 300 seconds (5 minutes)

---

## Storage Estimates

### MongoDB Storage

**Per Product Embedding**:
- Embedding array: 1536 floats × 8 bytes = 12,288 bytes (~12 KB)
- Metadata: ~500 bytes
- **Total**: ~13 KB per product

**Catalog Size Estimates**:
| Products | Storage | Memory (with index) |
|----------|---------|---------------------|
| 1,000 | 13 MB | ~50 MB |
| 10,000 | 130 MB | ~500 MB |
| 100,000 | 1.3 GB | ~5 GB |
| 1,000,000 | 13 GB | ~50 GB |

**Index Size**: ~4x embedding data (HNSW overhead)

### Redis Storage

**Per Cached Query**:
- Embedding: 1536 floats × 8 bytes = 12,288 bytes (~12 KB)

**Cache Capacity Estimates** (with 256 MB Redis):
- Max cached queries: ~21,000
- With TTL 1h + LRU: Practically unlimited for typical traffic

---

## Backup & Recovery

### MongoDB Backup

```bash
docker exec myshop_mongodb mongodump --db=myshop --out=/backup

# Backup file: /backup/myshop/product_embeddings.bson
```

### MongoDB Restore

```bash
docker exec myshop_mongodb mongorestore --db=myshop /backup/myshop
```

### Full Re-Sync (Disaster Recovery)

```bash
# Delete all embeddings
docker exec myshop_mongodb mongosh myshop --eval "db.product_embeddings.deleteMany({})"

# Regenerate from MySQL
docker exec myshop_php bin/console app:embedding:sync-all
```

**Time Estimate**: 1,000 products = ~10 minutes (with API rate limits)

---

## Migration Scripts

### Add Vector Index (First-Time Setup)

```bash
docker exec myshop_php bin/console app:vector-index:create
```

### Change Embedding Dimensions (Model Upgrade)

```bash
# 1. Update environment variable
OPENAI_EMBEDDING_MODEL=text-embedding-3-large
EMBEDDING_DIMENSIONS=3072

# 2. Drop old index
docker exec myshop_mongodb mongosh myshop --eval "db.product_embeddings.dropIndex('vector_index_cosine')"

# 3. Regenerate embeddings with new model
docker exec myshop_php bin/console app:embedding:migrate-model --new-dimensions=3072

# 4. Recreate vector index
docker exec myshop_php bin/console app:vector-index:create --dimensions=3072
```

---

## Performance Tuning

### MongoDB Connection Pool

```yaml
# config/packages/mongodb_pooling.yaml
mongodb:
  client:
    uri: "%env(MONGODB_URL)%"
    maxPoolSize: 50        # Max connections
    minPoolSize: 10        # Warm pool
    maxIdleTimeMS: 60000   # 1 minute
```

### Query Optimization

**Use Projection** (reduce network transfer):
```javascript
db.product_embeddings.find(
    { similarity_score: { $gte: 0.7 } },
    { product_id: 1, name: 1, similarity_score: 1 }  // Only needed fields
);
```

**Limit Results**:
```javascript
db.product_embeddings.aggregate([
    { $vectorSearch: { /* ... */ } },
    { $limit: 50 }  // Enforce max results
]);
```

---

**Last Updated**: February 7, 2026  
**Schema Version**: 1.0  
**Maintained By**: myshop Development Team
