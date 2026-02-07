# Error Handling Strategy - Semantic Product Search

**Spec**: 010-semantic-search  
**Phase**: 6 - Error Handling & Reliability  
**Status**: Production-Ready  
**Last Updated**: 2026-02-07

## Overview

This document describes the comprehensive error handling and reliability strategy for the semantic product search system. The strategy ensures graceful degradation, automatic recovery, and production-grade reliability even when dependencies (MongoDB, OpenAI API) become unavailable.

---

## Core Principles

### 1. **Graceful Degradation**
The system continues operating even when components fail:
- **Semantic search failure** → Fallback to keyword search
- **MongoDB unavailable** → Circuit breaker blocks requests, jobs queued for retry
- **OpenAI API down** → Circuit breaker activates, automatic recovery when service restored

### 2. **User-Friendly Error Messages**
Technical errors are translated to Spanish user-friendly messages:
- ✅ `"El servicio está temporalmente no disponible"`
- ❌ `"MongoDB\Driver\Exception\ConnectionTimeoutException"`

### 3. **Automatic Recovery**
Services self-heal without manual intervention:
- **Circuit breakers** reset after timeout periods
- **Failed jobs** automatically retry with exponential backoff
- **Health checks** verify system readiness

### 4. **Comprehensive Logging**
All failures are logged with full context:
- Product ID, operation type, error stack traces
- Circuit breaker state transitions
- High failure rate alerts

---

## Error Handling Components

### 1. Circuit Breakers (T091, T092)

#### MongoDB Circuit Breaker
**Location**: `MongoDBEmbeddingRepository.php`

**Configuration**:
```php
CIRCUIT_BREAKER_THRESHOLD = 5     // failures before opening
CIRCUIT_BREAKER_TIMEOUT = 60      // seconds before half-open
QUERY_TIMEOUT_MS = 3000           // 3 second query timeout
```

**States**:
1. **CLOSED** (normal): All requests pass through
2. **OPEN** (failure): All requests blocked, throws `RuntimeException`
3. **HALF-OPEN** (testing): After 60s timeout, allows 1 request to test recovery

**Behavior**:
- After **5 consecutive failures**, circuit opens
- All MongoDB operations blocked for **60 seconds**
- After timeout, enters **half-open** state
- 1 successful request → circuit **closes** (service recovered)
- 1 failed request → circuit **opens** again (service still down)

**Methods**:
```php
isCircuitBreakerOpen(): bool
recordCircuitBreakerFailure(): void
resetCircuitBreaker(): void
```

**Applied To**:
- `searchSimilar()` - Vector similarity search
- `save()` - Save single embedding
- `saveBatch()` - Save multiple embeddings
- `findByProductId()` - Retrieve embedding
- `delete()` / `deleteAll()` - Delete operations

#### OpenAI Circuit Breaker
**Location**: `OpenAIEmbeddingService.php`

**Configuration**:
```php
CIRCUIT_BREAKER_THRESHOLD = 5
CIRCUIT_BREAKER_TIMEOUT = 60
REQUEST_TIMEOUT = 10              // 10 second HTTP timeout
MAX_RETRIES = 3                   // Retry with exponential backoff
```

**Behavior**:
- Same 3-state pattern as MongoDB circuit breaker
- Integrated with **retry logic** (attempts before circuit opens)
- Monitors **rate limit headers** (warns at 80% utilization)

---

### 2. Retry Logic with Exponential Backoff (T091)

**Location**: `OpenAIEmbeddingService::generateEmbedding()`

**Configuration**:
```php
MAX_RETRIES = 3
RETRY_DELAY_MS = 1000             // Base delay
```

**Backoff Pattern**:
- **Attempt 1**: Immediate
- **Attempt 2**: Wait 1 second (`1000ms * 2^0`)
- **Attempt 3**: Wait 2 seconds (`1000ms * 2^1`)
- **Attempt 4**: Wait 4 seconds (`1000ms * 2^2`)

**Logic**:
```php
for ($attempt = 1; $attempt <= MAX_RETRIES; $attempt++) {
    try {
        $embedding = $this->aiPlatform->request($embeddingRequest);
        $this->resetCircuitBreaker(); // Success
        return $embedding['embedding'];
    } catch (\Exception $e) {
        $this->recordCircuitBreakerFailure();
        if ($attempt < MAX_RETRIES) {
            $delay = RETRY_DELAY_MS * (2 ** ($attempt - 1));
            usleep($delay * 1000);
        }
    }
}
throw new \RuntimeException('OpenAI API failed after retries');
```

