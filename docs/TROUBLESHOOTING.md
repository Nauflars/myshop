# Troubleshooting Guide: Semantic Product Search

**Feature**: Spec-010  
**Version**: 1.0

## Quick Diagnostics

Run comprehensive health check:
```bash
docker exec myshop_php bin/console app:health-check
```

---

## Common Issues

### 1. Semantic Search Returns No Results

**Symptoms**:
- Query returns empty results
- Keyword search works, semantic doesn't

**Possible Causes & Solutions**:

**A. No embeddings in MongoDB**
```bash
# Check if embeddings exist
docker exec myshop_mongodb mongosh myshop --eval "db.product_embeddings.countDocuments()"

# If zero, sync all products
docker exec myshop_php bin/console app:embedding:sync-all
```

**B. Vector index not created**
```bash
# Check index status
docker exec myshop_php bin/console app:vector-index:status

# Create if missing
docker exec myshop_php bin/console app:vector-index:create
```

**C. minSimilarity threshold too high**
```bash
# Test with lower threshold
curl "http://localhost/api/products/search?q=laptop&mode=semantic&min_similarity=0.4"
```

**D. MongoDB not running**
```bash
docker ps | grep mongodb
docker restart myshop_mongodb
```

---

### 2. Slow Search Performance (>10s)

**Symptoms**:
- Response time exceeds 10 seconds
- Dashboard shows P95 > 10,000ms

**Diagnostics**:
```bash
# Check MongoDB query performance
docker exec myshop_mongodb mongosh myshop --eval "
  db.product_embeddings.find().limit(1).explain('executionStats')
"

# Should show IXSCAN, not COLLSCAN
```

**Solutions**:

**A. Vector index missing or inefficient**
```bash
docker exec myshop_php bin/console app:vector-index:create --force
```

**B. MongoDB connection pool exhausted**
```yaml
# config/packages/mongodb_pooling.yaml
mongodb:
  client:
    maxPoolSize: 100  # Increase from 50
```

**C. OpenAI API timeout**
```env
# .env
SEARCH_TIMEOUT_SECONDS=10  # Increase from 5
```

**D. Large result set**
```bash
# Limit results
curl "http://localhost/api/products/search?q=laptop&limit=20"
```

---

### 3. High OpenAI API Costs

**Symptoms**:
- Costs exceeding budget ($50/month)
- Low cache hit rate (<60%)

**Diagnostics**:
```bash
# View cost breakdown
docker exec myshop_php bin/console app:metrics:cost --period=30days

# Check cache hit rate
docker exec myshop_php bin/console app:cache:stats
```

**Solutions**:

**A. Increase cache TTL**
```env
# .env
EMBEDDING_CACHE_TTL=7200  # 2 hours instead of 1
```

**B. Preemptively cache popular queries**
```bash
docker exec myshop_php bin/console app:cache:warm --top-queries=100
```

**C. Normalize queries** (reduce unique queries)
```php
// Lowercase, trim, remove stop words
$normalizedQuery = strtolower(trim($query));
```

---

### 4. Product Out of Sync

**Symptoms**:
- Product updated in MySQL but search shows old data
- Product deleted but still appears in search

**Diagnostics**:
```bash
# Check sync status for specific product
docker exec myshop_php bin/console app:embedding:status <product-id>
```

**Solutions**:

**A. Manual re-sync**
```bash
docker exec myshop_php bin/console app:embedding:sync <product-id>
```

**B. Check failed sync jobs**
```bash
docker exec myshop_php bin/console messenger:failed:show

# Retry failed jobs
docker exec myshop_php bin/console messenger:failed:retry
```

**C. Doctrine event listener not firing**
```bash
# Check logs for sync events
docker logs myshop_php | grep "ProductEmbeddingListener"
```

---

### 5. "Cannot Operate on Different Currencies" Error

**Symptoms**:
- Cart error when adding products
- Mixed USD/EUR products

**Solution**:
```bash
# Normalize all products to USD
docker exec myshop_php bin/console doctrine:migrations:migrate
```

---

### 6. MongoDB Connection Failed

**Symptoms**:
- "Connection refused" errors
- Fallback to keyword search

**Diagnostics**:
```bash
# Check MongoDB status
docker ps | grep mongodb
docker logs myshop_mongodb

# Test connection
docker exec myshop_mongodb mongosh --eval "db.adminCommand('ping')"
```

**Solutions**:

**A. MongoDB not started**
```bash
docker-compose up -d mongodb
```

**B. Wrong connection string**
```env
# .env
MONGODB_URL=mongodb://root:rootpassword@mongodb:27017
```

**C. Network issue**
```bash
docker network inspect myshop_network
```

---

### 7. Redis Cache Not Working

**Symptoms**:
- Cache hit rate 0%
- High OpenAI API costs

