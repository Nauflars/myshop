# Deployment Checklist - spec-014 User Recommendations

This checklist ensures proper deployment of the user embeddings queue system (spec-014) to production.

## Pre-Deployment

### 1. Environment Variables (CRITICAL)

Verify all required environment variables are set in `.env` (use `.env.example` as template):

```bash
# RabbitMQ Configuration
RABBITMQ_DSN=amqp://username:password@host:port/%2f

# MongoDB Configuration  
MONGODB_DB=myshop
MONGODB_URL=mongodb://username:password@host:port

# User Embeddings Configuration
EMBEDDING_DECAY_LAMBDA=0.023        # 30-day half-life (recommended)
EMBEDDING_BATCH_ENABLED=false       # Batching disabled by default
EMBEDDING_BATCH_WINDOW=5            # 5 seconds if batching enabled

# Worker Configuration
WORKER_MAX_RETRIES=5                # Max retries before DLQ
WORKER_RETRY_DELAY=5000             # Initial delay (ms), exponential backoff

# OpenAI Configuration
OPENAI_API_KEY=sk-...              # Required for embeddings
OPENAI_EMBEDDING_MODEL=text-embedding-3-small
```

**Validation:**
```bash
# Check all variables are set
php bin/console debug:container --env=prod --parameter=env | grep -E 'RABBITMQ|MONGODB|EMBEDDING|WORKER|OPENAI'
```

### 2. Dependencies

Verify composer dependencies are installed:

```bash
composer install --no-dev --optimize-autoload
```

**Required packages:**
- `symfony/messenger` >= 7.4
- `symfony/amqp-messenger` >= 7.4  
- `mongodb/mongodb` >= 1.15
- `predis/predis` >= 2.0 (for idempotency cache)

### 3. Database Indexes

Create MongoDB indexes for optimal performance:

```bash
php bin/console app:setup-mongodb-embeddings --create-indexes
```

**Indexes created:**
- `user_embeddings.user_id` (unique)
- `user_embeddings.last_updated` (for temporal decay)
- `user_embeddings.version` (for optimistic locking)

### 4. RabbitMQ Queue Setup

Verify queue exists and is properly configured:

```bash
# Check queue stats
php bin/console messenger:stats user_embedding_updates

# Expected output: Queue exists with 0 or N messages
```

**Queue configuration:**
- **Name:** `user_embedding_updates`
- **Durable:** Yes
- **Auto-delete:** No
- **Dead Letter Exchange:** `failed` (for T046 fault tolerance)
- **Message TTL:** None (messages persist until processed)

### 5. Testing

Run test suite to verify code integrity:

```bash
# Run all non-integration tests
vendor/bin/phpunit --exclude-group=integration

# Expected: 173+ tests passing, 0 failures
```

Run contract tests to verify message format:

```bash
vendor/bin/phpunit --testsuite=Contract

# Expected: 10 tests passing (EventMessageFormatTest)
```

### 6. Load Testing

Execute load test to verify system capacity:

```bash
php bin/console app:generate-test-events --users=100 --events-per-user=50

# Expected output:
# - 5000 events generated
# - Throughput > 1000 events/sec
# - 0 failures
```

Monitor RabbitMQ queue depth:

```bash
# Queue should handle load without exceeding 5000 messages
watch -n 1 'php bin/console messenger:stats user_embedding_updates'
```

---

## Deployment

### 1. Application Deploy

```bash
# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoload

# Clear and warm cache
php bin/console cache:clear --env=prod
php bin/console cache:warmup --env=prod

# Run database migrations (if any)
php bin/console doctrine:migrations:migrate --no-interaction
```

### 2. Start Worker Processes

Workers consume messages from RabbitMQ and update user embeddings:

**Option A: Docker Compose (recommended)**

```yaml
# docker-compose.yml
services:
  worker:
    build: .
    command: php bin/console messenger:consume user_embedding_updates --time-limit=3600 --memory-limit=512M
    restart: always
    deploy:
      replicas: 3  # 3 workers for redundancy
    environment:
      - APP_ENV=prod
      - RABBITMQ_DSN=amqp://...
      - MONGODB_DB=myshop
```