---

### 3. Fallback Mechanisms (T093)

#### Semantic → Keyword Search
**Location**: `SearchFacade::search()`

**Flow**:
```
User search request
  ↓
executeSemanticSearch()
  ↓ (exception)
executeKeywordSearch()
  ↓ (exception)
Return empty SearchResult
```

**Implementation**:
```php
try {
    return $this->executeSemanticSearch($query);
} catch (\Exception $e) {
    $this->logger->warning('Semantic search failed, falling back to keyword');
    return $this->executeKeywordSearch($query);
}
```

**User Experience**:
- Semantic search fails → User sees keyword search results (slightly less accurate)
- Both fail → User sees "No results found" (graceful empty state)
- No technical errors exposed to user

---

### 4. Dead Letter Queue (T095)

**Purpose**: Record failed embedding sync jobs for later retry

**Database Table**: `failed_embedding_jobs`
```sql
CREATE TABLE failed_embedding_jobs (
    id INT AUTO_INCREMENT,
    product_id VARCHAR(36),           -- UUID
    operation VARCHAR(20),             -- create/update/delete
    error_message TEXT,
    error_trace TEXT,
    payload JSON,                      -- Product data snapshot
    attempts INT DEFAULT 0,
    failed_at DATETIME,
    last_retry_at DATETIME,
    retry_after DATETIME,              -- Exponential backoff
    status VARCHAR(20),                -- failed/retrying/resolved/abandoned
    resolved_at DATETIME,
    PRIMARY KEY(id)
);
```

**Service**: `FailedJobRegistry`

**Exponential Backoff Schedule**:
| Attempt | Retry After | Total Wait Time |
|---------|-------------|-----------------|
| 1       | 1 minute    | 1m              |
| 2       | 5 minutes   | 6m              |
| 3       | 30 minutes  | 36m             |
| 4       | 2 hours     | 2h 36m          |
| 5       | 24 hours    | 26h 36m         |

**Status Flow**:
```
failed → retrying → resolved (success)
failed → retrying → failed → ... → abandoned (max attempts)
```

**Integration**: `ProductEmbeddingListener`
```php
} catch (\Exception $e) {
    $this->failedJobRegistry->recordFailure($entity, 'create', $e);
    $this->logger->error('Failed to sync embedding', [...]);
}
```

**Retry Command** (T097):
```bash
php bin/console app:retry-failed-embeddings
php bin/console app:retry-failed-embeddings --limit=50
php bin/console app:retry-failed-embeddings --stats-only
```

---

### 5. High Failure Rate Alerting (T096)

**Purpose**: Trigger critical alerts when failure rate exceeds threshold

**Service**: `FailureRateMonitor`

**Configuration**:
```php
WINDOW_SECONDS = 300               // 5 minute sliding window
THRESHOLD_PERCENT = 10.0           // 10% failure rate threshold
ALERT_COOLDOWN_SECONDS = 900       // 15 minutes between alerts
```

**Sliding Window Counter**:
- Uses **Redis cache** with 5-minute TTL
- Tracks `success_count` and `failure_count`
- Calculates `failure_rate = (failures / total) * 100`

**Alert Trigger Logic**:
```php
$stats = $this->failureRateMonitor->getStatistics();

if ($stats['failure_rate'] > 10.0) {
    $this->logger->critical('HIGH FAILURE RATE ALERT', [
        'failure_rate' => '25.5%',
        'threshold' => '10%',
        'total_operations' => 100,
        'failed_operations' => 25,
        'window_seconds' => 300,
        'recommendation' => 'Check MongoDB/OpenAI health, review circuit breaker status'
    ]);
}
```

**Integration**: `ProductEmbeddingListener`
```php
$this->syncUseCase->onCreate($entity);
$this->failureRateMonitor->recordSuccess();  // Success

} catch (\Exception $e) {
    $this->failureRateMonitor->recordFailure($productId, 'create', $e);  // Failure + Alert
}
```

**Production Monitoring**:
- Alerts written to **error logs** (`var/log/prod.log`)
- Integrate with monitoring systems (Sentry, Datadog, Prometheus)
- Example alert:
  ```
  [2026-02-07 14:30:00] app.CRITICAL: HIGH FAILURE RATE ALERT: Embedding sync failing at 15.3% rate (threshold: 10%)
  ```

