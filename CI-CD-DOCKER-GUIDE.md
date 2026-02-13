# Docker Compose CI/CD Infrastructure Guide

## Overview

`docker-compose.ci.yml` defines the complete local CI/CD infrastructure for MyShop, including Jenkins, test environment, production environment, and all supporting services (database, cache, message queue).

## Architecture

```
┌─────────────────────────────────────────────────────┐
│                  Local Development Machine          │
├─────────────────────────────────────────────────────┤
│                                                      │
│  ┌──────────────┐                                   │
│  │   Jenkins    │  Port: 8080                       │
│  │   CI Server  │  Controls deployments             │
│  └──────────────┘                                   │
│         │                                           │
│    ┌────┴────┐                                      │
│    │         │                                      │
│  ┌─▼────────────┐          ┌────────────────┐     │
│  │ Test Env     │          │ Production Env │     │
│  │ Port: 8081   │          │ Port: 8082     │     │
│  └──────────────┘          └────────────────┘     │
│    │ │ │ │                   │ │ │ │              │
│    │ │ │ └─MySQL-test       │ │ │ └─MySQL-prod   │
│    │ │ └───Redis-test        │ │ └───Redis-prod   │
│    │ └─────MongoDB-test       │ └─────MongoDB-prod │
│    └───────RabbitMQ-test      └───────RabbitMQ-prod│
│                                                      │
└─────────────────────────────────────────────────────┘
```

## Quick Start

### Start All Services

```bash
cd /var/www2/myshop
docker-compose -f docker-compose.ci.yml up -d
```

**Wait time**: ~2-3 minutes for all services to be healthy

**Verify**:
```bash
docker- -f docker-compose.ci.yml ps
```

### Access Services

| Service | URL | Purpose |
|---------|-----|---------|
| Jenkins | http://localhost:8080 | CI/CD orchestration |
| Test App | http://localhost:8081 | Test environment |
| Prod App | http://localhost:8082 | Production simulation |
| RabbitMQ Test | http://localhost:15672 | Message queue admin (test) |
| RabbitMQ Prod | http://localhost:15673 | Message queue admin (prod) |

### Stop All Services

```bash
docker-compose -f docker-compose.ci.yml stop
```

**Note**: Data is preserved in Docker volumes

### Stop and Remove Everything

```bash
docker-compose -f docker-compose.ci.yml down -v
```

**⚠️ WARNING**: This deletes all data, databases, and Jenkins configuration!

## Service Details

### Jenkins (CI Server)

**Container**: `myshop-jenkins`
**Image**: Built from `.jenkins/Dockerfile.jenkins`
**Ports**: 8080 (UI), 50000 (agent)

**Features**:
- Docker CLI (for managing other containers)
- Ansible (for deployments)
- Node.js 20 (for Playwright)
- Composer (for PHP dependencies)

**First-Time Setup**:
```bash
# Get initial admin password
docker exec myshop-jenkins cat /var/jenkins_home/secrets/initialAdminPassword
```

**Volumes**:
- `jenkins_home:/var/jenkins_home` - Jenkins configuration and jobs
- `/var/run/docker.sock:/var/run/docker.sock` - Docker socket access
- `.:/workspace` - Project source code

### Test Environment

**Container**: `myshop-test`
**Port**: 8081
**Purpose**: Automated test deployment target

**Environment Variables**:
- `APP_ENV=test`
- `DATABASE_URL=mysql://root:testpass@mysql-test:3306/myshop_test`

**Volumes**:
- `test_releases` - Application releases
- `test_shared` - Shared files across releases (logs, uploads)

**Deployment Path**: `/var/www/myshop/`

### Production Environment

**Container**: `myshop-prod`
**Port**: 8082
**Purpose**: Production simulation (local)

**Environment Variables**:
- `APP_ENV=prod`
- `DATABASE_URL=mysql://root:prodpass@mysql-prod:3306/myshop_prod`

**Volumes**:
- `prod_releases` - Application releases
- `prod_shared` - Shared files across releases

### Supporting Services

#### MySQL (Test & Production)

**Containers**: `myshop-mysql-test`, `myshop-mysql-prod`
**Image**: `mysql:8.0`

**Test**:
- Port: 3306 (internal)
- Database: `myshop_test`
- Password: `testpass`

**Production**:
- Port: 3306 (internal)
- Database: `myshop_prod`
- Password: `prodpass`

**Connect**:
```bash
# Test database
docker exec -it myshop-mysql-test mysql -u root -ptestpass myshop_test

# Production database
docker exec -it myshop-mysql-prod mysql -u root -pprodpass myshop_prod
```

#### MongoDB (Test & Production)

**Containers**: `myshop-mongodb-test`, `myshop-mongodb-prod`
**Image**: `mongo:7`

**Connect**:
```bash
docker exec -it myshop-mongodb-test mongosh
```

#### Redis (Test & Production)

**Containers**: `myshop-redis-test`, `myshop-redis-prod`
**Image**: `redis:7-alpine`

**Connect**:
```bash
docker exec -it myshop-redis-test redis-cli
```

#### RabbitMQ (Test & Production)

