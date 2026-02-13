# Rollback Procedure Documentation

## Overview

This document provides step-by-step instructions for rolling back a deployment when issues are discovered in production.

## When to Rollback

Rollback immediately if:
- ✗ Critical bugs discovered in production
- ✗ Smoke tests fail after deployment
- ✗ Database migrations cause data integrity issues
- ✗ Performance degradation >50%
- ✗ Security vulnerabilities introduced
- ✗ Service outages or unavailability

## Rollback Methods

### Method 1: Via Jenkins (Recommended)

#### Step 1: Navigate to Jenkins
1. Open Jenkins: `http://localhost:8080`
2. Navigate to "myshop-rollback" job

#### Step 2: Start Rollback
1. Click "Build with Parameters"
2. Fill in parameters:
   - **Environment**: Select `test` or `production`
   - **Release Version**: Use `previous` or specific timestamp
   - **Rollback Reason**: **Required** - describe the issue

#### Step 3: Confirm Rollback
1. Review the rollback details
2. Confirm you understand the implications
3. Click "Proceed" to execute

#### Step 4: Monitor Progress
1. Watch the console output
2. Verify health checks pass
3. Check application functionality

### Method 2: Via Ansible (Emergency)

If Jenkins is unavailable, use Ansible directly:

```bash
# Test environment
ansible-playbook deployment/rollback-local.yml \
  -i deployment/inventories/local-test/hosts \
  -e "rollback_reason='Emergency rollback - Jenkins unavailable'" \
  --vault-password-file .vault_pass

# Production environment
ansible-playbook deployment/rollback-local.yml \
  -i deployment/inventories/local-production/hosts \
  -e "rollback_reason='Emergency rollback - Jenkins unavailable'" \
  --vault-password-file .vault_pass
```

### Method 3: Manual Rollback (Last Resort)

If both Jenkins and Ansible are unavailable:

```bash
# 1. Access the container
docker exec -it myshop-prod bash

# 2. Navigate to deployment directory
cd /var/www/myshop

# 3. List available releases
ls -lt releases/

# 4. Identify previous release (second most recent)
PREVIOUS_RELEASE=$(ls -t releases/ | sed -n '2p')

# 5. Update current symlink
ln -sfn "releases/$PREVIOUS_RELEASE" current

# 6. Reload PHP-FPM
service php8.3-fpm reload

# 7. Clear caches
php current/bin/console cache:clear --env=prod

# 8. Exit container
exit

# 9. Verify rollback
curl http://localhost:8082/health
```

## Post-Rollback Actions

### 1. Verify Application Health

```bash
# Run smoke tests
bash scripts/deploy/smoke-test.sh production

# Check all health endpoints
curl http://localhost:8082/health
curl http://localhost:8082/api/health/database
curl http://localhost:8082/api/health/redis
curl http://localhost:8082/api/health/mongodb
curl http://localhost:8082/api/health/rabbitmq
```

### 2. Monitor Application

- Check application logs: `docker logs myshop-prod --tail 100 -f`
- Monitor error rates
- Review user reports
- Check performance metrics

### 3. Investigate Root Cause

- Review deployment logs
- Check what changed between releases
- Reproduce the issue in test environment
- Document findings

### 4. Communicate

- Notify team via Slack
- Update incident ticket
- Send status update to stakeholders

## Rollback Scenarios

### Scenario 1: Bad Deployment Detected Immediately

**Timeline**: 0-15 minutes after deployment

**Action**:
1. Rollback via Jenkins immediately
2. No user impact if caught quickly
3. Fix issue and redeploy

### Scenario 2: Issue Discovered After Hours

**Timeline**: Hours after deployment

**Action**:
1. Assess severity
2. If critical: Rollback immediately
3. If minor: Consider hotfix instead
4. Document affected transactions

### Scenario 3: Database Migration Issue

**Timeline**: Any time

**⚠️ CAUTION**: Database rollbacks are complex!

**Action**:
1. **STOP** - Do not rollback automatically
2. Assess database state
3. Check if migration is reversible
4. Consider data loss implications
5. Consult with database admin
6. May require manual migration rollback:
   ```bash
   docker exec myshop-prod php /var/www/myshop/current/bin/console \
     doctrine:migrations:migrate prev --no-interaction
   ```

## Rollback Testing

### Test Rollback Procedure (Quarterly)

1. **Prepare Test Environment**
   ```bash
   # Deploy latest to test
   docker exec myshop-jenkins jenkins-cli build myshop-pipeline -p BRANCH=master
   ```

2. **Execute Rollback**
   ```bash
   # Rollback via Jenkins
   # Navigate to myshop-rollback job
   # Select environment: test
   # Release: previous
   # Reason: "Quarterly rollback drill"
   ```

3. **Verify Success**
   ```bash
   bash scripts/deploy/rollback-verify.sh test
   bash scripts/deploy/smoke-test.sh test
   ```

4. **Document Results**
   - Time to complete rollback
   - Any issues encountered
   - Improvements needed

## Rollback Metrics

Track these metrics for each rollback:

- **Rollback Frequency**: How often are rollbacks needed?
- **Time to Rollback**: How long does it take?
- **Success Rate**: What % of rollbacks succeed?
- **Time Between Deploy and Rollback**: How quickly are issues detected?

## Troubleshooting Rollback Issues

### Issue: Rollback fails with "release not found"

**Solution**:
```bash
# List available releases
docker exec myshop-prod ls -lt /var/www/myshop/releases

# Manually specify release timestamp
# Use the release timestamp from the list above
```

### Issue: Health checks fail after rollback

**Solution**:
```bash
# Check PHP-FPM status
docker exec myshop-prod service php8.3-fpm status

# Restart PHP-FPM if needed
docker exec myshop-prod service php8.3-fpm restart

# Clear all caches
docker exec myshop-prod php /var/www/myshop/current/bin/console cache:clear --env=prod
```

### Issue: Symlink is broken after rollback

**Solution**:
```bash
# Check current symlink
docker exec myshop-prod readlink -f /var/www/myshop/current

# Recreate symlink manually
docker exec myshop-prod ln -sfn /var/www/myshop/releases/TIMESTAMP /var/www/myshop/current
```

## Prevention Strategies

To reduce the need for rollbacks:

1. **Comprehensive Testing**
   - Run full test suite before deployment
   - Execute E2E tests on staging
   - Perform manual smoke tests

2. **Gradual Rollout**
   - Deploy to test first
   - Monitor for issues
   - Wait 24 hours before production

3. **Feature Flags**
   - Use feature flags for risky changes
   - Enable gradually for user segments
   - Easy rollback without deployment

4. **Database Migrations**
   - Test migrations on copy of production data
   - Make migrations reversible
   - Separate data migrations from schema changes

5. **Monitoring**
   - Real-time error tracking
   - Performance monitoring
   - User experience monitoring

## Contact Information

**For Rollback Assistance**:
- DevOps Team: #devops-team (Slack)
- On-Call Engineer: (see PagerDuty)
- Emergency: Escalate to CTO

## Audit Trail

All rollbacks are logged to:
- File: `var/log/rollbacks.log`
- Format: `YYYY-MM-DD HH:MM:SS UTC - Rollback [environment] to [version] - By: [user] - Reason: [reason]`
- Slack: #deployments channel
- Jenkins: Build history

## Review Process

After each rollback:
1. Conduct post-mortem meeting
2. Document root cause
3. Identify preventive measures
4. Update runbooks
5. Share learnings with team