---

### 6. User-Friendly Error Messages (T094)

**Purpose**: Translate technical exceptions to Spanish user-friendly messages

**Service**: `ErrorMessageTranslator`

**Translation Examples**:

| Technical Error | User-Friendly Message (Spanish) |
|----------------|----------------------------------|
| `MongoDB service unavailable (circuit breaker open)` | `El servicio de búsqueda está temporalmente no disponible. Estamos trabajando en resolverlo. Por favor, intenta nuevamente en unos minutos.` |
| `OpenAI API request failed` | `El servicio de búsqueda inteligente no está disponible temporalmente. Hemos activado el modo de búsqueda alternativo.` |
| `Connection timeout after 3000ms` | `La búsqueda está tardando más de lo esperado. Por favor, intenta con términos más específicos o inténtalo nuevamente.` |
| `Rate limit exceeded` | `Estamos procesando demasiadas solicitudes. Por favor, espera unos segundos e intenta nuevamente.` |
| `Embedding dimensions: expected 1536, got 512` | `Ha ocurrido un error procesando el producto. Por favor, contacta al soporte técnico.` |

**Usage in Controller** (`ProductController`):
```php
} catch (\Exception $e) {
    $errorData = $this->errorTranslator->translateWithStatus($e, 'search');
    return $this->json([
        'error' => $errorData['message'],  // User-friendly Spanish message
    ], $errorData['status_code']);         // Appropriate HTTP status (503, 429, etc.)
}
```

**HTTP Status Code Mapping**:
- `400` - Invalid input (validation errors)
- `429` - Rate limit exceeded
- `503` - Service unavailable (circuit breaker, MongoDB/OpenAI down)
- `504` - Gateway timeout
- `500` - Internal server error (generic fallback)

**Logging**:
- Technical details logged to `var/log/prod.log`:
  ```php
  $this->logger->error('Error translator caught exception', [
      'exception_class' => 'MongoDB\Driver\Exception\ConnectionTimeoutException',
      'message' => 'Connection timeout',
      'file' => '/src/Repository/MongoDBRepository.php',
      'line' => 215,
      'trace' => $exception->getTraceAsString()
  ]);
  ```
- User sees only friendly message, no stack traces

---

### 7. Input Validation (T101, T102)

#### Embedding Dimension Validation (T101)
**Location**: `OpenAIEmbeddingService::generateEmbedding()`

**Validation**:
```php
$expectedDimensions = 1536;  // text-embedding-3-small
$actualDimensions = count($embedding);

if ($actualDimensions !== $expectedDimensions) {
    throw new \RuntimeException(sprintf(
        'Invalid embedding dimensions: expected %d, got %d',
        $expectedDimensions,
        $actualDimensions
    ));
}
```

**Purpose**: Detect API changes or model configuration errors

#### Description Length Validation (T102)
**Location**: `ProductEmbeddingSyncService::generateEmbeddingText()`

**Validation**:
```php
MAX_RAW_DESCRIPTION_LENGTH = 32000;  // ~8000 tokens

if (strlen($rawDescription) > MAX_RAW_DESCRIPTION_LENGTH) {
    throw new \InvalidArgumentException(sprintf(
        'Product description is too long: %d characters (max: %d)',
        strlen($rawDescription),
        MAX_RAW_DESCRIPTION_LENGTH
    ));
}
```

**Purpose**:
- Prevent OpenAI API rejection (8191 token limit)
- Early validation before expensive API call
- User-friendly error message via `ErrorMessageTranslator`

**Note**: Automatic truncation at 8000 chars with sentence boundary detection for shorter descriptions

---

### 8. Request Timeouts (T099, T100)

#### OpenAI API Timeout
**Configuration**: `OpenAIEmbeddingService`
```php
REQUEST_TIMEOUT = 10;  // 10 seconds
```

**Implementation**:
```php
$options = [
    'timeout' => self::REQUEST_TIMEOUT,
    'headers' => [
        'Authorization' => 'Bearer ' . $apiKey,
    ],
];
$response = $this->httpClient->request('POST', $url, $options);
```

**Behavior**:
- Request exceeds 10 seconds → `TimeoutException`
- Caught by retry logic → exponential backoff
- After 3 retries → circuit breaker may open

#### MongoDB Query Timeout
**Configuration**: `MongoDBEmbeddingRepository`
```php
QUERY_TIMEOUT_MS = 3000;  // 3 seconds
```

