# API Documentation: Semantic Product Search

**Feature**: Spec-010 Semantic Search  
**Version**: 1.0  
**Base URL**: `/api`

## Authentication

All API endpoints require authentication via session cookie or Bearer token.

## Endpoints

### Search Products

Search products using semantic or keyword mode.

**Endpoint**: `GET /api/products/search`

**Query Parameters**:
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `q` | string | Yes | - | Search query (2-500 chars) |
| `mode` | string | No | `keyword` | Search mode: `semantic` or `keyword` |
| `limit` | integer | No | `10` | Results per page (1-100) |
| `offset` | integer | No | `0` | Pagination offset |
| `category` | string | No | - | Filter by category |
| `min_similarity` | float | No | `0.6` | Minimum similarity score (0.0-1.0, semantic only) |

**Request Example**:
```http
GET /api/products/search?q=gaming%20laptop&mode=semantic&limit=20&category=electronics
Host: localhost
Cookie: PHPSESSID=abc123
```

**Response 200 OK**:
```json
{
  "products": [
    {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "name": "ROG Gaming Laptop",
      "description": "High-performance laptop for gaming",
      "price": {
        "amount": 129999,
        "currency": "USD"
      },
      "category": "electronics",
      "stock": 5,
      "in_stock": true,
      "image_url": "/images/products/rog-laptop.jpg",
      "similarity_score": 0.92
    }
  ],
  "metadata": {
    "mode": "semantic",
    "total_results": 45,
    "returned_results": 1,
    "execution_time_ms": 234.5
  }
}
```

**Response 400 Bad Request**:
```json
{
  "error": "Validation failed",
  "details": {
    "q": ["Query must be at least 2 characters long"],
    "limit": ["Limit must be between 1 and 100"]
  }
}
```

**Response 500 Internal Server Error**:
```json
{
  "error": "Search temporarily unavailable",
  "fallback": "keyword",
  "message": "Semantic search unavailable, using keyword fallback"
}
```

---

### Get Search Metrics (Admin Only)

Retrieve search performance metrics.

**Endpoint**: `GET /admin/api/search-metrics`

**Query Parameters**:
| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| `period` | string | No | `24h` | Time period: `1h`, `24h`, `7d`, `30d` |

**Response 200 OK**:
```json
{
  "summary": {
    "total_searches": 1523,
    "semantic_searches": 892,
    "keyword_searches": 631,
    "average_response_time_ms": 189.4,
    "cache_hit_rate": 82.3,
    "empty_search_rate": 5.2
  },
  "percentiles": {
    "p50": 145.2,
    "p95": 450.8,
    "p99": 1203.5
  },
  "costs": {
    "total_api_calls": 268,
    "estimated_cost_usd": 0.54,
    "projected_monthly_cost_usd": 16.20
  }
}
```

---

## Virtual Assistant Tool

### semantic_product_search

AI tool accessible by customer virtual assistant.

**Tool Signature**:
```typescript
semantic_product_search(
  query: string,
  limit?: number,
  category?: string
): ProductSearchResult
```

**Example Usage** (internal):
```
Customer: "I need a laptop for video editing"
VA: semantic_product_search("laptop video editing", 5)
VA Response: "I found 5 laptops suitable for video editing. 
             The Dell XPS 15 has excellent performance..."
```

---

## Rate Limiting

- **Anonymous**: 30 requests/minute
- **Authenticated**: 60 requests/minute
- **Admin**: Unlimited

**Rate Limit Headers**:
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1675789200
```

---

## Error

 Codes

| Code | Description | Solution |
|------|-------------|----------|
| `400` | Validation error | Check request parameters |
| `401` | Unauthorized | Provide valid authentication |
| `403` | Forbidden | Insufficient permissions |
| `429` | Rate limit exceeded | Wait and retry |
| `500` | Internal server error | Contact support |
| `503` | Service unavailable | Retry with exponential backoff |

---

**Last Updated**: February 7, 2026
