# CI/CD Deployment Troubleshooting Guide

## Common Issues and Solutions

### Table of Contents

1. [Docker Container Issues](#docker-container-issues)
2. [Jenkins Pipeline Failures](#jenkins-pipeline-failures)
3. [Ansible Deployment Errors](#ansible-deployment-errors)
4. [Database Migrations](#database-migrations)
5. [Health Check Failures](#health-check-failures)
6. [E2E Test Failures](#e2e-test-failures)
7. [Rollback Problems](#rollback-problems)
8. [Performance Issues](#performance-issues)

---

## Docker Container Issues

### Container Won't Start

**Symptoms**: `docker-compose up` fails, container status is "Exited"

**Diagnosis**:
```bash
# Check container logs
docker logs myshop-test --tail 50

# Check container status
docker ps -a | grep myshop
```

**Solutions**:

1. **Port already in use**:
   ```bash
   # Find process using port 8081
   lsof -i :8081
   # Kill process
   kill -9 <PID>
   ```

2. **Missing environment variables**:
   - Check `.env` file exists
   - Verify all required variables are set
   - Review `docker-compose.ci.yml` environment section

3. **Volume mount issues**:
   ```bash
   # Remove and recreate volumes
   docker-compose -f docker-compose.ci.yml down -v
   docker-compose -f docker-compose.ci.yml up -d
   ```

### Container Runs But Unhealthy

**Symptoms**: Container status shows "unhealthy"

**Diagnosis**:
```bash
# Check health check status
docker inspect myshop-test | grep -A 10 Health

# Test health endpoint manually
curl http://localhost:8081/health
```

**Solutions**:

1. **Database not ready**:
   ```bash
   # Check MySQL is accessible
   docker exec myshop-test php bin/console doctrine:query:sql "SELECT 1"
   ```

2. **PHP-FPM not running**:
   ```bash
   docker exec myshop-test service php8.3-fpm status
   docker exec myshop-test service php8.3-fpm restart
   ```

---

## Jenkins Pipeline Failures

### Build Timeout

**Symptoms**: Pipeline exceeds timeout and aborts

**Solutions**:

1. **Increase timeouts** in Jenkinsfile:
   ```groovy
   options {
       timeout(time: 60, unit: 'MINUTES')  // Increase from 30
   }
   ```

2. **Optimize slow stages**:
   - Cache Composer dependencies
   - Run tests in parallel
   - Use faster test database

### Workspace Disk Full

**Symptoms**: "No space left on device" error

**Diagnosis**:
```bash
# Check Docker disk usage
docker system df

# Check workspace size
du -sh /var/jenkins_home/workspace/*
```

**Solutions**:

1. **Clean old workspaces**:
   ```bash
   # In Jenkins
   cd /var/jenkins_home/workspace
   rm -rf old-build-*
   ```

2. **Configure build retention**:
   ```groovy
   options {
       buildDiscarder(logRotator(numToKeepStr: '10'))
   }
   ```

3. **Clean Docker**:
   ```bash
   docker system prune -a --volumes -f
   ```

### Credentials Not Found

**Symptoms**: "Credentials 'ansible-vault-password' not found"

**Solutions**:

1. **Add credential in Jenkins**:
   - Go to: Manage Jenkins → Credentials
   - Add credential with exact ID

2. **Verify ID matches**:
   - Check Jenkinsfile uses same ID
   - Case-sensitive match required

---

## Ansible Deployment Errors

### Connection Timeout

**Symptoms**: Ansible hangs or times out connecting

**Diagnosis**:
```bash
# Test Ansible connection
ansible all -i deployment/inventories/local-test/hosts -m ping

# Check container is running
docker ps | grep myshop-test
```

**Solutions**:

1. **Container not running**:
   ```bash
   docker-compose -f docker-compose.ci.yml up -d myshop-test
   ```

2. **Inventory misconfiguration**:
   - Verify `ansible_connection=local` in inventory
   - Check `container_name` variable matches

### Vault Decryption Failed

**Symptoms**: "Decryption failed" or "incorrect password"

**Solutions**:

1. **Verify vault password**:
   ```bash
   ansible-vault view deployment/inventories/local-test/group_vars/all/vault.yml
   ```

2. **Re-encrypt vault file**:
   ```bash
   ansible-vault rekey deployment/inventories/local-test/group_vars/all/vault.yml
   ```

### Composer Install Fails

**Symptoms**: Composer dependency resolution fails

**Solutions**:

1. **Clear Composer cache**:
   ```bash
   docker exec myshop-test rm -rf /root/.composer/cache
   ```

2. **Update composer.lock**:
   ```bash
   composer update --lock
   git add composer.lock
   git commit -m "Update composer.lock"
   ```

---

## Database Migrations

### Migration Fails

**Symptoms**: `doctrine:migrations:migrate` returns error

**Diagnosis**:
```bash
# Check migration status
docker exec myshop-test php bin/console doctrine:migrations:status

# Check database connectivity
docker exec myshop-test php bin/console doctrine:query:sql "SELECT 1"
```

**Solutions**:

1. **Database connection error**:
   - Verify `DATABASE_URL` in `.env`
   - Check MySQL container is running
   - Test connectivity: `docker exec myshop-mysql-test mysqladmin ping`

2. **Migration SQL error**:
   - Review migration file for syntax errors
   - Test migration on copy of database
   - Fix SQL and create new migration

3. **Already executed migration**:
   ```bash
   # Mark migration as executed
   docker exec myshop-test php bin/console doctrine:migrations:version XXXXXX --add
   ```

### Data Loss on Rollback

**⚠️ CRITICAL ISSUE**

**Prevention**:
- Always backup database before migrations
- Make migrations reversible (down() method)
- Test rollback on staging first

**Recovery**:
```bash
# Restore from backup
docker exec myshop-mysql-prod mysql myshop_prod < backup.sql
```

---

## Health Check Failures

### Database Health Check Fails

**Symptoms**: `/api/health/database` returns 503

**Diagnosis**:
```bash
# Test database connection
docker exec myshop-test php bin/console doctrine:query:sql "SELECT 1"

# Check MySQL logs
docker logs myshop-mysql-test --tail 50
```

**Solutions**:

1. **MySQL not started**:
   ```bash
   docker-compose -f docker-compose.ci.yml restart myshop-mysql-test
   ```

2. **Wrong credentials**:
   - Verify `DATABASE_URL` matches MySQL password
   - Check `docker-compose.ci.yml` for MySQL `MYSQL_ROOT_PASSWORD`

### Redis Health Check Fails

**Symptoms**: `/api/health/redis` returns 503

**Solutions**:

1. **Redis not running**:
   ```bash
   docker-compose -f docker-compose.ci.yml restart redis-test
   ```

2. **Connection configuration**:
   - Check `REDIS_URL` in environment variables
   - Verify Redis container name in Docker network

### MongoDB Health Check Fails

**Symptoms**: `/api/health/mongodb` returns 503

**Solutions**:

1. **MongoDB not started**:
   ```bash
   docker-compose -f docker-compose.ci.yml restart mongodb-test
   ```

2. **Connection string error**:
   - Verify `MONGODB_URL` format: `mongodb://mongodb-test:27017`

---

## E2E Test Failures

### Playwright Installation Fails

**Symptoms**: `npx playwright install` errors

**Solutions**:

1. **Missing dependencies**:
   ```bash
   npm run playwright install --with-deps
   ```

2. **Node version mismatch**:
   - Ensure Node.js 20+ is installed
   - Check `tests/E2E/package.json` for version requirements

### Tests Timeout

**Symptoms**: Tests fail with "Timeout 30000ms exceeded"

**Solutions**:

1. **Increase timeouts** in `playwright.config.ts`:
   ```typescript
   use: {
       actionTimeout: 15000,  // Increase from 10000
       navigationTimeout: 45000,  // Increase from 30000
   }
   ```

2. **Application slow to respond**:
   - Check container resources
   - Review application logs for errors
   - Optimize slow endpoints

### Flaky Tests

**Symptoms**: Tests pass/fail inconsistently

**Solutions**:

1. **Add explicit waits**:
   ```typescript
   await page.waitForSelector('[data-test="cart-item"]');
   await page.waitForLoadState('networkidle');
   ```

2. **Use retries**:
   ```typescript
   retries: process.env.CI ? 2 : 0
   ```

---

## Rollback Problems

### Rollback Fails - Release Not Found

**Symptoms**: "Release XXXXXX does not exist"

**Diagnosis**:
```bash
# List available releases
docker exec myshop-prod ls -lt /var/www/myshop/releases
```

**Solutions**:

1. **Use correct release timestamp**:
   - Copy exact timestamp from `ls` output
   - Use `previous` for most recent

2. **Manually create symlink**:
   ```bash
   docker exec myshop-prod ln -sfn /var/www/myshop/releases/20260213120000 /var/www/myshop/current
   docker exec myshop-prod service php8.3-fpm reload
   ```

### Rollback Complete But App Broken

**Symptoms**: Rollback succeeds but application returns 500 errors

**Solutions**:

1. **Clear all caches**:
   ```bash
   docker exec myshop-prod php /var/www/myshop/current/bin/console cache:clear
   docker exec myshop-prod php /var/www/myshop/current/bin/console cache:pool:clear cache.global_clearer
   ```

2. **Check release integrity**:
   ```bash
   # Verify vendor/ directory exists
   docker exec myshop-prod ls -la /var/www/myshop/current/vendor

   # Check file permissions
   docker exec myshop-prod ls -la /var/www/myshop/current
   ```

---

## Performance Issues

### Deployment Takes Too Long

**Symptoms**: Deployment exceeds 15 minutes

**Solutions**:

1. **Optimize Composer**:
   ```bash
   # Use cached vendor archive
   tar -xzf artifacts/vendor-latest.tar.gz
   ```

2. **Parallel stages**:
   - Run tests in parallel
   - Build and test simultaneously when possible

3. **Skip unnecessary steps**:
   - Don't run E2E tests for non-master branches
   - Skip smoke tests for test environment

### Jenkins Slow to Start

**Symptoms**: Jenkins takes >5 minutes to become available

**Solutions**:

1. **Allocate more resources**:
   ```yaml
   # In docker-compose.ci.yml
   jenkins:
       deploy:
           resources:
               limits:
                   cpus: '2.0'
                   memory: 4G
   ```

2. **Disable unnecessary plugins**:
   - Review installed plugins
   - Disable unused plugins

---

## Getting Help

If issues persist:

1. **Check logs**:
   - Jenkins: Build console output
   - Docker: `docker logs <container>`
   - Application: `var/log/*.log`

2. **Ask the team**:
   - Slack: #devops-team
   - Create ticket with:
     - Error message
     - Steps to reproduce
     - Logs

3. **Escalate**:
   - On-call engineer (PagerDuty)
   - Emergency: CTO

## Preventive Measures

To avoid issues:

- ✓ Monitor disk space regularly
- ✓ Keep Jenkins plugins updated
- ✓ Test deployments in test environment first
- ✓ Review logs after each deployment
- ✓ Document new issues and solutions
- ✓ Practice rollback procedures quarterly