**Purpose**:
- Prevent slow queries from blocking application
- Triggers circuit breaker on repeated timeouts
- Fallback to keyword search (via `SearchFacade`)

---

## Error Handling Flow Diagrams

### Product Embedding Sync Flow
```
Product CRUD Operation (Create/Update/Delete)
  ↓
ProductEmbeddingListener catches event
  ↓
Try: SyncProductEmbedding use case
  ↓
  ├─ Success → FailureRateMonitor.recordSuccess()
  ↓
  └─ Failure (Exception)
      ├─ FailedJobRegistry.recordFailure() → Dead letter queue
      ├─ FailureRateMonitor.recordFailure() → Check alert threshold
      └─ Logger.error() → Detailed error log
```

### Semantic Search Flow with Fallbacks
```
User Search Request
  ↓
SearchFacade.search(query, mode='semantic')
  ↓
Is MongoDB circuit breaker open?
  ├─ Yes → Skip semantic, go to keyword search
  └─ No → Continue
  ↓
Try: SemanticSearchService.search()
  ├─ Generate query embedding (OpenAI)
  │   ├─ Is OpenAI circuit breaker open? → Throw exception
  │   ├─ Retry up to 3 times with backoff
  │   └─ Validate embedding dimensions (1536)
  ↓
  ├─ Search MongoDB for similar vectors
  │   ├─ Check circuit breaker before query
  │   ├─ Apply 3s query timeout
  │   └─ Record success/failure for circuit breaker
  ↓
  ├─ Success → Return SearchResult
  ↓
  └─ Failure (Exception)
      ↓
      Fallback: KeywordSearchService.search()
      ├─ MySQL FULLTEXT search
      └─ Return SearchResult (or empty if fails)
```

### Circuit Breaker State Machine
```
┌─────────┐
│ CLOSED  │ (Normal operation)
└────┬────┘
     │ 5 failures
     ↓
┌─────────┐
│  OPEN   │ (Block all requests for 60s)
└────┬────┘
     │ 60s timeout
     ↓
┌─────────┐
│HALF-OPEN│ (Test with 1 request)
└────┬────┘
     │
     ├→ Success → CLOSED (Service recovered)
     └→ Failure → OPEN (Still down, wait 60s again)
```

---

## Production Deployment Checklist

### Pre-Deployment

- [ ] **Run database migration** for `failed_embedding_jobs` table:
  ```bash
  php bin/console doctrine:migrations:migrate --no-interaction
  ```

- [ ] **Verify service registration** in `config/services.yaml`:
  - `ErrorMessageTranslator`
  - `FailedJobRegistry`
  - `FailureRateMonitor`

- [ ] **Test circuit breakers**:
  ```bash
  # Stop MongoDB
  docker-compose stop mongodb
  
  # Attempt search - should fallback to keyword search
  curl "http://localhost/api/products/search?q=laptop&mode=semantic"
  
  # Restart MongoDB
  docker-compose start mongodb
  ```

- [ ] **Test failed job retry**:
  ```bash
  # Check statistics
  php bin/console app:retry-failed-embeddings --stats-only
  
  # Retry failed jobs
  php bin/console app:retry-failed-embeddings --limit=100
  ```

- [ ] **Configure log monitoring**:
  - Set up alerts for `CRITICAL` level logs (high failure rate)
  - Monitor `circuit breaker` keywords in logs
  - Track error rates in monitoring dashboard

### Post-Deployment Monitoring

- [ ] **Monitor circuit breaker metrics**:
  ```bash
  # Check Redis cache for circuit breaker state
  redis-cli GET mongodb_circuit_breaker
  redis-cli GET openai_circuit_breaker
  ```

- [ ] **Monitor failure rate**:
  ```bash
  # Check failure rate statistics (via health endpoint or logs)
  curl http://localhost/health/detailed
  ```

- [ ] **Monitor dead letter queue**:
  ```sql
  SELECT status, COUNT(*) 
  FROM failed_embedding_jobs 
  GROUP BY status;
  ```

- [ ] **Set up cron job for retries**:
  ```cron
  */15 * * * * cd /var/www/myshop && php bin/console app:retry-failed-embeddings --limit=100 >> /var/log/retry-embeddings.log 2>&1
  ```

---

## Troubleshooting Guide

### High Failure Rate Alert

**Symptom**: `CRITICAL` log: `HIGH FAILURE RATE ALERT: Embedding sync failing at 25% rate`

