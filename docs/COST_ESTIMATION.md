# Cost Estimation: Semantic Product Search

**Feature**: Spec-010  
**Version**: 1.0  
**Budget Target**: $50/month

## OpenAI API Costs

### Pricing Model

**text-embedding-3-small**:
- **Cost**: $0.02 per 1 million tokens
- **Input**: Product name + description OR search query
- **Output**: 1536-dimensional vector (no cost for output)

### Cost Breakdown

#### Initial Catalog Embedding 

| Catalog Size | Avg Tokens/Product | Total Tokens | Cost (One-Time) |
|--------------|---------------------|--------------|-----------------|
| 1,000 products | 100 | 100,000 | $0.002 |
| 10,000 products | 100 | 1,000,000 | $0.02 |
| 100,000 products | 100 | 10,000,000 | $0.20 |

**Assumptions**:
- Average product: 50 words name + description = ~100 tokens
- One-time cost on initial sync
- Re-sync only on product updates

#### Ongoing Search Costs

**Without Caching** (worst case):
| Monthly Searches | Avg Tokens/Query | Total Tokens | Monthly Cost |
|------------------|------------------|--------------|--------------|
| 10,000 | 10 | 100,000 | $0.002 |
| 50,000 | 10 | 500,000 | $0.01 |
| 100,000 | 10 | 1,000,000 | $0.02 |
| 500,000 | 10 | 5,000,000 | $0.10 |
| 1,000,000 | 10 | 10,000,000 | $0.20 |

**With 80% Cache Hit Rate** (target):
| Monthly Searches | Cache Hits (80%) | API Calls (20%) | Monthly Cost |
|------------------|------------------|-----------------|--------------|
| 10,000 | 8,000 | 2,000 | $0.0004 |
| 50,000 | 40,000 | 10,000 | $0.002 |
| 100,000 | 80,000 | 20,000 | $0.004 |
| 500,000 | 400,000 | 100,000 | $0.02 |
| 1,000,000 | 800,000 | 200,000 | $0.04 |

**Assumptions**:
- Average query: 5 words = ~10 tokens
- 80% cache hit rate (realistic with 1-hour TTL)
- No product updates (steady state)

#### Product Update Costs

| Updates/Month | Tokens/Update | Monthly Cost |
|---------------|---------------|--------------|
| 100 | 100 | $0.0002 |
| 1,000 | 100 | $0.002 |
| 10,000 | 100 | $0.02 |

---

## Total Cost Scenarios

### Scenario 1: Small Catalog (1K products, 10K searches/month)

| Component | Cost |
|-----------|------|
| Initial sync (one-time) | $0.002 |
| Search operations | $0.0004/month |
| Product updates (100/month) | $0.0002/month |
| **Monthly Total** | **$0.0006/month** |
| **Annual Projection** | **$0.007/year** |

✅ **Well within budget**

### Scenario 2: Medium Catalog (10K products, 100K searches/month)

| Component | Cost |
|-----------|------|
| Initial sync (one-time) | $0.02 |
| Search operations | $0.004/month |
| Product updates (1K/month) | $0.002/month |
| **Monthly Total** | **$0.006/month** |
| **Annual Projection** | **$0.07/year** |

✅ **Well within budget**

### Scenario 3: Large Catalog (100K products, 1M searches/month)

| Component | Cost |
|-----------|------|
| Initial sync (one-time) | $0.20 |
| Search operations | $0.04/month |
| Product updates (10K/month) | $0.02/month |
| **Monthly Total** | **$0.06/month** |
| **Annual Projection** | **$0.72/year** |

✅ **Well within budget**

### Scenario 4: High-Traffic E-commerce (10K products, 10M searches/month)

| Component | Cost |
|-----------|------|
| Initial sync (one-time) | $0.02 |
| Search operations (80% cache) | $0.40/month |
| Product updates (1K/month) | $0.002/month |
| **Monthly Total** | **$0.40/month** |
| **Annual Projection** | **$4.80/year** |

✅ **Well within budget**

---

## Infrastructure Costs

### MongoDB Atlas (if using managed service)

| Tier | Storage | RAM | Monthly Cost |
|------|---------|-----|--------------|
| M0 (Free) | 512 MB | Shared | $0 |
| M10 | 10 GB | 2 GB | $57 |
| M20 | 20 GB | 4 GB | $140 |

**Recommendation**: Self-hosted Docker (included in existing infrastructure)

### Self-Hosted MongoDB (Docker)

| Resource | Specification | Cost |
|----------|---------------|------|
| CPU | Shared with existing containers | $0 |
| RAM | 1-2 GB dedicated | $0 (existing server) |
| Storage | 5 GB (10K products) | $0 (existing disk) |
| **Monthly Total** | | **$0** |