**Diagnostics**:
```bash
# Check Redis connection
docker exec myshop_redis redis-cli PING

# Monitor cache activity
docker exec myshop_redis redis-cli MONITOR | grep "search:embedding"
```

**Solutions**:

**A. Redis not started**
```bash
docker-compose up -d redis
```

**B. Memory limit reached**
```bash
# Check memory usage
docker exec myshop_redis redis-cli INFO MEMORY

# Increase maxmemory
docker exec myshop_redis redis-cli CONFIG SET maxmemory 512mb
```

**C. Wrong eviction policy**
```bash
docker exec myshop_redis redis-cli CONFIG SET maxmemory-policy allkeys-lru
```

---

### 8. OpenAI API Rate Limit Exceeded

**Symptoms**:
- "Rate limit exceeded" errors
- 429 HTTP status codes

**Solutions**:

**A. Implement exponential backoff**
```php
// Already implemented in OpenAIEmbeddingService
// Retries with 1s, 2s, 4s delays
```

**B. Reduce concurrent requests**
```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async:
                options:
                    prefetch_count: 1  # Process one at a time
```

**C. Upgrade OpenAI tier**
- Contact OpenAI to increase rate limits

---

## Error Codes Reference

| Error Code | Meaning | Solution |
|------------|---------|----------|
| `EMBEDDING_GENERATION_FAILED` | OpenAI API error | Check API key, retry |
| `MONGODB_CONNECTION_FAILED` | MongoDB unreachable | Restart MongoDB container |
| `VECTOR_INDEX_MISSING` | Index not created | Run `app:vector-index:create` |
| `INVALID_EMBEDDING_DIMENSIONS` | Wrong dimension count | Re-sync embeddings |
| `CACHE_WRITE_FAILED` | Redis error | Check Redis connection |
| `SYNC_CONSISTENCY_ERROR` | MySQL/MongoDB out of sync | Run `app:embedding:verify-consistency` |

---

## Logs Analysis

### Application Logs
```bash
# View recent errors
docker logs myshop_php --tail=100 | grep ERROR

# Search-specific logs
docker logs myshop_php | grep "SemanticSearchService"

# Sync logs
docker logs myshop_php | grep "ProductEmbeddingSync"
```

### MongoDB Logs
```bash
# View MongoDB logs
docker logs myshop_mongodb --tail=100

# Check for slow queries
docker exec myshop_mongodb mongosh myshop --eval "
  db.system.profile.find({millis: {\$gt: 1000}}).pretty()
"
```

### Redis Logs
```bash
# View Redis logs
docker logs myshop_redis --tail=100

# Monitor live commands
docker exec myshop_redis redis-cli MONITOR
```

---

## Performance Profiling

### Enable Symfony Profiler
```env
# .env.local
APP_ENV=dev
```

Access profiler: `http://localhost/_profiler`

### Measure Search Performance
```bash
# Run performance tests
docker exec myshop_php bin/console app:test:performance

# Output:
# - P50 response time
# - P95 response time
# - Cache hit rate
# - MongoDB query latency
```

---

## Data Consistency Checks

### Verify MySQL ↔ MongoDB Sync
```bash
docker exec myshop_php bin/console app:embedding:verify-consistency

# Reports:
# - Products without embeddings
# - Orphaned embeddings
# - Dimension mismatches
# - Stale embeddings
```

### Fix Inconsistencies
```bash
# Re-sync all products (can take time)
docker exec myshop_php bin/console app:embedding:sync-all --force
```

---

## Emergency Procedures

### Disable Semantic Search (Fallback to Keyword)
```env
# .env
SEMANTIC_SEARCH_ENABLED=false
```

### Clear All Caches
```bash
# Clear Redis cache
docker exec myshop_redis redis-cli FLUSHDB

# Clear Symfony cache
docker exec myshop_php bin/console cache:clear
```

### Rebuild Vector Index
```bash
# Drop index
docker exec myshop_mongodb mongosh myshop --eval "
  db.product_embeddings.dropIndex('vector_index_cosine')
"

# Recreate
docker exec myshop_php bin/console app:vector-index:create
```

---

## Contact Support

If issues persist after following this guide:

1. **Collect Diagnostics**:
```bash
docker exec myshop_php bin/console app:health-check --verbose > health-report.txt
```

2. **Check Documentation**:
   - Admin Guide: `docs/ADMIN_GUIDE.md`
   - Developer Guide: `docs/DEVELOPER_GUIDE.md`
   - API Docs: `docs/API.md`

3. **Review Logs**:
```bash
docker logs myshop_php > app.log
docker logs myshop_mongodb > mongo.log
docker logs myshop_redis > redis.log
```

4. **Create GitHub Issue**:
   - Include health-report.txt
   - Include relevant logs
   - Describe steps to reproduce

---

**Last Updated**: February 7, 2026  
**Maintained By**: myshop Development Team