**Diagnosis**:
1. Check MongoDB health:
   ```bash
   docker-compose ps mongodb
   curl http://localhost/health/detailed
   ```

2. Check OpenAI API status:
   - Visit: https://status.openai.com/
   - Check circuit breaker state in Redis:
     ```bash
     redis-cli GET openai_circuit_breaker
     ```

3. Review recent error logs:
   ```bash
   tail -f var/log/prod.log | grep -i "embedding\|circuit\|mongodb\|openai"
   ```

**Resolution**:
- If MongoDB down → Restart container, verify connection
- If OpenAI API down → Wait for circuit breaker to auto-close (60s after service restores)
- If persistent failures → Check network, firewall, API keys

### Circuit Breaker Stuck Open

**Symptom**: All semantic searches failing with "service unavailable" error

**Diagnosis**:
```bash
# Check circuit breaker state
redis-cli GET mongodb_circuit_breaker
# Output: {"is_open": true, "failures": 5, "opened_at": 1738937400}

redis-cli GET openai_circuit_breaker
# Output: {"is_open": true, "failures": 5, "opened_at": 1738937400}
```

**Resolution**:
1. Verify service is healthy:
   ```bash
   # MongoDB
   docker-compose exec mongodb mongosh --eval "db.adminCommand('ping')"
   
   # OpenAI (test embedding generation)
   php bin/console app:test-embedding "test query"
   ```

2. If service is healthy, manually reset circuit breaker:
   ```bash
   redis-cli DEL mongodb_circuit_breaker
   redis-cli DEL openai_circuit_breaker
   ```

3. Attempt request - circuit will re-open if service still down, close if healthy

### Failed Jobs Not Retrying

**Symptom**: Dead letter queue growing, jobs not resolving

**Diagnosis**:
```bash
# Check failed jobs statistics
php bin/console app:retry-failed-embeddings --stats-only

# Check specific job details
docker-compose exec mysql mysql -u root -ppassword myshop -e "
  SELECT id, product_id, operation, attempts, retry_after, status 
  FROM failed_embedding_jobs 
  WHERE status = 'failed' 
  ORDER BY failed_at DESC 
  LIMIT 10;
"
```

**Resolution**:
1. Verify cron job is running:
   ```bash
   crontab -l | grep retry-failed-embeddings
   ```

2. Manually trigger retry:
   ```bash
   php bin/console app:retry-failed-embeddings --limit=100
   ```

3. For abandoned jobs (>5 attempts), investigate root cause:
   - Check product still exists in database
   - Verify description length < 32000 chars
   - Check MongoDB/OpenAI services operational

---

## Testing Strategy

### Unit Tests
Location: `tests/Unit/`

- Test circuit breaker state transitions
- Test exponential backoff calculations
- Test error message translations
- Test validation logic (dimensions, description length)

### Integration Tests
Location: `tests/Integration/ErrorHandling/`

- **ErrorHandlingTest.php** (T103):
  - Failed job registry records failures
  - Exponential backoff retry scheduling
  - Job abandonment after max attempts
  - Failure rate monitoring
  - High failure rate alert triggering
  - User-friendly error message translation
  - Description length validation

### Manual Production Tests

1. **MongoDB Failure Scenario**:
   ```bash
   # Stop MongoDB
   docker-compose stop mongodb
   
   # Create/update product - should queue for retry
   curl -X POST http://localhost/api/products ...
   
   # Restart MongoDB
   docker-compose start mongodb
   
   # Run retry command - should succeed
   php bin/console app:retry-failed-embeddings
   ```

2. **OpenAI API Failure Scenario**:
   ```bash
   # Simulate API failure (temporarily set invalid API key)
   
   # Attempt semantic search - should fallback to keyword search
   curl "http://localhost/api/products/search?q=laptop&mode=semantic"
   
   # Restore valid API key
   
   # Wait 60s for circuit breaker to half-open and auto-recover
   ```

3. **High Failure Rate Scenario**:
   ```bash
   # Generate multiple failures quickly
   # (e.g., stop MongoDB, create 20 products)
   
   # Check logs for CRITICAL alert
   tail -f var/log/prod.log | grep "HIGH FAILURE RATE"
   
   # Should see alert after >10% failure rate
   ```

---

## Metrics & Observability

### Key Metrics to Monitor

1. **Embedding Sync Success Rate**:
   - Target: **> 90%** in 5-minute windows
   - Alert threshold: **< 90%** triggers critical alert

