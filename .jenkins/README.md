# Jenkins CI/CD Pipeline Documentation

## Overview

This directory contains the Jenkins pipeline configuration and supporting scripts for the MyShop CI/CD automation. The pipeline automates the complete workflow from PR validation through production deployment using local Docker containers.

## Structure

```
.jenkins/
├── Dockerfile.jenkins          # Custom Jenkins image with required tools
├── Jenkinsfile                 # Main pipeline (master branch deployments)
├── Jenkinsfile.pr              # PR validation pipeline
├── Jenkinsfile.rollback        # Rollback pipeline
├── stages/                     # Reusable stage scripts
│   ├── deploy.groovy           # Deployment stage logic
│   └── e2e.groovy              # E2E testing stage logic
├── scripts/                    # Helper scripts
│   └── notify-slack.sh         # Slack notification script
└── configs/                    # Configuration files
```

## Pipelines

### 1. PR Validation Pipeline (`Jenkinsfile.pr`)

**Trigger**: Pull request opened/updated
**Purpose**: Validate code quality before merge
**Stages**:
1. Validate (`composer validate`, syntax check)
2. Test (parallel: unit, integration, static analysis)
3. Report results to PR

**Duration**: ~8-12 minutes

### 2. Main Deployment Pipeline (`Jenkinsfile`)

**Trigger**: Merge to master branch
**Purpose**: Automated deployment to test and production
**Stages**:
1. Build (dependencies, assets, artifacts)
2. Test (full test suite)
3. Deploy to Test
4. Health Check - Test
5. E2E Tests on Test
6. **Manual Approval** (production only)
7. Deploy to Production
8. Smoke Tests - Production
9. Tag Release

**Duration**: ~25-40 minutes (excluding manual approval)

### 3. Rollback Pipeline (`Jenkinsfile.rollback`)

**Trigger**: Manual execution
**Purpose**: Rollback to previous release
**Parameters**:
- Environment (test/production)
- Release Version (timestamp or "previous")
- Rollback Reason (required)

**Stages**:
1. Validate Rollback Request
2. Confirm Rollback (manual approval)
3. Execute Rollback
4. Verify Rollback
5. Health Checks
6. Clear Caches

**Duration**: ~5-10 minutes

## Custom Jenkins Image

The custom Jenkins Docker image includes:
- Docker CLI (for Docker-in-Docker operations)
- Ansible & Ansible-Lint
- Node.js 20 (for Playwright)
- Composer (for PHP dependencies)
- Pre-installed Jenkins plugins

**Build**: Image is built automatically from `docker-compose.ci.yml`

## Jenkins Configuration

### Required Plugins

Pre-installed in custom image:
- `git` - Git integration
- `workflow-aggregator` - Pipeline functionality
- `docker-workflow` - Docker integration
- `ansible` - Ansible playbook execution
- `blueocean` - Modern UI
- `github` - GitHub integration
- `github-branch-source` - PR discovery
- `slack` - Slack notifications
- `junit` - Test result publishing
- `htmlpublisher` - HTML report publishing

### Required Credentials

Configure in Jenkins: **Manage Jenkins** → **Credentials**

| ID | Type | Description | Usage |
|----|------|-------------|-------|
| `ansible-vault-password` | Secret file | Ansible Vault password | Decrypt secrets |
| `github-ssh-key` | SSH key | GitHub deploy key | Clone repository |

### Optional Credentials

| ID | Type | Description |
|----|------|-------------|
| `slack-webhook-url` | Secret text | Slack webhook for notifications |

## Environment Variables

Used in pipelines:

| Variable | Description | Example |
|----------|-------------|---------|
| `COMPOSER_HOME` | Composer cache directory | `${WORKSPACE}/.composer` |
| `COMPOSER_ALLOW_SUPERUSER` | Allow Composer as root | `1` |
| `ANSIBLE_VAULT_PASSWORD_FILE` | Path to vault password | From credentials |
| `BASE_URL` | E2E test target URL | `http://localhost:8081` |

## Stage Scripts

### deploy.groovy

Reusable deployment logic:
```groovy
def deploy(String environment, String containerName) {
    // Container health check
    // Disk space check
    // Ansible deployment
}
```

**Usage in Jenkinsfile**:
```groovy
script {
    def deployLib = load '.jenkins/stages/deploy.groovy'
    deployLib.deploy('production', 'myshop-prod')
}
```

### e2e.groovy