```bash
docker-compose up -d worker
docker-compose ps worker  # Verify 3 workers running
```

**Option B: Systemd Service**

Create `/etc/systemd/system/myshop-worker@.service`:

```ini
[Unit]
Description=MyShop Queue Worker %i
After=network.target rabbitmq-server.service mongodb.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/myshop
ExecStart=/usr/bin/php /var/www/myshop/bin/console messenger:consume user_embedding_updates --time-limit=3600 --memory-limit=512M
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

Start 3 worker instances:

```bash
systemctl enable myshop-worker@{1..3}.service
systemctl start myshop-worker@{1..3}.service
systemctl status myshop-worker@*
```

**Option C: Supervisor**

Create `/etc/supervisor/conf.d/myshop-worker.conf`:

```ini
[program:myshop-worker]
command=php /var/www/myshop/bin/console messenger:consume user_embedding_updates --time-limit=3600 --memory-limit=512M
process_name=%(program_name)s_%(process_num)02d
numprocs=3
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/log/myshop/worker.log
stderr_logfile=/var/log/myshop/worker.error.log
```

```bash
supervisorctl reread
supervisorctl update
supervisorctl start myshop-worker:*
```

### 3. Verify Workers

Check workers are consuming messages:

```bash
# Option 1: Check logs
docker-compose logs -f worker
# or
tail -f /var/log/myshop/worker.log

# Option 2: Monitor queue depth (should decrease)
php bin/console messenger:stats user_embedding_updates

# Option 3: Check MongoDB for new embeddings
docker-compose exec mongodb mongosh myshop --eval "db.user_embeddings.countDocuments()"
```

### 4. Health Checks

Set up monitoring for:

1. **Worker Health** (T072 monitoring requirements):
   ```bash
   # Workers should restart if crash
   # Max 5 restarts per hour before alerting
   ```

2. **Queue Depth** (T082 SLA):
   ```bash
   # Alert if queue depth > 5000 messages
   # This indicates workers are overwhelmed
   ```

3. **Processing Latency** (T081 SLA):
   ```bash
   # 95% of embeddings updated within 30 seconds
   # Check MongoDB last_updated timestamps
   ```

4. **Error Rate**:
   ```bash
   # Monitor failed queue (DLQ)
   php bin/console messenger:stats failed
   # Should be < 1% of total messages
   ```

---

## Post-Deployment Validation

### 1. Smoke Tests

```bash
# Test 1: Publish a single message
php bin/console app:generate-test-events --users=1 --events-per-user=1

# Test 2: Wait 10 seconds and verify embedding exists
docker-compose exec mongodb mongosh myshop --eval "db.user_embeddings.findOne({user_id: 500000})"

# Expected: Document with 1536-dimensional vector, version=1
```

### 2. Performance Validation

```bash
# Generate 1000 events
php bin/console app:generate-test-events --users=20 --events-per-user=50

# Monitor processing time
time php bin/console messenger:stats user_embedding_updates
# Queue should drain in < 60 seconds with 3 workers
```

### 3. Idempotency Test

```bash
# Publish duplicate message (same message_id)
# Should be skipped by handler without error
# Check logs for: "Message already processed (idempotency check), skipping"
```

---

## Rollback Procedure

If critical issues arise:

### 1. Stop Workers

```bash
docker-compose stop worker
# or
systemctl stop myshop-worker@*
# or
supervisorctl stop myshop-worker:*
```

### 2. Drain Queue

```bash
# Purge messages (CAUTION: DATA LOSS)
php bin/console messenger:stop-workers
# Wait for workers to stop gracefully

