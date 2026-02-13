# Jenkinsfile Contract Schema

**Version**: 1.0.0  
**Date**: 2026-02-13

## Overview

This document defines the contract structure for Jenkinsfiles used in the MyShop CI/CD pipeline. It establishes conventions for pipeline definition, stage naming, and integration points.

---

## Pipeline Structure Contract

### Main Production Pipeline (`Jenkinsfile`)

Triggered on: Push to `master` branch

**Required Stages** (in order):
1. `Build` - Compile and prepare application
2. `Test` - Run unit, integration, and quality checks
3. `Deploy to Test` - Deploy to test environment
4. `E2E Tests on Test` - Run Playwright tests against test environment
5. `Deploy to Production` - Deploy to production (requires manual approval)
6. `Smoke Tests` - Verify production deployment

**Stage Parameters**:

| Stage Name | Required Environment Variables | Outputs | Success Criteria |
|-----------|-------------------------------|---------|------------------|
| `Build` | `COMPOSER_HOME`, `APP_ENV` | `vendor/` directory, compiled assets | Exit code 0 |
| `Test` | `DATABASE_URL`, `APP_ENV=test` | JUnit XML, coverage reports | All tests pass |
| `Deploy to Test` | `ANSIBLE_VAULT_PASSWORD_FILE` | None | Ansible playbook exit code 0 |
| `E2E Tests on Test` | `BASE_URL=https://test.myshop.com` | Playwright HTML report | All E2E tests pass |
| `Deploy to Production` | `ANSIBLE_VAULT_PASSWORD_FILE` | None | Manual approval + playbook exit code 0 |
| `Smoke Tests` | `BASE_URL=https://myshop.com` | Health check results | All health checks return 200 |

---

### Pull Request Pipeline (`Jenkinsfile.pr`)

Triggered on: Push to any feature branch with open PR

**Required Stages** (in order):
1. `Validate PR` - Check composer.json validity, install dependencies
2. `Test` (parallel substages):
   - `Unit Tests`
   - `Integration Tests`
   - `Static Analysis` (PHPStan, PHP-CS-Fixer)

**Stage Parameters**:

| Stage Name | Required Environment Variables | Outputs | Success Criteria |
|-----------|-------------------------------|---------|------------------|
| `Validate PR` | None | `vendor/` directory | Composer validate passes |
| `Unit Tests` | `DATABASE_URL` (test DB), `APP_ENV=test` | JUnit XML | All unit tests pass |
| `Integration Tests` | `DATABASE_URL`, `REDIS_URL`, `MONGODB_URL` | JUnit XML | All integration tests pass |
| `Static Analysis` | None | PHPStan report, CS-Fixer report | No errors reported |

---

## Jenkins Environment Variables Contract

### Global Environment Variables

These variables MUST be available in Jenkins global configuration or injected via credentials:

| Variable Name | Type | Source | Example Value |
|--------------|------|--------|---------------|
| `COMPOSER_HOME` | Path | Computed | `${WORKSPACE}/.composer` |
| `APP_ENV` | String | Hardcoded in pipeline | `prod`, `test` |
| `ANSIBLE_VAULT_PASSWORD_FILE` | Secret File | Jenkins Credentials Store | `/tmp/vault-pass-{id}` |
| `SLACK_WEBHOOK_URL` | Secret Text | Jenkins Credentials Store | `https://hooks.slack.com/...` |
| `GIT_COMMIT` | String | Jenkins built-in | SHA-1 hash |
| `GIT_BRANCH` | String | Jenkins built-in | `origin/master` |
| `BUILD_URL` | String | Jenkins built-in | `https://jenkins.myshop.com/job/...` |

### Stage-Specific Environment Variables

#### Build Stage
```groovy
environment {
    COMPOSER_HOME = "${WORKSPACE}/.composer"
    APP_ENV = 'prod'
    NODE_ENV = 'production'
}
```

#### Test Stage
```groovy
environment {
    APP_ENV = 'test'
    DATABASE_URL = 'mysql://root:password@mysql-test:3306/myshop_test'
    REDIS_URL = 'redis://redis-test:6379'
    MONGODB_URL = 'mongodb://root:password@mongodb-test:27017'
}
```

#### E2E Stage
```groovy
environment {
    BASE_URL = "https://${params.ENVIRONMENT}.myshop.com"
    PLAYWRIGHT_JUNIT_OUTPUT_NAME = 'results.xml'
}
```

---

## Jenkins Credentials Contract

### Required Credentials in Jenkins Credential Store

