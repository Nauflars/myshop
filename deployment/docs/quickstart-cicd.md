# MyShop CI/CD Pipeline: Getting Started

## Quick Start (5 minutes)

### Prerequisites

- Docker Desktop 24.0+ (with Docker Compose V2)
- Git 2.30+
- 16GB RAM (8GB minimum)
- 20GB free disk space

### 1. Start CI/CD Infrastructure

```bash
# Clone repository
git clone git@github.com:yourorg/myshop.git
cd myshop

# Start all containers
docker-compose -f docker-compose.ci.yml up -d

# Verify containers are running
docker-compose -f docker-compose.ci.yml ps
```

Expected output:
```
NAME                    STATUS              PORTS
myshop-jenkins          Up (healthy)        0.0.0.0:8080->8080/tcp
myshop-test             Up (healthy)        0.0.0.0:8081->80/tcp
myshop-prod             Up (healthy)        0.0.0.0:8082->80/tcp
myshop-mysql-test       Up (healthy)        3306/tcp
myshop-mysql-prod       Up (healthy)        3306/tcp
...
```

### 2. Initialize Jenkins

```bash
# Get Jenkins initial password
docker exec myshop-jenkins cat /var/jenkins_home/secrets/initialAdminPassword

# Open Jenkins
open http://localhost:8080
```

Follow setup wizard:
1. Paste initial admin password
2. Install suggested plugins
3. Create admin user
4. Set Jenkins URL: `http://localhost:8080`

### 3. Verify Environments

```bash
# Test environment (should return 200 OK)
curl http://localhost:8081/health

# Production environment (should return 200 OK)
curl http://localhost:8082/health
```

## Development Workflow

### Create Feature Branch

```bash
git checkout -b feature/my-new-feature
# Make changes
git add .
git commit -m "feat: add new feature"
git push origin feature/my-new-feature
```

### Open Pull Request

1. Create PR on GitHub
2. Jenkins automatically runs PR validation pipeline
3. View results in PR status checks
4. After approval, merge to master

### Automatic Deployment

After merge to master:
1. Jenkins automatically builds and deploys to test (http://localhost:8081)
2. E2E tests run automatically
3. Manual approval required for production
4. After approval, deploys to production (http://localhost:8082)
5. Smoke tests verify deployment

## Key URLs

- **Jenkins**: http://localhost:8080
- **Test Environment**: http://localhost:8081
- **Production Environment**: http://localhost:8082
- **RabbitMQ Test**: http://localhost:15672
- **RabbitMQ Prod**: http://localhost:15673

## Common Commands

```bash
# Start all containers
docker-compose -f docker-compose.ci.yml up -d

# Stop all containers
docker-compose -f docker-compose.ci.yml down

# View container logs
docker logs myshop-test -f
docker logs myshop-prod -f

# Run smoke tests
bash scripts/deploy/smoke-test.sh test
bash scripts/deploy/smoke-test.sh production

# Check health
bash scripts/deploy/docker-health.sh

# Manual deployment (if Jenkins unavailable)
ansible-playbook deployment/deploy-local.yml \
  -i deployment/inventories/local-test/hosts
```

## Troubleshooting

**Jenkins won't start**:
```bash
# Check logs
docker logs myshop-jenkins

# Restart
docker-compose -f docker-compose.ci.yml restart jenkins
```

**Container unhealthy**:
```bash
# Check health status
docker inspect myshop-test | grep -A 10 Health

# Restart container
docker-compose -f docker-compose.ci.yml restart myshop-test
```

**Cannot connect to container**:
```bash
# Verify network
docker network ls | grep cicd

# Check container is on network
docker inspect myshop-test | grep NetworkMode
```

## Next Steps

1. **Configure Ansible Vault**: See [Deployment Documentation](deployment/docs/)
2. **Set up E2E Tests**: See [E2E Testing Guide](tests/E2E/)
3. **Configure Slack Notifications**: Add webhook URL to Jenkins credentials
4. **Review Pipelines**: Check [Jenkins README](.jenkins/README.md)

## Support

- **Documentation**: See [docs/](docs/) directory
- **Troubleshooting**: [deployment/docs/troubleshooting.md](deployment/docs/troubleshooting.md)
- **Slack**: #devops-team
