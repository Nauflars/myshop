# Migration Guide: spec-009 → spec-012

**Goal**: Safe transition from legacy context storage to unified conversation architecture.

**Audience**: DevOps, Backend Developers

**Estimated Time**: 2-4 hours (includes testing & rollback prep)

---

## Pre-Migration Checklist

- [ ] Redis backup created (`BGSAVE`)
- [ ] MySQL backup created
- [ ] Feature flag configured (`UNIFIED_CONVERSATION_ENABLED`)
- [ ] Monitoring dashboard ready (Redis memory, response times)
- [ ] Rollback plan tested

---

## Migration Strategy

### Option 1: Hard Cutover (Recommended)

**When**: Low traffic hours (e.g., 2 AM - 4 AM)

**Steps**:

1. **Deploy code** with spec-012 implementation
2. **Clear legacy Redis keys** (optional, they'll expire naturally)
3. **Monitor** for 24 hours
4. **Remove legacy code** after validation

**Advantage**: Simple, clean break  
**Risk**: If bugs found, requires rollback

### Option 2: Gradual Rollout with Feature Flag

**When**: High-traffic systems requiring zero downtime

**Steps**:

1. **Deploy code** with feature flag OFF
2. **Enable for 10% of users**
3. **Monitor metrics** (errors, latency, Redis memory)
4. **Gradually increase** to 50%, 100%
5. **Remove flag** after validation

**Advantage**: Safe, incremental  
**Risk**: More complex deployment

---

## Phase 1: Pre-Deployment (Day 0)

### 1.1. Backup Current State

```bash
# Redis backup
redis-cli BGSAVE

# MySQL backup
mysqldump -u root -p myshop > backup_$(date +%Y%m%d).sql
```

### 1.2. Configure Environment

Add to `.env`:

```bash
# spec-012 Configuration
UNIFIED_CONVERSATION_ENABLED=true

# TTL settings (existing)
CUSTOMER_CONTEXT_TTL=1800
ADMIN_CONTEXT_TTL=1800
CONTEXT_TTL=1800

# Redis connection (existing)
REDIS_URL=redis://localhost:6379
```

### 1.3. Test in Staging

```bash
# Run tests
vendor/bin/phpunit tests/Unit/Infrastructure/Repository/UnifiedConversationStorageTest.php
vendor/bin/phpunit tests/Integration/Application/Service/UnifiedCustomerContextManagerTest.php

# Manual smoke test
# 1. Open chatbot
# 2. Send 3 messages
# 3. Verify Redis keys exist:
redis-cli KEYS "conversation:*"
```

---

## Phase 2: Deployment (Day 1)

### 2.1. Deploy New Code

```bash
# Pull latest code
git pull origin 012-unified-conversation-memory

# Install dependencies
composer install --no-dev --optimize-autoloader

# Clear Symfony cache
php bin/console cache:clear --env=prod

# Warm up cache
php bin/console cache:warmup --env=prod
```

### 2.2. Verify Services Registered

```bash
# Check container compilation
php bin/console debug:container UnifiedConversationStorage
php bin/console debug:container UnifiedCustomerContextManager
php bin/console debug:container UnifiedAdminContextManager

# Should see:
# App\Infrastructure\Repository\UnifiedConversationStorage
# App\Application\Service\UnifiedCustomerContextManager
# App\Application\Service\UnifiedAdminContextManager
```

### 2.3. Initial Smoke Test

```bash
# Test customer chatbot
curl -X POST http://localhost:8000/api/chat \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <token>" \
  -d '{"message": "Hello"}'

# Check Redis
redis-cli KEYS "conversation:client:*"
# Should see: history, state, meta keys

# Test admin assistant
curl -X POST http://localhost:8000/admin/assistant/chat \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer <admin-token>" \
  -d '{"message": "Show me sales stats"}'

# Check Redis
redis-cli KEYS "conversation:admin:*"
```

---

## Phase 3: Monitoring (Day 1-7)

### 3.1. Key Metrics to Monitor

| Metric | Expected | Alert If |
|--------|----------|----------|
| Redis memory usage | +10-20% | > +50% |
| API response time | Same as before | > +100ms |
| Error rate | < 0.1% | > 1% |
| Conversation creation rate | Same | Drops > 20% |

### 3.2. Redis Monitoring Commands

```bash
# Check memory usage
redis-cli INFO memory | grep used_memory_human

# Check key count
redis-cli DBSIZE

# Check conversation keys
redis-cli KEYS "conversation:*" | wc -l

# Sample key inspection
redis-cli GET "conversation:client:123:abc-uuid:history"

# Check TTL distribution
redis-cli --scan --pattern "conversation:*:meta" | xargs redis-cli TTL
```

### 3.3. Application Logs

Monitor for:

```bash
# Errors
tail -f var/log/prod.log | grep ERROR

# Redis failures
tail -f var/log/prod.log | grep "Error loading unified"

# Context creation
tail -f var/log/prod.log | grep "New.*conversation created"
```

---

## Phase 4: Data Migration (Optional)

If you want to migrate existing contexts to new format:

### 4.1. Create Migration Command

(Already exists: `src/Command/MigrateContextToUnifiedCommand.php`)

```bash
php bin/console app:migrate:context-to-unified --dry-run
# Review proposed changes

php bin/console app:migrate:context-to-unified
# Execute migration
```

### 4.2. Migration Logic

1. Find all legacy Redis keys: `chat:customer:*`, `admin:context:*`
2. For each key:
   - Generate UUID for conversation
   - Extract state data
   - Load last 10 messages from MySQL
   - Create new Redis keys: `conversation:{role}:{userId}:{uuid}:*`
3. Keep legacy keys for 7 days (rollback safety)

---

## Phase 5: Validation (Day 7)

### 5.1. Functional Tests

- [ ] Customer can start new conversation
- [ ] Customer can continue existing conversation
- [ ] History persists across page reloads
- [ ] Admin assistant works correctly
- [ ] Multi-step operations work (e.g., product creation)
- [ ] TTL refreshes on interaction

### 5.2. Performance Tests

- [ ] API response time < 200ms (p95)
- [ ] Redis memory usage stable
- [ ] No error rate increase
- [ ] MySQL query count unchanged

### 5.3. Edge Cases

- [ ] Redis connection failure → graceful degradation
- [ ] TTL expiration → new conversation created
- [ ] > 10 messages → oldest messages removed
- [ ] Concurrent requests → no race conditions

---

## Phase 6: Cleanup (Day 14)

### 6.1. Remove Legacy Code

Once validation complete:

```bash
# Remove old context managers (optional, mark deprecated instead)
# src/Application/Service/CustomerContextManager.php (keep for reference)
# src/Application/Service/AdminContextManager.php (keep for reference)

# Remove legacy storage
rm src/Infrastructure/Repository/RedisContextStorage.php

# Update services.yaml
# Comment out or remove legacy service registrations
```

### 6.2. Clear Legacy Redis Keys

```bash
# Find legacy keys
redis-cli KEYS "chat:customer:*"
redis-cli KEYS "admin:context:*"

# Delete if no longer needed
redis-cli DEL $(redis-cli KEYS "chat:customer:*")
redis-cli DEL $(redis-cli KEYS "admin:context:*")
```

---

## Rollback Plan

If critical issues arise:

### Immediate Rollback (< 1 hour)

```bash
# 1. Revert to previous commit
git revert HEAD
git push origin 012-unified-conversation-memory

# 2. Redeploy
composer install --no-dev
php bin/console cache:clear --env=prod

# 3. Verify legacy code active
php bin/console debug:container CustomerContextManager
# Should exist

# 4. Clear new Redis keys (optional)
redis-cli DEL $(redis-cli KEYS "conversation:*")
```

### Delayed Rollback (Day 1-7)

If issues discovered after initial deployment:

```bash
# 1. Disable new managers in services.yaml
# Replace with legacy managers

# 2. Deploy config change
php bin/console cache:clear --env=prod

# 3. Monitor for stabilization

# 4. Investigate root cause before re-attempting
```

---

## Common Issues & Solutions

### Issue 1: Redis Memory Spike

**Symptoms**: Redis memory > +50% after deployment

**Cause**: Every conversation now stores history (10 messages)

**Solutions**:
1. **Reduce TTL**: Change from 1800s (30min) to 900s (15min)
2. **Increase Redis memory**: Update `maxmemory` in redis.conf
3. **Enable eviction**: Set `maxmemory-policy allkeys-lru`

### Issue 2: Conversations Not Persisting

**Symptoms**: Users report lost context after refresh

**Cause**: TTL expiring before user returns

**Solutions**:
1. **Increase TTL**: Change to 3600s (1 hour) if needed
2. **Auto-refresh on page load**: Add `refreshTtl()` call in frontend

### Issue 3: History Not Loading

**Symptoms**: AI responses don't reference previous messages

**Cause**: MessageBag construction issue

**Debug**:
```php
// In controller, before AI call:
dump($messages);
// Verify history is included
```

**Solution**: Check `buildMessageBagContext()` returns correct format

### Issue 4: Performance Degradation

**Symptoms**: API response time > +100ms

**Cause**: Extra Redis roundtrips

**Solutions**:
1. **Pipeline Redis calls**: Batch `GET` operations
2. **Cache in memory**: Store conversation in request lifecycle
3. **Profile slow queries**: Use Symfony Profiler

---

## Success Criteria

✅ **Migration considered successful when**:

- [ ] No increase in error rate
- [ ] API response times stable (< +50ms)
- [ ] Redis memory usage predictable
- [ ] All features working (chatbot, admin assistant)
- [ ] 7 days of uptime without rollback
- [ ] Team comfortable with new architecture

---

## Post-Migration Optimization

After 30 days of stable operation:

### Optimize Redis Memory

```bash
# Reduce TTL for inactive conversations
# Currently: 30 minutes for all
# Optimized: 15 minutes for inactive, 60 minutes for active

# Implement in UnifiedConversationStorage:
# - Track activity frequency
# - Adjust TTL dynamically
```

### Add Monitoring

```yaml
# config/packages/monolog.yaml
monolog:
    channels: ['conversation']
    handlers:
        conversation:
            type: stream
            path: "%kernel.logs_dir%/conversation.log"
            channels: ['conversation']
            level: info
```

### Performance Tuning

```php
// Enable Redis pipeline for bulk operations
$pipeline = $redis->pipeline();
$pipeline->get('conversation:client:123:conv:history');
$pipeline->get('conversation:client:123:conv:state');
$pipeline->get('conversation:client:123:conv:meta');
$results = $pipeline->execute();
```

---

**Questions?** Contact: Development Team  
**Documentation**: See [developer-guide.md](./developer-guide.md)