# If needed, purge queue via RabbitMQ management:
# rabbitmqctl purge_queue user_embedding_updates
```

### 3. Rollback Code

```bash
git revert <commit-sha>
composer install --no-dev --optimize-autoload
php bin/console cache:clear --env=prod
```

### 4. Database Rollback

```bash
# Drop user_embeddings collection if needed
docker-compose exec mongodb mongosh myshop --eval "db.user_embeddings.drop()"
```

---

## Scaling Guidelines

### When to Scale Up Workers

Monitor these metrics:

1. **Queue Depth**: Consistently > 1000 messages
2. **Processing Latency**: P95 > 30 seconds  
3. **Worker CPU**: > 80% utilization across all workers

**Add workers:**

```bash
# Docker Compose
docker-compose up -d --scale worker=5

# Systemd
systemctl start myshop-worker@{4..5}.service

# Supervisor
# Edit numprocs=5 in config, then:
supervisorctl update
```

### When to Scale Down Workers

1. **Queue Depth**: Consistently < 100 messages
2. **Worker CPU**: < 20% utilization  
3. **Cost Optimization**: During off-peak hours

**Remove workers:**

```bash
docker-compose up -d --scale worker=1
# or stop specific instances
```

---

## Monitoring Setup

### Prometheus Metrics

Add to `config/packages/prod/messenger.yaml`:

```yaml
framework:
    messenger:
        failure_transport: failed
        transports:
            user_embedding_updates:
                dsn: '%env(RABBITMQ_DSN)%'
                options:
                    queues:
                        user_embedding_updates:
                            durable: true
                    exchange:
                        name: user_embedding_updates
                        type: direct
        routing:
            App\Application\Message\UpdateUserEmbeddingMessage: user_embedding_updates
```

**Metrics to track:**

- `messenger_queue_depth{queue="user_embedding_updates"}`
- `messenger_processed_messages_total{queue="user_embedding_updates",status="success"}`
- `messenger_processed_messages_total{queue="user_embedding_updates",status="failed"}`
- `messenger_processing_time_seconds{queue="user_embedding_updates",percentile="0.95"}`

### Grafana Dashboard

Import dashboard template: [Link to dashboard JSON]

**Panels:**
1. Queue Depth Over Time
2. Throughput (events/sec)
3. P95 Processing Latency
4. Error Rate
5. Worker Health Status

---

## Troubleshooting

See [TROUBLESHOOTING.md](TROUBLESHOOTING.md) for common issues and solutions.

**Quick diagnostics:**

```bash
# Check RabbitMQ connection
php bin/console messenger:setup-transports

# Check MongoDB connection
docker-compose exec mongodb mongosh myshop --eval "db.stats()"

# Check worker status
docker-compose ps worker  # or systemctl status / supervisorctl status

# View recent errors
php bin/console messenger:failed:show

# Retry failed messages
php bin/console messenger:failed:retry
```

---

## Security Checklist

- [ ] No API keys in code (all in environment variables)
- [ ] RabbitMQ credentials use strong passwords (not guest/guest in prod)
- [ ] MongoDB credentials use strong passwords
- [ ] MongoDB not exposed to public internet (firewall rules)
- [ ] RabbitMQ management UI not exposed publicly (or behind authentication)
- [ ] Workers run as non-root user (www-data)
- [ ] File permissions: `chown -R www-data:www-data /var/www/myshop`
- [ ] Logs do not contain sensitive data (check worker logs)
- [ ] HTTPS enabled for web interface
- [ ] Rate limiting on user-facing endpoints (T086 security review)

---

## Completion Checklist

- [ ] All environment variables configured
- [ ] MongoDB indexes created
- [ ] RabbitMQ queue configured
- [ ] Test suite passes (173+ tests)
- [ ] Contract tests pass (10 tests)
- [ ] Load test successful (5000 events)
- [ ] 3 workers running and consuming messages
- [ ] Health checks configured
- [ ] Monitoring dashboard deployed
- [ ] Smoke tests pass
- [ ] Performance SLAs met (queue depth < 5000, P95 <30s)
- [ ] Documentation reviewed
- [ ] Security checklist complete
- [ ] Team trained on troubleshooting procedures
- [ ] Rollback procedure tested

**Deployment approved by:** ________________  
**Date:** ________________
