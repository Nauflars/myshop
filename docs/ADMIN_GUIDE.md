# Admin Guide: Semantic Product Search

**Feature**: Spec-010 Semantic Product Search  
**Version**: 1.0  
**Last Updated**: February 2026  
**Audience**: System Administrators, Operations Team

## Table of Contents

1. [Overview](#overview)
2. [Admin Dashboard](#admin-dashboard)
3. [Product Management](#product-management)
4. [Search Metrics](#search-metrics)
5. [Cache Management](#cache-management)
6. [Troubleshooting](#troubleshooting)
7. [Maintenance Tasks](#maintenance-tasks)

---

## Overview

Semantic Search enables customers to find products using natural language queries like "laptop for gaming" or "affordable phone for photography". The system uses OpenAI embeddings and MongoDB vector search to understand meaning and intent, not just keywords.

### Key Benefits
- **Better Search Relevance**: Finds products even when exact keywords don't match
- **Natural Language**: Customers search how they speak
- **Automatic Sync**: Product changes auto-update search index
- **Cost Optimization**: Redis caching reduces API costs by 80%

### How It Works
1. Admin creates/updates product → System generates embedding via OpenAI
2. Embedding stored in MongoDB with product reference
3. Customer searches → Query embedded → Vector similarity search
4. Results ranked by semantic relevance (0.0-1.0 similarity score)

---

## Admin Dashboard

### Access Search Metrics

**URL**: `/admin/search-metrics`  
**Authentication**: Admin role required

The dashboard shows real-time metrics:

- **Total Searches**: Number of semantic searches in last 24 hours
- **Average Response Time**: P50, P95, P99 percentiles
- **Cache Hit Rate**: Percentage of cached queries (target: ≥80%)
- **OpenAI API Cost**: Estimated daily/monthly cost
- **MongoDB Performance**: Query execution times
- **Empty Search Rate**: Queries returning no results

### Dashboard Features

**Real-Time Updates**: Auto-refreshes every 30 seconds

**Charts**:
- Response Time Distribution (histogram)
- Search Mode Usage (semantic vs keyword pie chart)
- Cache Performance Over Time (line graph)

**Health Checks**:
- ✅ **GREEN**: All systems operational
- ⚠️ **YELLOW**: Degraded performance (fallback to keyword)
- ❌ **RED**: Critical failure (service down)

---

## Product Management

### Product Sync Automation

**When embeddings are created**:
- Admin creates new product via `/admin/products/new`
- Admin updates product name or description
- Product data copied from another source

**When embeddings are updated**:
- Admin edits product description
- Admin changes product name
- Bulk update operations

**When embeddings are deleted**:
- Admin deletes product
- Product archived or inactive

### Sync Status

Check sync status for a product:

```bash
# Via Symfony console
docker exec myshop_php bin/console app:embedding:status <product-id>

# Output:
# Product: Gaming Laptop XYZ
# MySQL: ✓ Exists
# MongoDB: ✓ Synced (embedding dimensions: 1536)
# Last Updated: 2026-02-07 14:30:00
# Similarity Score: N/A (not searched yet)
```

### Manual Re-Sync

If embeddings get out of sync:

**Single Product**:
```bash
docker exec myshop_php bin/console app:embedding:sync <product-id>
```

**All Products** (use during maintenance):
```bash
docker exec myshop_php bin/console app:embedding:sync-all

# Options:
--batch-size=50    # Process 50 products at a time (default: 100)
--dry-run          # Preview changes without executing
--force            # Re-sync even if already synced
```

**Specific Category**:
```bash
docker exec myshop_php bin/console app:embedding:sync-all --category=electronics
```

### Sync Failure Handling

If OpenAI API or MongoDB fails:
- ✅ **Product saves to MySQL** (source of truth preserved)
- 📋 **Sync job queued** for retry (Symfony Messenger)
- 🔄 **Automatic retry** with exponential backoff (3 attempts)
- 📧 **Alert sent** if all retries fail

View failed jobs:
```bash
docker exec myshop_php bin/console messenger:failed:show

# Retry failed jobs:
docker exec myshop_php bin/console messenger:failed:retry
```

---

## Search Metrics

### Understanding Metrics

**Response Time Targets**:
- **P50 (median)**: <2 seconds for cached queries
- **P95**: <5 seconds for uncached queries
- **P99**: <8 seconds (includes worst-case scenarios)

**Cache Hit Rate**:
- **Target**: ≥80% hit rate
- **Good**: 70-80% (acceptable performance)
- **Poor**: <70% (investigate cache issues or increase TTL)

**OpenAI API Cost**:
- **Embedding Model**: text-embedding-3-small ($0.02 per 1M tokens)
- **Average Query**: ~100 tokens = $0.000002 per search
- **Estimated Monthly**: Based on 30-day rolling average

### Metric Alerts

System automatically alerts when:
- Response time P95 > 10 seconds (performance degradation)
- Cache hit rate < 60% (caching not effective)
- OpenAI API cost > $100/month (budget threshold)
- Error rate > 10% in 5-minute window (system issues)

View alerts:
```bash
docker exec myshop_php bin/console app:alerts:list --level=warning
```

---

## Cache Management

### Redis Cache Overview

**Purpose**: Cache query embeddings to reduce OpenAI API calls  
**Key Format**: `search:embedding:{md5(query)}`  
**TTL**: 3600 seconds (1 hour, configurable)  
**Storage**: ~6KB per cached query (1536 floats as JSON)

### Cache Commands

**View Cache Stats**:
```bash
docker exec myshop_redis redis-cli INFO STATS

# Key metrics:
# - keyspace_hits: Cache hits
# - keyspace_misses: Cache misses
# - evicted_keys: Keys evicted due to memory limits
```

**Clear Embedding Cache**:
```bash
docker exec myshop_php bin/console app:cache:clear-embeddings

# Clears all search:embedding:* keys
# Use after changing embedding model or dimensions
```

**Inspect Cached Query**:
```bash
docker exec myshop_redis redis-cli GET "search:embedding:$(echo -n 'laptop' | md5sum | cut -d' ' -f1)"

# Returns: JSON array with 1536 float dimensions
```

**Monitor Cache in Real-Time**:
```bash
docker exec myshop_redis redis-cli MONITOR | grep "search:embedding"

# Shows all cache operations live
```

### Cache Configuration

Edit `.env` or `.env.local`:

```env
# Embedding cache TTL (seconds)
EMBEDDING_CACHE_TTL=3600    # 1 hour (default)

# Options:
# 1800 = 30 minutes (high-traffic sites, frequent query changes)
# 7200 = 2 hours (stable catalogs, cost optimization)
# 86400 = 24 hours (rarely changing queries, maximum cost savings)
```

---

## Troubleshooting

### Semantic Search Not Working

**Symptoms**: Searches return no results or keyword fallback used

**Check**bot:
1. **MongoDB Connection**:
   ```bash
   docker exec myshop_mongodb mongosh --eval "db.adminCommand('ping')"
   ```

2. **OpenAI API Key**:
   ```bash
   docker exec myshop_php php -r "echo getenv('OPENAI_API_KEY') ? 'SET' : 'NOT SET';"
   ```

3. **Product Embeddings Exist**:
   ```bash
   docker exec myshop_mongodb mongosh myshop --eval "db.product_embeddings.countDocuments()"
   ```

4. **Vector Index Created**:
   ```bash
   docker exec myshop_php bin/console app:vector-index:status
   ```

### Slow Search Performance

**Symptoms**: Response time > 10 seconds consistently

**Solutions**:
1. **Check MongoDB Query Performance**:
   ```bash
   docker exec myshop_mongodb mongosh myshop --eval "db.product_embeddings.find().explain('executionStats')"
   ```

2. **Verify Vector Index Used**:
   Should show `IXSCAN` stage, not `COLLSCAN`

3. **Increase Redis Cache TTL**:
   Reduce OpenAI API calls by caching longer

4. **Enable MongoDB Connection Pooling**:
   Check `config/packages/mongodb_pooling.yaml` settings

5. **Limit Result Size**:
   Default: 50 products max. Lower if performance issues persist.

### High OpenAI API Costs

**Symptoms**: Costs exceeding $50/month budget

**Solutions**:
1. **Increase Cache TTL**: Cache queries longer (e.g., 2-4 hours)
2. **Check Cache Hit Rate**: Should be ≥80%, investigate if lower
3. **Review Query Patterns**: Identify and optimize repetitive queries
4. **Implement Query Preprocessing**: Remove stop words, normalize queries

**View Cost Breakdown**:
```bash
docker exec myshop_php bin/console app:metrics:cost --period=30days

# Shows:
# - Total API calls
# - Total tokens used
# - Estimated cost
# - Cost per query
# - Top 10 most expensive queries
```

---

## Maintenance Tasks

### Daily Tasks

**Monitor Dashboard**:
- Check search metrics dashboard for anomalies
- Verify cache hit rate ≥70%
- Review error logs for failures

### Weekly Tasks

**Review Sync Status**:
```bash
docker exec myshop_php bin/console app:embedding:health-check
```

**Analyze Search Quality**:
```bash
docker exec myshop_php bin/console app:search:quality-report --days=7
```

### Monthly Tasks

**Cost Review**:
- Analyze OpenAI API usage and costs
- Optimize cache TTL if costs exceed budget
- Review query patterns for optimization opportunities

**Performance Tuning**:
- Run performance tests: `bin/console app:test:performance`
- Review P95 response times
- Optimize slow queries

**Data Consistency Check**:
```bash
docker exec myshop_php bin/console app:embedding:verify-consistency

# Reports:
# - Products in MySQL without MongoDB embeddings
# - Orphaned MongoDB embeddings (product deleted)
# - Dimension mismatches
# - Stale embeddings (not updated after product change)
```

### Quarterly Tasks

**Embedding Model Upgrade**:
- Check OpenAI for newer embedding models
- Test new model in staging environment
- Plan migration if performance/cost improvements significant

**Infrastructure Scaling**:
- Review MongoDB storage usage
- Plan sharding if collection > 1M documents
- Evaluate Redis memory usage and eviction policy

---

## Configuration Reference

### Environment Variables

```env
# OpenAI Configuration
OPENAI_API_KEY=sk-...                    # Required
OPENAI_EMBEDDING_MODEL=text-embedding-3-small   # Default

# MongoDB Configuration
MONGODB_URL=mongodb://root:rootpassword@mongodb:27017
MONGODB_DATABASE=myshop

# Cache Configuration
EMBEDDING_CACHE_TTL=3600                 # 1 hour
REDIS_URL=redis://redis:6379

# Performance
MAX_SEARCH_RESULTS=50                    # Limit per query
SEARCH_TIMEOUT_SECONDS=5                 # OpenAI API timeout
MONGODB_QUERY_TIMEOUT_MS=3000            # MongoDB query timeout

# Monitoring
ALERT_EMAIL=admin@example.com
COST_ALERT_THRESHOLDMONTHLY=50          # USD
```

### Service Health Endpoints

- **Overall Health**: `GET /health`
- **MongoDB Status**: `GET /health/mongodb`
- **OpenAI Status**: `GET /health/openai`
- **Redis Status**: `GET /health/redis`
- **Search Metrics**: `GET /admin/search-metrics`

---

## Support

### Getting Help

**Documentation**:
- Developer Guide: `docs/DEVELOPER_GUIDE.md`
- API Reference: `docs/API.md`
- Troubleshooting: `docs/TROUBLESHOOTING.md`

**Logs**:
```bash
# Application logs
docker logs myshop_php

# MongoDB logs
docker logs myshop_mongodb

# Redis logs
docker logs myshop_redis

# Nginx access logs
docker logs myshop_nginx
```

**Console Commands**:
```bash
docker exec myshop_php bin/console list app:

# Shows all available semantic search commands
```

---

## Appendix: Glossary

- **Embedding**: Numerical vector representation of text (1536 dimensions)
- **Vector Search**: Similarity search using cosine distance between embeddings
- **Cosine Similarity**: Measure of similarity between two vectors (0.0-1.0)
- **Cache Hit Rate**: Percentage of queries served from cache vs. API
- **P50/P95/P99**: Performance percentiles (50th, 95th, 99th percentile response times)
- **Sync**: Process of keeping MongoDB embeddings consistent with MySQL products
- **TTL**: Time To Live - how long data is cached before expiration

---

**Document Version**: 1.0  
**Last Reviewed**: February 7, 2026  
**Next Review**: May 7, 2026  
**Maintained By**: myshop Development Team