2. **Circuit Breaker Opens**:
   - Target: **< 5 per hour** in production
   - Investigate if frequent opens (indicates service instability)

3. **Dead Letter Queue Size**:
   - Target: **< 100 failed jobs** at any time
   - Alert if exceeds 500 (indicates persistent failures)

4. **Retry Success Rate**:
   - Target: **> 80%** of retries succeed
   - Alert if < 50% (indicates systemic issue)

5. **Semantic Search Fallback Rate**:
   - Target: **< 5%** of searches fallback to keyword
   - Alert if > 10% (indicates MongoDB/OpenAI issues)

### Logging Channels

- **Error Logs** (`var/log/prod.log`):
  - Circuit breaker state transitions
  - Failed embedding syncs
  - High failure rate alerts (CRITICAL level)

- **Metrics Logs** (Redis cache):
  - Failure rate counters (5min sliding window)
  - Circuit breaker state
  - Search metrics (via `SearchMetricsCollector`)

- **Database Logs** (`failed_embedding_jobs`):
  - Failed job records
  - Retry history
  - Resolution timeline

---

## Migration Path from Development to Production

### Development Environment
- **Expected failures**: MongoDB/OpenAI not always available
- **Manual retries**: Run retry command when needed
- **Permissive thresholds**: Higher failure rates acceptable

### Staging Environment
- **Service reliability**: MongoDB/OpenAI should be stable
- **Automated retries**: Cron job every 15 minutes
- **Alert testing**: Verify critical logs generated

### Production Environment
- **High availability**: MongoDB/OpenAI must be production-grade
- **Monitoring integration**: Alerts sent to Sentry/Datadog/Slack
- **Automated recovery**: Cron job + circuit breakers handle transient failures
- **Strict thresholds**: >10% failure rate triggers immediate investigation

---

## Appendix: Configuration Reference

### Circuit Breaker Constants

```php
// OpenAIEmbeddingService
const CIRCUIT_BREAKER_THRESHOLD = 5;
const CIRCUIT_BREAKER_TIMEOUT = 60;
const REQUEST_TIMEOUT = 10;
const MAX_RETRIES = 3;
const RETRY_DELAY_MS = 1000;

// MongoDBEmbeddingRepository
const CIRCUIT_BREAKER_THRESHOLD = 5;
const CIRCUIT_BREAKER_TIMEOUT = 60;
const CIRCUIT_BREAKER_CACHE_KEY = 'mongodb_circuit_breaker';
const QUERY_TIMEOUT_MS = 3000;
```

### Failure Rate Monitor Constants

```php
// FailureRateMonitor
const WINDOW_SECONDS = 300;                    // 5 minutes
const THRESHOLD_PERCENT = 10.0;                // 10%
const ALERT_COOLDOWN_SECONDS = 900;            // 15 minutes
const CACHE_KEY_SUCCESS = 'embedding_sync_success_count';
const CACHE_KEY_FAILURE = 'embedding_sync_failure_count';
const CACHE_KEY_LAST_ALERT = 'embedding_sync_last_alert';
```

### Failed Job Registry Constants

```php
// FailedJobRegistry
const MAX_ATTEMPTS = 5;
const RETRY_DELAYS = [
    1 => 60,      // 1 minute
    2 => 300,     // 5 minutes
    3 => 1800,    // 30 minutes
    4 => 7200,    // 2 hours
    5 => 86400,   // 24 hours
];
```

### Validation Constants

```php
// ProductEmbeddingSyncService
const MAX_RAW_DESCRIPTION_LENGTH = 32000;      // ~8000 tokens
const MAX_DESCRIPTION_LENGTH = 8000;            // After optimization
const MAX_TOKENS_PER_REQUEST = 8191;            // OpenAI limit

// OpenAIEmbeddingService
const EXPECTED_EMBEDDING_DIMENSIONS = 1536;     // text-embedding-3-small
```

---

## Related Documentation

- **PERFORMANCE.md**: Performance optimization and monitoring
- **README.md**: Project setup and architecture
- **spec.md**: Feature requirements and acceptance criteria
- **tasks.md**: Implementation task breakdown

---

## Changelog

| Date | Version | Changes |
|------|---------|---------|
| 2026-02-07 | 1.0 | Initial error handling strategy documentation (T104) |

---

**Status**: ✅ Production-Ready  
**Maintainer**: Development Team  
**Last Review**: 2026-02-07
