# CI/CD Pipeline Quickstart Guide (Local Docker Mode)

**Version**: 2.0.0  
**Last Updated**: 2026-02-13

## Overview

This guide provides step-by-step instructions for setting up and using the MyShop CI/CD pipeline **locally using Docker Compose**. The pipeline runs entirely on your development machine with no external servers required. It automates deployment from feature branch development through to production simulation using Jenkins, Ansible/Ansistrano, and Playwright - all within Docker containers.

**Key Advantages**:
- ✅ No external servers or SSH keys required
- ✅ Complete CI/CD pipeline on your laptop
- ✅ Identical environments across all developers
- ✅ Works offline after initial setup
- ✅ Fast feedback (no network latency)
- ✅ Safe experimentation (break things without consequences)

---

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Initial Setup](#initial-setup)
3. [Developer Workflow](#developer-workflow)
4. [Jenkins Configuration](#jenkins-configuration)
5. [Ansible Setup](#ansible-setup)
6. [Running Deployments](#running-deployments)
7. [Rollback Procedures](#rollback-procedures)
8. [Troubleshooting](#troubleshooting)

---

## Prerequisites

### Required Software on Your Local Machine

- **Docker Desktop 24.0+** or **Docker Engine 24.0+ with Docker Compose V2**
- **Git 2.30+**
- **Node.js 20+** (for Playwright installation, optional - can run in container)
- **Make** (optional, for convenience commands)

**That's it!** No Ansible, no SSH, no remote servers needed.

### System Requirements

- **RAM**: Minimum 8GB (16GB recommended for running all containers)
- **Disk Space**: Minimum 20GB free space
- **OS**: Windows 10/11 with WSL2, macOS 10.15+, or Linux

---

## Initial Setup

### Step 1: Clone the Repository

```bash
git clone git@github.com:yourorg/myshop.git
cd myshop
```

### Step 2: Start the CI/CD Infrastructure

```bash
# Start all CI/CD containers (Jenkins, test, prod environments)
docker-compose -f docker-compose.ci.yml up -d

# Verify all containers are running
docker-compose -f docker-compose.ci.yml ps
```

You should see:
- `myshop-jenkins` (Jenkins CI server) on port 8080
- `myshop-test` (Test environment) on port 8081
- `myshop-prod` (Production environment) on port 8082
- Supporting services: MySQL, Redis, MongoDB for each environment

### Step 3: Initialize Jenkins

1. **Get initial admin password**:
   ```bash
   docker exec myshop-jenkins cat /var/jenkins_home/secrets/initialAdminPassword
   ```

2. **Open Jenkins**: http://localhost:8080

3. **Complete setup wizard**:
   - Paste the admin password
   - Choose "Install suggested plugins"
   - Create your admin user
   - Set Jenkins URL to `http://localhost:8080`

### Step 4: Install Ansible Roles (Inside Jenkins Container)

```bash
# Enter Jenkins container
docker exec -it myshop-jenkins bash

# Install Ansistrano roles
cd /var/jenkins_home/workspace
ansible-galaxy collection install community.general
ansible-galaxy role install ansistrano.deploy ansistrano.rollback

exit
```

### Step 5: Verify Container Health

```bash
# Check test environment
curl http://localhost:8081/health
# Should return: {"status":"ok"}

# Check production environment
curl http://localhost:8082/health
# Should return: {"status":"ok"}
```

### Step 6: Configure Ansible Vault (Optional for Development)

For local development, you can use simplified secrets or skip vault encryption:

```bash
# Create vault password file (local development only!)
echo "dev-vault-password-123" > .vault_pass
chmod 600 .vault_pass

# Add to .gitignore
echo ".vault_pass" >> .gitignore
```

#### Create Test Environment Variables

```bash
vim deployment/inventories/test/group_vars/all.yml
```

Configure basic variables (see [ansible-inventory-schema.md](contracts/ansible-inventory-schema.md) for full template).

#### Create Encrypted Vault for Secrets

```bash
# Create encrypted vault file
ansible-vault create deployment/inventories/test/group_vars/all/vault.yml
```

Enter secrets when prompted (database passwords, API keys, etc.).

**Repeat for production environment with production-specific values.**

---

## Developer Workflow

### Feature Development Process (Local Docker Pipeline)

#### 1. Start CI/CD Infrastructure (if not already running)

```bash
docker-compose -f docker-compose.ci.yml up -d
```

#### 2. Create Feature Branch

```bash
git checkout master
git pull origin master
git checkout -b feature/your-feature-name
```

#### 3. Develop and Commit

```bash
# Make changes to your code
git add .
git commit -m "feat: add new feature"
git push origin feature/your-feature-name
```

#### 4. Trigger Jenkins Build Manually (Development)

**Option A: Via Jenkins UI**
1. Open http://localhost:8080
2. Navigate to your pipeline job
3. Click "Build with Parameters"
4. Select your branch: `feature/your-feature-name`
5. Click "Build"

**Option B: Via CLI (requires Jenkins CLI)**
```bash
java -jar jenkins-cli.jar -s http://localhost:8080/ build myshop-pipeline \
  -p BRANCH_NAME=feature/your-feature-name
```

#### 5. Monitor Pipeline Execution

Watch your pipeline in real-time:
- **Jenkins UI**: http://localhost:8080/blue/organizations/jenkins/myshop-pipeline/activity
- **Test Deployment**: http://localhost:8081 (after deploy stage completes)
- **Logs**: Available in Jenkins console output

#### 6. Run E2E Tests Locally (Optional)

```bash
cd tests/E2E
npm install
npx playwright install --with-deps

# Test against local Docker test environment
BASE_URL=http://localhost:8081 npm test

# Test against local Docker production environment
BASE_URL=http://localhost:8082 npm test
```

#### 7. Verify Deployment in Docker Containers

```bash
# Check test environment status
curl http://localhost:8081/health

# Check production environment status
curl http://localhost:8082/health

# View application logs
docker logs myshop-test --tail 50
docker logs myshop-prod --tail 50

# Enter container to inspect
docker exec -it myshop-test bash
# Inside container, check deployed releases
ls -la /var/www/myshop/releases/
exit
```

#### 8. Debug Deployment Issues

```bash
# View Ansible playbook execution logs
docker logs myshop-jenkins | grep ansible

# Check container disk usage
docker exec myshop-test df -h

# View PHP-FPM logs inside container
docker exec myshop-test tail -f /var/log/php8.3-fpm.log

# Check database connectivity from container
docker exec myshop-test php bin/console doctrine:query:sql "SELECT 1"
```

#### 6. Merge to Master

Once approved and all checks pass:
```bash
# Via GitHub UI or command line
git checkout master
git merge feature/your-feature-name
git push origin master
```

#### 7. Automatic Deployment

After merge to `master`, Jenkins automatically:
1. ✅ Runs full test suite
2. ✅ Deploys to **test** environment
3. ✅ Runs E2E tests on test
4. ⏸️ **Waits for manual approval** for production
5. ✅ Deploys to **production** (after approval)
6. ✅ Runs smoke tests
7. ✅ Sends Slack notification

---

## Jenkins Configuration

### Creating the Jenkins Pipeline Job

#### 1. Log in to Jenkins

Navigate to `https://jenkins.myshop.com/`

#### 2. Create New Item

- Click "New Item"
- Enter name: `myshop-deployment-pipeline`
- Select "Multibranch Pipeline"
- Click OK

#### 3. Configure Branch Sources

- **Branch Sources** → Add source → Git
- **Project Repository**: `git@github.com:yourorg/myshop.git`
- **Credentials**: Select your GitHub SSH key credential
- **Behaviors**: 
  - Discover branches: All branches
  - Discover pull requests: Merged + origin

#### 4. Build Configuration

- **Mode**: by Jenkinsfile
- **Script Path**: `.jenkins/Jenkinsfile` (for master branch)
- **Script Path**: `.jenkins/Jenkinsfile.pr` (for PR branches)

#### 5. Configure Webhook

- **Scan Multibranch Pipeline Triggers**
  - Check: "Scan by webhook"
  - **Trigger token**: `myshop-webhook-token`

- In GitHub repository settings → Webhooks:
  - **Payload URL**: `https://jenkins.myshop.com/github-webhook/`
  - **Content type**: `application/json`
  - **Events**: Push events, Pull requests

#### 6. Add Jenkins Credentials

Navigate to **Manage Jenkins** → **Credentials** → **System** → **Global credentials**

Add the following credentials:

| ID | Type | Description | Value |
|----|------|-------------|-------|
| `ansible-vault-password` | Secret file | Ansible Vault password | Upload file containing vault password |
| `slack-webhook-url` | Secret text | Slack webhook URL | `https://hooks.slack.com/services/...` |
| `github-ssh-key` | SSH username with private key | GitHub SSH key | Your deploy SSH private key |

#### 7. Configure Jenkins Agent Labels

Ensure Jenkins agents have correct labels:
- `php83` - Agent with PHP 8.3, Composer, Ansible
- `playwright` - Agent with Node.js 20 and Playwright browsers

---

## Ansible Setup

### Project Structure

```
deployment/
├── ansible.cfg                    # Ansible config
├── deploy.yml                     # Main deployment playbook
├── rollback.yml                   # Rollback playbook
├── inventories/
│   ├── test/                      # Test environment
│   │   ├── hosts
│   │   └── group_vars/all.yml
│   └── production/                # Production environment
│       ├── hosts
│       └── group_vars/all/vault.yml (encrypted)
├── hooks/
│   ├── before-symlink.yml         # Pre-deployment tasks
│   └── after-symlink.yml          # Post-deployment tasks
└── roles/
    └── requirements.yml           # Ansistrano roles
```

### Manual Deployment (for testing)

#### Deploy to Test Environment

```bash
cd deployment

# Deploy specific branch
ansible-playbook -i inventories/test/hosts deploy.yml \
  -e "branch=master" \
  --vault-password-file .vault_password

# Or prompt for vault password
ansible-playbook -i inventories/test/hosts deploy.yml \
  -e "branch=master" \
  --ask-vault-pass
```

#### Deploy to Production

```bash
ansible-playbook -i inventories/production/hosts deploy.yml \
  -e "branch=master" \
  --vault-password-file .vault_password
```

### Deployment Process

Ansistrano performs the following steps:

1. **Setup**: Creates directory structure on target server
   ```
   /var/www/myshop/
   ├── current -> releases/20260213150000/
   ├── releases/
   ├── shared/
   └── repo/
   ```

2. **Update Code**: Clones repository to new release directory

3. **Before Symlink Hook** (see `hooks/before-symlink.yml`):
   - Install Composer dependencies
   - Run database migrations
   - Warm up cache
   - Build frontend assets

4. **Symlink**: Atomically switches `current` symlink to new release

5. **After Symlink Hook** (see `hooks/after-symlink.yml`):
   - Reload PHP-FPM
   - Clear OPcache
   - Restart Messenger consumers

6. **Cleanup**: Removes old releases (keeps last 5 in production)

---

## Running Deployments

### Via Jenkins (Recommended)

#### For Test Environment

Deployment to test is automatic after merge to `master`:

1. Merge PR to master
2. Jenkins builds and tests
3. Automatically deploys to test
4. Runs E2E tests on test
5. Notifies Slack of result

#### For Production

1. Navigate to Jenkins job: `https://jenkins.myshop.com/job/myshop-deployment-pipeline/job/master/`
2. Click "Build Now" or wait for automatic trigger after test deployment
3. Pipeline will pause at "Deploy to Production" stage
4. Click "Deploy" to approve production deployment
5. Monitor deployment progress
6. Verify smoke tests pass
7. Check Slack notification

### Via Ansible (Manual/Emergency)

#### Quick Deployment

```bash
# Test
./scripts/deploy/deploy-test.sh

# Production (with confirmation)
./scripts/deploy/deploy-prod.sh
```

#### With Custom Options

```bash
ansible-playbook -i inventories/production/hosts deploy.yml \
  -e "branch=7f8a9b0c1d2e3f4a5b6c7d8e9f0a1b2c3d4e5f6" \
  -e "ansistrano_keep_releases=10" \
  --vault-password-file .vault_password \
  --check  # Dry-run mode
```

---

## Rollback Procedures

### When to Rollback

Rollback immediately if:
- Critical bugs discovered in production
- Smoke tests fail after deployment
- Database migrations cause issues
- Performance degradation >50%

### Rollback via Jenkins

1. Navigate to Jenkins job
2. Click "Build with Parameters"
3. Select job: `myshop-rollback`
4. Choose:
   - **Environment**: `production`
   - **Release**: `previous` (or specific release timestamp)
5. Confirm and run

### Rollback via Ansible

#### Quick Rollback to Previous Release

```bash
cd deployment

# Production
ansible-playbook -i inventories/production/hosts rollback.yml \
  --vault-password-file .vault_password

# Test
ansible-playbook -i inventories/test/hosts rollback.yml \
  --vault-password-file .vault_password
```

This will:
1. Confirm rollback (prompt)
2. Switch `current` symlink to previous release
3. Reload PHP-FPM
4. Clear cache
5. Run health checks
6. Notify Slack

#### Rollback to Specific Release

```bash
ansible-playbook -i inventories/production/hosts rollback.yml \
  -e "ansistrano_release_number=20260213120000" \
  --vault-password-file .vault_password
```

### Verify Rollback

```bash
# Check current deployment
ssh deploy@prod-web-01 "readlink /var/www/myshop/current"

# Check health
curl https://myshop.com/health
```

### Post-Rollback Actions

1. **Investigate**: Review logs to determine root cause
2. **Fix Forward**: Create hotfix branch with fix
3. **Test**: Verify fix in test environment
4. **Re-deploy**: Deploy fixed version when ready

---

## Monitoring & Verification

### Health Check Endpoints

After deployment, verify all health checks pass:

```bash
# Overall health
curl https://myshop.com/health

# Individual services
curl https://myshop.com/api/health/database
curl https://myshop.com/api/health/redis
curl https://myshop.com/api/health/mongodb
curl https://myshop.com/api/health/rabbitmq

# Detailed metrics
curl https://myshop.com/api/health/detailed
```

See [deployment-api.yaml](contracts/deployment-api.yaml) for full API specification.

### Smoke Tests

Automated smoke tests run post-deployment:

```bash
# Run manually
bash scripts/deploy/smoke-test.sh production
```

This tests:
- ✅ HTTP 200 response from homepage
- ✅ All health check endpoints
- ✅ Database connectivity
- ✅ Cache operations
- ✅ Critical API endpoints

### Logs

#### Jenkins Logs

- Web UI: `https://jenkins.myshop.com/job/myshop-deployment-pipeline/`
- Console output available for each build

#### Application Logs

```bash
# On target server
tail -f /var/www/myshop/shared/var/log/prod.log
tail -f /var/www/myshop/shared/var/log/dev.log
```

#### Deployment History

```bash
# On target server
cat /var/www/myshop/.last_deployment.json
```

---

## Troubleshooting

### Common Issues

#### Issue: Jenkins Pipeline Fails at Build Stage

**Error**: `composer install` fails

**Solution**:
```bash
# Clear Composer cache
rm -rf $WORKSPACE/.composer

# Or in Jenkinsfile, add:
sh 'composer clear-cache'
```

#### Issue: Ansible Vault Decryption Fails

**Error**: `ERROR! Attempting to decrypt but no vault secrets found`

**Solution**:
- Verify vault password is correct
- Ensure Jenkins credential ID matches Jenkinsfile: `ansible-vault-password`
- Test locally:
  ```bash
  ansible-vault view deployment/inventories/production/group_vars/all/vault.yml
  ```

#### Issue: Database Migrations Fail During Deployment

**Error**: Migration fails, deployment aborted

**Solution**:
1. SSH to target server
2. Check migration status:
   ```bash
   cd /var/www/myshop/releases/LATEST_RELEASE
   php bin/console doctrine:migrations:status
   ```
3. Manually fix migration or rollback:
   ```bash
   php bin/console doctrine:migrations:migrate prev
   ```
4. Re-run deployment or rollback

#### Issue: E2E Tests Fail on Test Environment

**Error**: Playwright tests timeout

**Solution**:
1. Check test environment health:
   ```bash
   curl https://test.myshop.com/health
   ```
2. Review Playwright report in Jenkins artifacts
3. Run tests locally with debug:
   ```bash
   cd tests/E2E
   PWDEBUG=1 npm test
   ```
4. Fix flaky tests or increase timeouts

#### Issue: SSH Connection Fails from Jenkins to Target Server

**Error**: `Permission denied (publickey)`

**Solution**:
- Verify SSH key added to Jenkins credentials
- Verify deploy user exists on target server
- Test SSH connection:
  ```bash
  ssh -i ~/.ssh/id_rsa_deploy deploy@target-server
  ```
- Add Jenkins agent SSH key to target server:
  ```bash
  ssh-copy-id -i /var/lib/jenkins/.ssh/id_rsa.pub deploy@target-server
  ```

#### Issue: Production Deployment Hangs at Manual Approval

**Error**: No one approved production deployment

**Solution**:
- Log in to Jenkins
- Navigate to paused job
- Click "Deploy" to approve
- Or abort if deployment should not proceed

#### Issue: Health Checks Fail After Deployment

**Error**: `503 Service Unavailable` from `/health`

**Solution**:
1. Check PHP-FPM status:
   ```bash
   sudo systemctl status php8.3-fpm
   ```
2. Check application logs:
   ```bash
   tail -f /var/www/myshop/shared/var/log/prod.log
   ```
3. Test individual services:
   ```bash
   curl https://myshop.com/api/health/database
   curl https://myshop.com/api/health/redis
   ```
4. If critical, rollback immediately:
   ```bash
   ansible-playbook -i inventories/production/hosts rollback.yml
   ```

### Getting Help

- **Jenkins Issues**: Contact DevOps team in `#devops` Slack channel
- **Application Issues**: Contact development team in `#engineering`
- **Infrastructure**: Open ticket in Jira project `INFRA`

---

## Best Practices

### For Developers

1. **Test Locally First**: Run tests locally before pushing
   ```bash
   php bin/phpunit
   vendor/bin/phpstan analyse src
   ```

2. **Small, Focused PRs**: Keep PRs small and focused on a single feature

3. **Write Tests**: Every new feature should have tests (unit + integration)

4. **Follow Branching Convention**:
   - `feature/` - New features
   - `bugfix/` - Bug fixes
   - `hotfix/` - Critical production fixes

5. **Monitor Deployments**: Watch Slack notifications after merge to master

### For DevOps

1. **Always Test in Test Environment**: Never deploy directly to production

2. **Keep Vault Passwords Secure**: Store vault passwords in password manager, never commit

3. **Monitor Disk Space**: Ensure servers have adequate space for deployments

4. **Regular Secret Rotation**: Rotate database passwords, API keys quarterly

5. **Backup Before Major Changes**: Create database backup before significant migrations

6. **Document Changes**: Update this quickstart guide when processes change

---

## Reference

### Key Files

| File | Purpose |
|------|---------|
| `.jenkins/Jenkinsfile` | Main production pipeline |
| `.jenkins/Jenkinsfile.pr` | Pull request validation pipeline |
| `deployment/deploy.yml` | Ansible deployment playbook |
| `deployment/rollback.yml` | Ansible rollback playbook |
| `tests/E2E/playwright.config.ts` | Playwright E2E test configuration |
| `scripts/deploy/smoke-test.sh` | Post-deployment smoke tests |

### Useful Commands

```bash
# Ansible: Test connection
ansible all -i inventories/test/hosts -m ping

# Ansible: Deploy to test
ansible-playbook -i inventories/test/hosts deploy.yml -e "branch=master"

# Ansible: Rollback production
ansible-playbook -i inventories/production/hosts rollback.yml

# Playwright: Run E2E tests locally
npm test --prefix tests/E2E

# Check deployment history on server
ssh deploy@server "cat /var/www/myshop/.last_deployment.json"

# Check current release on server
ssh deploy@server "readlink /var/www/myshop/current"

# View available releases
ssh deploy@server "ls -lh /var/www/myshop/releases/"
```

---

## Next Steps

After completing this quickstart:

1. ✅ Set up test environment and verify deployments work
2. ✅ Set up production environment (with extra caution)
3. ✅ Run E2E test suite and verify all tests pass
4. ✅ Configure Slack notifications
5. ✅ Train team on deployment process
6. ✅ Document environment-specific configurations
7. ✅ Set up monitoring alerts for deployment failures

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-02-13 | Initial quickstart guide |

---

## Additional Resources

- [Research Document](research.md) - Technology decisions and best practices
- [Data Model](data-model.md) - Pipeline entities and state
- [Jenkinsfile Contract](contracts/jenkinsfile-schema.md) - Jenkins pipeline structure
- [Ansible Inventory Contract](contracts/ansible-inventory-schema.md) - Ansible configuration
- [Deployment API](contracts/deployment-api.yaml) - Health check API specification
- [Ansistrano Documentation](https://ansistrano.com/docs) - Official Ansistrano docs
- [Playwright Documentation](https://playwright.dev) - Official Playwright docs