**Containers**: `myshop-rabbitmq-test`, `myshop-rabbitmq-prod`
**Image**: `rabbitmq:3-management-alpine`

**Management UI**:
- Test: http://localhost:15672
- Prod: http://localhost:15673
- User: `guest`
- Password: `guest`

## Common Operations

### View Logs

```bash
# All services
docker-compose -f docker-compose.ci.yml logs -f

# Specific service
docker logs myshop-test -f
docker logs myshop-jenkins --tail 100
```

### Restart Service

```bash
docker-compose -f docker-compose.ci.yml restart myshop-test
```

### Rebuild Service

```bash
# Rebuild and restart
docker-compose -f docker-compose.ci.yml up -d --build myshop-test

# Force rebuild (no cache)
docker-compose -f docker-compose.ci.yml build --no-cache myshop-test
```

### Execute Commands in Container

```bash
# Interactive shell
docker exec -it myshop-test bash

# Single command
docker exec myshop-test php bin/console cache:clear

# As specific user
docker exec -u www-data myshop-test ls -la /var/www/myshop
```

### Check Health Status

```bash
# Check all containers
docker ps --format "table {{.Names}}\t{{.Status}}"

# Detailed health check
docker inspect myshop-test | grep -A 10 Health

# Custom health check script
bash scripts/deploy/docker-health.sh
```

## Volume Management

### List Volumes

```bash
docker volume ls | grep myshop
```

### Inspect Volume

```bash
docker volume inspect myshop_jenkins_home
```

### Backup Volume

```bash
# Backup Jenkins home
docker run --rm -v myshop_jenkins_home:/data -v $(pwd):/backup \
  alpine tar czf /backup/jenkins-home-backup.tar.gz /data

# Backup production database
docker exec myshop-mysql-prod mysqldump -u root -pprodpass myshop_prod > prod-db-backup.sql
```

### Restore Volume

```bash
# Restore Jenkins home
docker run --rm -v myshop_jenkins_home:/data -v $(pwd):/backup \
  alpine sh -c "cd /data && tar xzf /backup/jenkins-home-backup.tar.gz --strip 1"

# Restore production database
docker exec -i myshop-mysql-prod mysql -u root -pprodpass myshop_prod < prod-db-backup.sql
```

### Clear Volume (Reset)

```bash
# Stop services
docker-compose -f docker-compose.ci.yml stop

# Remove volume
docker volume rm myshop_test_releases

# Restart to recreate
docker-compose -f docker-compose.ci.yml up -d
```

## Network

**Network Name**: `myshop_cicd_network`
**Driver**: bridge

**Benefits**:
- Isolated from other Docker networks
- Services can communicate by container name
- No port conflicts with host

**Inspect Network**:
```bash
docker network inspect myshop_cicd_network
```

## Resource Management

### Check Resource Usage

```bash
# Overall Docker stats
docker stats

# Specific containers
docker stats myshop-jenkins myshop-test myshop-prod
```

### Set Resource Limits

Add to `docker-compose.ci.yml`:

```yaml
services:
  jenkins:
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 4G
        reservations:
          cpus: '1.0'
          memory: 2G
```

## Troubleshooting

### Container Won't Start

1. **Check logs**:
   ```bash
   docker logs myshop-test
   ```

2. **Check port availability**:
   ```bash
   lsof -i :8081  # For test environment
   ```

3. **Remove and recreate**:
   ```bash
   docker-compose -f docker-compose.ci.yml stop myshop-test
   docker-compose -f docker-compose.ci.yml rm -f myshop-test
   docker-compose -f docker-compose.ci.yml up -d myshop-test
   ```

### Out of Disk Space

```bash
# Check usage
docker system df

# Clean up
docker system prune -a -f --volumes

# Remove specific volumes
docker volume prune -f
```

### Containers Unhealthy

```bash
# Check health status
docker ps

# Inspect health check
docker inspect myshop-test | grep -A 20 Health

# Test health endpoint manually
curl http://localhost:8081/health
```

### Network Issues

```bash
# Recreate network
docker-compose -f docker-compose.ci.yml down
docker network prune -f
docker-compose -f docker-compose.ci.yml up -d
```

## Best Practices

1. **Always use `docker-compose` commands**:
   - Ensures consistency
   - Manages dependencies properly

2. **Don't expose production ports publicly**:
   - Only localhost (127.0.0.1)
   - Use VPN for remote access

3. **Regular backups**:
   - Jenkins configuration
   - Database data
   - Application files

4. **Monitor resource usage**:
   - Check `docker stats` regularly
   - Clean up unused containers/images

5. **Use health checks**:
   - Wait for services to be healthy before deploying
   - Monitor health status continuously

## Integration with CI/CD

The Docker Compose infrastructure integrates with Jenkins:

1. **Jenkins runs in container**: Uses Docker socket to control other containers
2. **Ansible deployments**: Target Docker containers via local connection
3. **E2E tests**: Run against containerized environments (8081, 8082)
4. **Health checks**: Automated verification post-deployment

## Further Reading

- [Jenkins Documentation](.jenkins/README.md)
- [Deployment Guide](deployment/docs/runbook.md)
- [Troubleshooting](deployment/docs/troubleshooting.md)