| Credential ID | Type | Description | Usage |
|--------------|------|-------------|-------|
| `ansible-vault-password` | Secret File | Ansible Vault password file | Decrypt production secrets during deployment |
| `slack-webhook-url` | Secret Text | Slack incoming webhook URL | Send pipeline notifications |
| `github-ssh-key` | SSH Username with private key | GitHub SSH key for git operations | Clone repository on Jenkins agents |
| `docker-registry-credentials` | Username with password | Docker registry credentials (if using private registry) | Pull base images for builds |

**Credential Usage Example**:
```groovy
environment {
    ANSIBLE_VAULT_PASSWORD_FILE = credentials('ansible-vault-password')
    SLACK_WEBHOOK_URL = credentials('slack-webhook-url')
}
```

---

## Agent Labels Contract

### Required Jenkins Agent Labels

| Label | Description | Required Software | Use Case |
|-------|-------------|-------------------|----------|
| `php83` | PHP 8.3 agent | PHP 8.3, Composer, Node.js 20, Ansible | Main pipeline build and deployment |
| `docker` | Docker-enabled agent | Docker Engine, docker-compose | Build Docker images (future use) |
| `playwright` | E2E testing agent | Node.js 20, Playwright browsers installed | Run Playwright E2E tests |

**Agent Specification Example**:
```groovy
pipeline {
    agent { label 'php83' }
    // or for specific stage:
    stage('E2E Tests') {
        agent { label 'playwright' }
    }
}
```

---

## Parallel Stage Contract

### Test Stage Parallelization

The `Test` stage MUST run substages in parallel to reduce pipeline time:

```groovy
stage('Test') {
    parallel {
        stage('Unit Tests') {
            steps {
                sh 'php bin/phpunit --testsuite=unit'
            }
        }
        stage('Integration Tests') {
            steps {
                sh 'php bin/phpunit --testsuite=integration'
            }
        }
        stage('Code Quality') {
            steps {
                sh 'vendor/bin/phpstan analyse src'
                sh 'vendor/bin/php-cs-fixer fix --dry-run'
            }
        }
    }
}
```

**Contract Requirements**:
- All parallel stages MUST be independent (no shared state)
- Each parallel stage SHOULD have isolated test databases if needed
- Failure in ANY parallel stage fails the entire `Test` stage

---

## Post Actions Contract

### Required Post Actions

Every Jenkinsfile MUST include these post actions:

#### Always Execute
```groovy
post {
    always {
        // Archive test results for Jenkins UI
        junit testResults: 'var/log/phpunit/*.xml', allowEmptyResults: true
        
        // Archive Playwright reports
        publishHTML([
            allowMissing: true,
            alwaysLinkToLastBuild: true,
            keepAll: true,
            reportDir: 'tests/E2E/playwright-report',
            reportFiles: 'index.html',
            reportName: 'Playwright Test Report'
        ])
        
        // Clean workspace to save disk space (optional, conditional)
        cleanWs(
            deleteDirs: true,
            disableDeferredWipeout: true,
            notFailBuild: true,
            patterns: [
                [pattern: 'vendor/**', type: 'EXCLUDE'],
                [pattern: 'node_modules/**', type: 'EXCLUDE']
            ]
        )
    }
    
    success {
        // Notify Slack on success (only for master branch)
        script {
            if (env.BRANCH_NAME == 'master') {
                sh """
                    bash .jenkins/scripts/notify-slack.sh 'success' \
                    '${env.BUILD_URL}' '${env.GIT_COMMIT_MSG}'
                """
            }
        }
    }
    
    failure {
        // Always notify on failure
        sh """
            bash .jenkins/scripts/notify-slack.sh 'failure' \
            '${env.BUILD_URL}' '${env.GIT_COMMIT_MSG}'
        """
    }
}
```

---

## Input Step Contract (Manual Approval)

### Production Deployment Approval

The `Deploy to Production` stage MUST include a manual approval step:

```groovy
stage('Deploy to Production') {
    when {
        branch 'master'
    }
    steps {
        input(
            message: 'Deploy to Production?',
            ok: 'Deploy',
            submitter: 'jenkins-admin,devops-team',
            parameters: [
                choice(
                    name: 'CONFIRM',
                    choices: ['No', 'Yes'],
                    description: 'Are you sure you want to deploy to production?'
                )
            ]
        )
        
        script {
            if (params.CONFIRM != 'Yes') {
                error('Deployment cancelled by user')
            }
        }
        
        // Proceed with deployment...
    }
}
```

**Contract Requirements**:
- **Submitters**: Only users in `jenkins-admin` or `devops-team` groups can approve
- **Timeout**: Approval request expires after 24 hours (default)
- **Audit**: Jenkins logs who approved and when

---

## Webhook Configuration Contract

### GitHub Webhook Requirements

Jenkins job MUST be configured to trigger on GitHub webhook events:

**Webhook URL**: `https://jenkins.myshop.com/github-webhook/`

**Events to trigger**:
- `push` - Triggers main pipeline on push to `master`
- `pull_request` - Triggers PR pipeline on PR open/update

**Branch Filter**:
- Main pipeline: `*/master`
- PR pipeline: `PR-*` (Jenkins plugin auto-creates PR jobs)

**Jenkins Job Configuration**:
```groovy
// In job configuration or Jenkinsfile
properties([
    pipelineTriggers([
        githubPush(), // Trigger on GitHub push webhook
    ]),
    buildDiscarder(logRotator(numToKeepStr: '50')),
])
```

---

## Artifact Archiving Contract

### Required Artifacts to Archive

| Artifact | Path | Retention Days | Purpose |
|----------|------|----------------|---------|
| Test results (XML) | `var/log/phpunit/*.xml` | 30 | Jenkins test result visualization |
| Playwright report | `tests/E2E/playwright-report/` | 30 | E2E test debugging |
| Build logs | Auto-archived by Jenkins | 50 builds | Debugging failed builds |
| Coverage report | `var/coverage/` | 30 | Code coverage tracking |

**Archiving Example**:
```groovy
archiveArtifacts(
    artifacts: 'var/log/phpunit/*.xml,var/coverage/**',
    allowEmptyArchive: true,
    fingerprint: true
)
```

---

## Stage Timeout Contract

To prevent hanging builds, each stage MUST have a timeout:

| Stage | Timeout | Reason |
|-------|---------|--------|
| Build | 10 minutes | Composer install should not exceed 10 minutes |
| Test | 15 minutes | Test suite should complete within 15 minutes |
| Deploy to Test | 10 minutes | Ansible deployment should complete within 10 minutes |
| E2E Tests | 20 minutes | Playwright tests can be slow, allow 20 minutes |
| Deploy to Production | 10 minutes | Same as test deployment |
| Smoke Tests | 5 minutes | Health checks are fast |

**Implementation**:
```groovy
stage('Build') {
    options {
        timeout(time: 10, unit: 'MINUTES')
    }
    steps {
        // build steps
    }
}
```

---

## Error Handling Contract

### Required Error Handling Patterns

1. **Catch and Report**: Catch errors, log them, notify Slack, then fail
2. **Retry Logic**: Retry flaky operations (network calls) up to 3 times
3. **Continue on Specific Errors**: Some quality checks can warn instead of fail

**Example**:
```groovy
stage('Static Analysis') {
    steps {
        script {
            try {
                sh 'vendor/bin/phpstan analyse src'
            } catch (Exception e) {
                // Log the error
                echo "PHPStan failed: ${e.message}"
                // Notify but don't fail build (can be made stricter)
                unstable(message: 'Code quality checks failed')
            }
        }
    }
}
```

---

## Jenkins Shared Library Contract (Future)

When pipeline grows complex, extract common logic to shared library:

**Library Structure**:
```
vars/
├── buildSymfony.groovy       # Reusable build logic
├── deployAnsistrano.groovy   # Reusable deployment logic
├── runPlaywright.groovy      # Reusable E2E test logic
└── notifySlack.groovy        # Reusable notification logic
```

**Usage in Jenkinsfile**:
```groovy
@Library('myshop-pipeline-lib') _

pipeline {
    agent any
    stages {
        stage('Build') {
            steps {
                buildSymfony() // Calls shared library function
            }
        }
    }
}
```

---

## Compliance & Audit Requirements

### Audit Trail

Jenkins MUST log the following for every production deployment:
- Who triggered the deployment (user or webhook)
- What commit SHA was deployed
- When the deployment occurred
- Who approved the production deployment
- Whether deployment succeeded or failed

**Access via Jenkins API**:
```bash
# Get build information
curl -u user:token https://jenkins.myshop.com/job/myshop-pipeline/123/api/json
```

---

## Validation Checklist

Before deploying changes to Jenkinsfile:

- [ ] All required stages are present and in correct order
- [ ] Environment variables are properly defined
- [ ] Credentials are referenced by ID (never hardcoded)
- [ ] Post actions include test result publishing
- [ ] Production deployment has manual approval gate
- [ ] Timeouts are set for all stages
- [ ] Slack notifications configured for failures
- [ ] Parallel stages are independent
- [ ] Agent labels are correctly specified

---

## Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2026-02-13 | Initial contract definition |

---

## See Also

- [ansible-inventory-schema.md](ansible-inventory-schema.md) - Ansible inventory contract
- [deployment-api.yaml](deployment-api.yaml) - Health check API contract
- [../research.md](../research.md) - Technology research and decisions