### Redis (Cache)

| Resource | Specification | Cost |
|----------|---------------|------|
| RAM | 256 MB dedicated | $0 (existing Redis instance) |
| **Monthly Total** | | **$0** |

---

## Cost Optimization Strategies

### 1. Increase Cache Hit Rate

**Current**: 80% hit rate  
**Target**: 90% hit rate

**Impact**:
- 1M searches/month: $0.04 → $0.02 (50% savings)

**Actions**:
- Increase cache TTL from 1h to 2h
- Preemptively cache popular queries
- Implement query normalization (lowercase, trim)

### 2. Batch Product Updates

**Current**: Real-time sync on each update  
**Optimization**: Batch updates every 5 minutes

**Impact**:
- 10,000 updates/month: $0.02 → $0.01 (50% savings)
- Acceptable staleness: <5 minutes

**Actions**:
- Buffer updates in memory
- Flush to OpenAI/MongoDB on schedule
- Immediate flush for high-priority products

### 3. Reduce Embedding Dimensions

**Current**: text-embedding-3-small (1536 dimensions, $0.02/1M tokens)  
**Alternative**: text-embedding-ada-002 (1536 dimensions, $0.0001/1M tokens) - DEPRECATED

⚠️ **Not recommended**: Newer models provide better quality

### 4. Smart Re-Embedding

**Current**: Re-embed on any product update  
**Optimization**: Re-embed only if name/description changed significantly

**Impact**:
- Reduce unnecessary API calls by ~30%

**Implementation**:
```php
if ($this->hasSignificantChange($oldProduct, $newProduct)) {
    $this->syncService->syncUpdate($newProduct);
}
```

---

## Cost Monitoring

### Real-Time Tracking

**Command**:
```bash
docker exec myshop_php bin/console app:metrics:cost --period=30days
```

**Output**:
```
OpenAI API Usage (Last 30 Days):
- Total API Calls: 5,420
- Total Tokens: 54,200
- Estimated Cost: $0.00108 USD
- Projected Monthly Cost: $0.00108 USD
- Budget Status: ✓ Under budget ($0.00108 / $50.00)

Top 10 Most Expensive Operations:
1. Initial catalog sync: $0.0002 (200,000 tokens)
2. Product batch update: $0.0001 (100,000 tokens)
...
```

### Alerts

**Cost Alert Threshold**: $40/month (80% of budget)

**.env Configuration**:
```env
COST_ALERT_THRESHOLD_MONTHLY=40
ALERT_EMAIL=admin@example.com
```

**Automatic Actions on Alert**:
1. Send email to admin
2. Log warning to monitoring system
3. Optionally disable semantic search (fallback to keyword)

---

## Budget Scenarios

Given $50/month budget:

### Maximum Supported Load

**With 80% cache hit rate**:
- **Max Searches**: ~625 million searches/month ($50 ÷ $0.00008 per search)
- **Realistic Estimate**: ~10-50 million searches/month (with updates)

**Conclusion**: Budget is more than sufficient for any realistic e-commerce traffic.

### Budget Allocation

| Category | Allocation | Max Cost |
|----------|-----------|----------|
| Search operations | 60% | $30/month |
| Product updates | 20% | $10/month |
| Buffer/misc | 20% | $10/month |

---

## Cost Comparison: Semantic vs. Traditional Search

### Traditional Search (MySQL FULLTEXT)

**Costs**:
- OpenAI API: $0
- Infrastructure: Existing MySQL (no additional cost)

**Tradeoffs**:
- Lower search relevance
- No natural language understanding
- No synonym matching

### Semantic Search

**Additional Costs**:
- OpenAI API: ~$0.01-$1.00/month (depending on traffic)
- MongoDB: $0 (self-hosted) or $57+/month (Atlas)
- Development: One-time implementation cost

**Benefits**:
- 30-50% better search relevance (estimated)
- Natural language queries
- Synonym and context understanding
- Better customer experience → higher conversion

**ROI Calculation**:
- If semantic search improves conversion by just 0.1%, break-even at ~$1000/month revenue

---

## Recommendations

1. ✅ **Start with self-hosted MongoDB** (no additional cost)
2. ✅ **Use text-embedding-3-small** (best quality-to-cost ratio)
3. ✅ **Maintain 80%+ cache hit rate** (monitor weekly)
4. ✅ **Set up cost alerts** ($40/month threshold)
5. ✅ **Review costs monthly** (adjust cache TTL if needed)

**Expected Monthly Cost**: $0.01-$1.00 (well under $50 budget)

---

**Last Updated**: February 7, 2026  
**Budget Owner**: Operations Team  
**Review Schedule**: Monthly
