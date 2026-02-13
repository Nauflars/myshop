# DevOps Runbook: MyShop CI/CD Operations

## Quick Links

- [Daily Operations](#daily-operations)
- [Emergency Procedures](#emergency-procedures)
- [Deployment Procedures](#deployment-procedures)
- [Monitoring](#monitoring)
- [Maintenance](#maintenance)

---

## Daily Operations

### Morning Checklist

1. **Check overnight builds**:
   - Review Jenkins dashboard: http://localhost:8080
   - Investigate any failed builds
   - Check Slack #deployments channel

2. **Verify environments**:
   ```bash
   # Check all containers are healthy
   bash scripts/deploy/docker-health.sh
   
   # Test environments
   curl http://localhost:8081/health
   curl http://localhost:8082/health
   ```

3. **Review logs**:
   ```bash
   # Check for errors
   docker logs myshop-test --since 24h | grep ERROR
   docker logs myshop-prod --since 24h | grep ERROR
   ```

### Weekly Tasks

**Every Monday**:
1. Clean old Jenkins builds
2. Review deployment metrics
3. Check disk space usage
4. Update team on pipeline health

**Commands**:
```bash
# Clean Docker
docker system prune -f

# Check disk usage
df -h
docker system df
```

---

## Emergency Procedures

### Production Down

**Severity**: P1 - Critical

**Immediate Actions** (within 5 minutes):

1. **Verify the issue**:
   ```bash
   curl http://localhost:8082/health
   docker ps | grep myshop-prod
   ```

2. **Check container logs**:
   ```bash
   docker logs myshop-prod --tail 100
   ```

3. **Quick fixes**:
   ```bash
   # Restart container
   docker-compose -f docker-compose.ci.yml restart myshop-prod
   
   # Reload PHP-FPM
   docker exec myshop-prod service php8.3-fpm reload
   ```

4. **If not resolved, rollback**:
   - Navigate to Jenkins → myshop-rollback job
   - Select production, reason: "Production outage"
   - Execute immediately

5. **Notify**:
   - Slack: @channel in #incidents
   - Start incident log
   - Update status page

**Post-Incident**:
- Conduct post-mortem within 24 hours
- Document root cause
- Implement preventive measures

### Bad Deployment Detected

**Severity**: P2 - High

**Actions**:

1. **Assess impact**:
   - Check error rates
   - Review user reports
   - Monitor performance metrics

2. **Decide**: Fix forward or rollback?
   - **Rollback**: If issue is widespread or critical
   - **Fix forward**: If issue is minor and fast fix available

3. **Execute rollback**:
   ```bash
   # Via Jenkins
   # Navigate to: myshop-rollback job
   # Environment: production
   # Release: previous
   # Reason: "Bad deployment - [describe issue]"
   ```

4. **Verify**:
   ```bash
   bash scripts/deploy/rollback-verify.sh production
   bash scripts/deploy/smoke-test.sh production
   ```

### Jenkins Server Down

**Severity**: P3 - Medium

**Actions**:

1. **Check Jenkins container**:
   ```bash
   docker ps -a | grep jenkins
   docker logs myshop-jenkins --tail 50
   ```

2. **Restart Jenkins**:
   ```bash
   docker-compose -f docker-compose.ci.yml restart jenkins
   ```

3. **If container won't start**:
   ```bash
   # Check disk space
   df -h
   
   # Clean up
   docker system prune -f
   
   # Restart with fresh container
   docker-compose -f docker-compose.ci.yml up -d --force-recreate jenkins
   ```

4. **Manual deployment** (if Jenkins unavailable):
   ```bash
   ansible-playbook deployment/deploy-local.yml \
     -i deployment/inventories/local-production/hosts \
     --vault-password-file .vault_pass
   ```

### Database Issues

**Severity**: Varies

**Migration failure during deployment**:

1. **STOP deployment immediately**
2. **Check migration status**:
   ```bash
   docker exec myshop-prod php bin/console doctrine:migrations:status
   ```

3. **If data corrupted**:
   - **DO NOT** proceed
   - Consult database administrator
   - Restore from backup if necessary

4. **If migration incomplete**:
   ```bash
   # Complete migration
   docker exec myshop-prod php bin/console doctrine:migrations:migrate --no-interaction
   ```

---

## Deployment Procedures

### Standard Deployment (via Jenkins)

**Prerequisites**:
- All tests passing in test environment
- Code review completed
- PR merged to master

**Steps**:

1. **Monitor deployment**:
   - Watch Jenkins pipeline: http://localhost:8080/blue
   - Follow in Slack: #deployments

2. **When prompted for production approval**:
   - Review test deployment results
   - Verify E2E tests passed
   - Click "Approve" in Jenkins

3. **Post-deployment**:
   ```bash
   # Verify production
   bash scripts/deploy/smoke-test.sh production
   
   # Monitor for 15 minutes
   docker logs myshop-prod -f
   ```

4. **Confirm success** in Slack

### Manual Deployment (emergency)

**Use only when Jenkins is unavailable**

```bash
# 1. Navigate to project
cd /var/www2/myshop

# 2. Update code
git fetch origin
git checkout master
git pull origin master

# 3. Install dependencies
composer install --no-dev --optimize-autoloader

# 4. Deploy using Ansible
ansible-playbook deployment/deploy-local.yml \
  -i deployment/inventories/local-production/hosts \
  -e "branch=$(git rev-parse HEAD)" \
  --vault-password-file .vault_pass

# 5. Verify
bash scripts/deploy/smoke-test.sh production
```

### Hotfix Deployment

**For critical production bugs**

1. **Create hotfix branch**:
   ```bash
   git checkout master
   git pull
   git checkout -b hotfix/critical-bug-fix
   ```

2. **Fix and commit**:
   ```bash
   # Make fix
   git add .
   git commit -m "hotfix: Fix critical bug"
   git push origin hotfix/critical-bug-fix
   ```

3. **Deploy directly** (skip PR if truly critical):
   ```bash
   # Merge to master
   git checkout master
   git merge hotfix/critical-bug-fix
   git push origin master
   
   # Jenkins will auto-deploy, OR manual deploy:
   ansible-playbook deployment/deploy-local.yml \
     -i deployment/inventories/local-production/hosts \
     --vault-password-file .vault_pass
   ```

4. **Create PR after** for code review

---

## Monitoring

### Key Metrics to Watch

**Application Health**:
- HTTP response times
- Error rates
- Database query performance
- Queue sizes

**Infrastructure Health**:
- Docker container status
- Disk space usage (alert at 80%)
- Memory usage
- CPU usage

### Health Check Commands

```bash
# Overall health
curl http://localhost:8082/health | jq

# Individual services
curl http://localhost:8082/api/health/database
curl http://localhost:8082/api/health/redis
curl http://localhost:8082/api/health/mongodb
curl http://localhost:8082/api/health/rabbitmq
curl http://localhost:8082/api/health/disk

# Container health
docker ps --format "table {{.Names}}\t{{.Status}}"
```

### Log Monitoring

```bash
# Real-time logs
docker logs myshop-prod -f

# Search for errors
docker logs myshop-prod --since 1h | grep -i error

# Check PHP errors
docker exec myshop-prod tail -f /var/log/php8.3-fpm.log

# Check application logs
docker exec myshop-prod tail -f /var/www/myshop/current/var/log/prod.log
```

---

## Maintenance

### Daily Maintenance

```bash
# Clean up old Docker images
docker image prune -f

# Check disk space
df -h | grep -E 'Filesystem|/$'

# Verify backups completed
ls -lt /backups/ | head -5
```

### Weekly Maintenance

**Every Sunday 2 AM** (automated or manual):

1. **Clean Docker system**:
   ```bash
   docker system prune -a -f --volumes
   ```

2. **Rotate logs**:
   ```bash
   docker exec myshop-prod find /var/www/myshop/current/var/log -name "*.log" -mtime +7 -delete
   ```

3. **Update dependencies**:
   ```bash
   # Check for security updates
   docker exec myshop-prod composer audit
   ```

### Monthly Maintenance

**First Saturday of each month**:

1. **Update Jenkins plugins**:
   - Navigate to: Manage Jenkins → Plugin Manager
   - Update all plugins
   - Restart Jenkins

2. **Review and optimize pipelines**:
   - Check average build times
   - Identify slow stages
   - Optimize where possible

3. **Test rollback procedure**:
   ```bash
   # Test on test environment
   # Follow rollback procedure documentation
   ```

4. **Security audit**:
   - Review access logs
   - Check for unauthorized access attempts
   - Rotate credentials if needed

### Quarterly Maintenance

**Disaster recovery test**:

1. **Backup verification**:
   - Restore from backup in test environment
   - Verify data integrity

2. **Full pipeline test**:
   - Test complete CI/CD workflow
   - Document any issues

3. **Capacity planning**:
   - Review resource usage trends
   - Plan for scaling if needed

---

## Disaster Recovery

### Database Backup

**Automated** (daily):
```bash
# Backup script runs daily at 03:00
docker exec myshop-mysql-prod mysqldump -u root -p myshop_prod > backup-$(date +%Y%m%d).sql
```

**Manual backup**:
```bash
docker exec myshop-mysql-prod mysqldump -u root -pprodpass myshop_prod > backup-emergency.sql
```

**Restore**:
```bash
docker exec -i myshop-mysql-prod mysql -u root -pprodpass myshop_prod < backup-YYYYMMDD.sql
```

### Complete System Recovery

**From catastrophic failure**:

1. **Restore infrastructure**:
   ```bash
   git clone git@github.com:yourorg/myshop.git
   cd myshop
   docker-compose -f docker-compose.ci.yml up -d
   ```

2. **Restore database**:
   ```bash
   # See Database Backup section above
   ```

3. **Deploy application**:
   ```bash
   ansible-playbook deployment/deploy-local.yml \
     -i deployment/inventories/local-production/hosts
   ```

4. **Verify all services**:
   ```bash
   bash scripts/deploy/docker-health.sh
   bash scripts/deploy/smoke-test.sh production
   ```

---

## On-Call Procedures

### Rotation Schedule

- Week rotation
- Handoff: Friday 5 PM
- Backup: Previous week's on-call

### Escalation Path

1. On-call engineer (PagerDuty)
2. Lead DevOps Engineer
3. CTO

### Incident Response

1. **Acknowledge**: Within 5 minutes
2. **Assess**: Within 10 minutes
3. **Respond**: Within 15 minutes
4. **Resolve**: Best effort, escalate if needed
5. **Document**: Update runbook and incident log

---

## Contacts

- **DevOps Team**: #devops-team (Slack)
- **On-Call**: PagerDuty
- **Emergency**: CTO (phone)

## Resources

- [Troubleshooting Guide](troubleshooting.md)
- [Rollback Procedures](rollback-procedure.md)
- [Jenkins README](../../.jenkins/README.md)
- [Project Documentation](../../docs/)