E2E testing execution:
```groovy
def runE2ETests(String environment, String baseUrl) {
    // Install Playwright
    // Run tests with retries
    // Publish reports
}
```

## Slack Notifications

The pipeline sends notifications to Slack for:
- ✓ Successful deployments
- ✗ Failed deployments
- ⚠ Aborted pipelines
- 🔄 Rollback events

**Configuration**:
1. Create Slack webhook in Slack workspace settings
2. Add webhook URL to Jenkins credentials as `slack-webhook-url`
3. Set `SLACK_WEBHOOK_URL` environment variable

**Script**: `.jenkins/scripts/notify-slack.sh`

## Troubleshooting

### Issue: Pipeline fails at "Deploy to Test"

**Symptoms**: Ansible playbook fails with connection error

**Solutions**:
1. Verify container is running: `docker ps | grep myshop-test`
2. Check Ansible inventory: `deployment/inventories/local-test/hosts`
3. Test Ansible connectivity: `ansible all -i deployment/inventories/local-test/hosts -m ping`

### Issue: E2E tests timeout

**Symptoms**: Playwright tests fail with timeout errors

**Solutions**:
1. Check application is accessible: `curl http://localhost:8081/health`
2. Increase timeout in `tests/E2E/playwright.config.ts`
3. Check browser installation: `npx playwright install --with-deps`

### Issue: Vault password error

**Symptoms**: Ansible fails with "Decryption failed"

**Solutions**:
1. Verify credential exists: Check Jenkins credentials
2. Test vault password: `ansible-vault view deployment/inventories/local-test/group_vars/all/vault.yml`
3. Recreate vault files if corrupted

### Issue: Docker socket permission denied

**Symptoms**: Pipeline fails with "permission denied" for `/var/run/docker.sock`

**Solutions**:
1. Check Docker socket permissions on host
2. Add Jenkins user to docker group: `usermod -aG docker jenkins`
3. Restart Jenkins container

## Best Practices

### Pipeline Development

1. **Test in PR pipeline first**: Validate changes before modifying main pipeline
2. **Use timeouts**: Every stage should have a timeout to prevent hanging
3. **Parallel where possible**: Run independent stages in parallel
4. **Fail fast**: Exit early on critical failures
5. **Archive artifacts**: Keep build artifacts for debugging

### Security

1. **Never commit secrets**: Use Ansible Vault or Jenkins credentials
2. **Limit approvers**: Restrict production deployment approval
3. **Audit trail**: All deployments logged with user and reason
4. **Rotate credentials**: Especially before sharing access

### Performance

1. **Cache dependencies**: Use Composer cache, npm cache
2. **Incremental builds**: Only rebuild what changed
3. **Optimize Docker layers**: Structure Dockerfile for caching
4. **Clean workspace**: Remove old builds to save disk space

## Monitoring

### Pipeline Metrics

Track these metrics:
- Build success rate
- Average build duration
- Time to deploy
- Rollback frequency
- Test pass rate

**View in Jenkins**: Blue Ocean provides visual metrics

### Logs

Pipeline logs are stored:
- Jenkins: Build console output
- Application: `var/log/deployments.log`
- Rollback: `var/log/rollbacks.log`

## Integration with GitHub

### Webhook Setup

1. Go to GitHub repository → Settings → Webhooks
2. Add webhook:
   - URL: `http://your-jenkins:8080/github-webhook/`
   - Content type: `application/json`
   - Events: Push, Pull requests

### Branch Protection

Recommended branch protection rules for `master`:
- ✓ Require pull request reviews (min 1)
- ✓ Require status checks to pass (CI/Jenkins)
- ✓ Require branches to be up to date
- ✓ Include administrators

## Maintenance

### Regular Tasks

**Weekly**:
- Review failed builds
- Clean up old artifacts
- Check disk space usage

**Monthly**:
- Update Jenkins plugins
- Review and optimize pipelines
- Test rollback procedure

**Quarterly**:
- Update base Docker images
- Security audit
- Disaster recovery test

## Further Reading

- [Ansible Deployment Documentation](../deployment/README.md)
- [Rollback Procedures](../deployment/docs/rollback-procedure.md)
- [Troubleshooting Guide](../deployment/docs/troubleshooting.md)
- [E2E Testing Guide](../tests/E2E/README.md)

## Support

For pipeline issues:
- Slack: #devops-team
- Email: devops@myshop.com
- Documentation: This README
