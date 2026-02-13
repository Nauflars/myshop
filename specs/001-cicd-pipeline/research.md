# Research: CI/CD Pipeline Implementation (Local Docker Mode)

**Date**: 2026-02-13  
**Feature**: Complete CI/CD workflow with Jenkins, Ansistrano, and Playwright

## Overview

This document consolidates research findings for implementing a production-grade CI/CD pipeline for a Symfony 7 PHP application. The pipeline automates the complete workflow from feature branch push through automated testing to zero-downtime deployment. **IMPORTANT: All operations occur locally using Docker Compose - no external servers or SSH connections required.**

---

## Infrastructure: Local Docker-Based Development

### Decision: Fully Local CI/CD with Docker Compose

**Rationale**:
- **Zero external dependencies**: Entire pipeline runs on developer's machine
- **Cost-effective**: No cloud infrastructure costs for development/testing
- **Fast feedback**: No network latency, instant deployments
- **Reproducible**: Identical environments across all developers
- **Safe experimentation**: Break things without affecting real servers
- **Offline-capable**: Works without internet after initial setup

### Docker Compose Architecture

```yaml
# docker-compose.ci.yml
version: '3.8'

services:
  # Jenkins orchestration
  jenkins:
    build:
      context: .jenkins
      dockerfile: Dockerfile.jenkins
    container_name: myshop-jenkins
    ports:
      - "8080:8080"      # Jenkins UI
      - "50000:50000"    # Jenkins agent
    volumes:
      - jenkins_home:/var/jenkins_home
      - /var/run/docker.sock:/var/run/docker.sock  # Docker socket access
      - .:/workspace     # Mount workspace for builds
    environment:
      - JENKINS_OPTS=--prefix=/jenkins
    networks:
      - cicd_network
  
  # Test environment (simulates staging)
  myshop-test:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: myshop-test
    ports:
      - "8081:80"       # Test environment
    volumes:
      - test_releases:/var/www/myshop/releases
      - test_shared:/var/www/myshop/shared
    environment:
      - APP_ENV=test
      - DATABASE_URL=mysql://root:testpass@mysql-test:3306/myshop_test
    depends_on:
      - mysql-test
      - redis-test
    networks:
      - cicd_network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3
  
  # Production environment (simulates production)
  myshop-prod:
    build:
      context: .
      dockerfile: Dockerfile
    container_name: myshop-prod
    ports:
      - "8082:80"       # Production environment
    volumes:
      - prod_releases:/var/www/myshop/releases
      - prod_shared:/var/www/myshop/shared
    environment:
      - APP_ENV=prod
      - DATABASE_URL=mysql://root:prodpass@mysql-prod:3306/myshop_prod
    depends_on:
      - mysql-prod
      - redis-prod
    networks:
      - cicd_network
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost/health"]
      interval: 30s
      timeout: 10s
      retries: 3
  
  # Test database
  mysql-test:
    image: mysql:8.0
    container_name: myshop-mysql-test
    environment:
      MYSQL_ROOT_PASSWORD: testpass
      MYSQL_DATABASE: myshop_test
    volumes:
      - mysql_test_data:/var/lib/mysql
    networks:
      - cicd_network
  
  # Production database
  mysql-prod:
    image: mysql:8.0
    container_name: myshop-mysql-prod
    environment:
      MYSQL_ROOT_PASSWORD: prodpass
      MYSQL_DATABASE: myshop_prod
    volumes:
      - mysql_prod_data:/var/lib/mysql
    networks:
      - cicd_network
  
  # Test Redis
  redis-test:
    image: redis:7-alpine
    container_name: myshop-redis-test
    networks:
      - cicd_network
  
  # Production Redis
  redis-prod:
    image: redis:7-alpine
    container_name: myshop-redis-prod
    networks:
      - cicd_network

networks:
  cicd_network:
    driver: bridge

volumes:
  jenkins_home:
  test_releases:
  test_shared:
  prod_releases:
  prod_shared:
  mysql_test_data:
  mysql_prod_data:
```

**Key Benefits**:
- **Isolated environments**: Separate containers for test and prod with own databases
- **Port mapping**: Access via localhost:8081 (test), localhost:8082 (prod)
- **Health checks**: Docker monitors container health for zero-downtime deploys
- **Persistent volumes**: Releases and shared data survive container restarts
- **Network isolation**: All services communicate via Docker network

### Custom Jenkins Docker Image

```dockerfile
# .jenkins/Dockerfile.jenkins
FROM jenkins/jenkins:lts

USER root

# Install Docker CLI for Docker-in-Docker operations
RUN apt-get update && apt-get install -y \
    apt-transport-https \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    && curl -fsSL https://download.docker.com/linux/debian/gpg | gpg --dearmor -o /usr/share/keyrings/docker-archive-keyring.gpg \
    && echo "deb [arch=amd64 signed-by=/usr/share/keyrings/docker-archive-keyring.gpg] https://download.docker.com/linux/debian $(lsb_release -cs) stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null \
    && apt-get update \
    && apt-get install -y docker-ce-cli

# Install Ansible
RUN apt-get install -y python3-pip \
    && pip3 install ansible ansible-lint

# Install Node.js for Playwright
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs

# Install Composer for PHP dependencies
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Cleanup
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

USER jenkins

# Pre-install Jenkins plugins
RUN jenkins-plugin-cli --plugins \
    git \
    workflow-aggregator \
    docker-workflow \
    ansible \
    blueocean \
    github \
    slack
```

**Rationale**:
- Jenkins needs Docker CLI to manage containers via socket
- Ansible for running deployment playbooks
- Node.js for Playwright E2E tests
- Composer for PHP dependency management
- Pre-installed plugins for Git, Docker, Ansible integration

---

## 1. Jenkins Pipeline Architecture for PHP/Symfony Projects

### Decision: Declarative Pipeline with Shared Library

**Rationale**:
- **Declarative syntax** provides better structure and error handling than scripted pipelines
- **Shared libraries** enable reuse of common logic across multiple Jenkinsfiles
- **Stage-based approach** clearly separates concerns (build, test, deploy)
- **Built-in retry and timeout** mechanisms for resilient pipelines

**Implementation Pattern**:
```groovy
// Jenkinsfile for main branch (merge to master triggers deployment)
pipeline {
    agent { label 'php83' }
    
    environment {
        COMPOSER_HOME = "${WORKSPACE}/.composer"
        APP_ENV = 'prod'
    }
    
    stages {
        stage('Build') {
            steps {
                sh 'composer install --no-dev --optimize-autoloader'
                sh 'npm ci && npm run build'
            }
        }
        
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
                        sh 'vendor/bin/php-cs-fixer fix --dry-run --diff'
                    }
                }
            }
        }
        
        stage('Deploy to Test') {
            steps {
                ansiblePlaybook(
                    playbook: 'deployment/deploy.yml',
                    inventory: 'deployment/inventories/test/hosts',
                    extras: '-e "branch=${GIT_COMMIT}"'
                )
            }
        }
        
        stage('E2E Tests on Test') {
            steps {
                sh 'cd tests/E2E && npm test -- --config=test.config.ts'
            }
        }
        
        stage('Deploy to Production') {
            when {
                branch 'master'
            }
            input {
                message "Deploy to production?"
                ok "Deploy"
            }
            steps {
                ansiblePlaybook(
                    playbook: 'deployment/deploy.yml',
                    inventory: 'deployment/inventories/production/hosts',
                    extras: '-e "branch=${GIT_COMMIT}"'
                )
            }
        }
        
        stage('Smoke Tests') {
            steps {
                sh 'bash scripts/deploy/smoke-test.sh production'
            }
        }
    }
    
    post {
        always {
            junit 'var/log/phpunit/*.xml'
            publishHTML([
                reportName: 'Playwright Report',
                reportDir: 'tests/E2E/playwright-report'
            ])
        }
        failure {
            sh 'bash .jenkins/scripts/notify-slack.sh "Pipeline failed"'
        }
    }
}
```

**Alternatives Considered**:
- **Scripted Pipeline**: Rejected due to lack of structure and harder to maintain
- **GitLab CI/Travis**: Rejected as Jenkins is already established in infrastructure
- **GitHub Actions**: Rejected due to requirement for on-premise Jenkins

**Best Practices Applied**:
- **Parallel test execution** to reduce pipeline time
- **Workspace cleanup** to avoid disk space issues
- **Artifact archiving** for debugging failed deployments
- **Branch-based conditionals** for production deployment
- **Manual approval gates** for production to prevent accidental deployments

---

## 2. Ansistrano: Zero-Downtime Deployment for Symfony (Local Docker Mode)

### Decision: Ansistrano with Docker Container Targets

**Rationale**:
- **Zero-downtime deployments**: Symlink switching ensures seamless transitions within containers
- **Rollback capability**: Previous releases maintained in Docker volumes for quick rollback
- **Idempotent**: Can rerun deployment safely without side effects
- **Symfony-optimized**: Handles cache warming, migrations, and asset compilation
- **Local execution**: Uses `ansible_connection=local` or `docker exec` to deploy into containers
- **No SSH required**: Direct execution within container filesystem

**Docker-Optimized Implementation**:

```yaml
# deployment/local-deploy.yml
---
- name: Deploy Symfony Application to Local Docker Container
  hosts: all
  connection: local  # Run Ansible locally, not via SSH
  vars:
    ansistrano_deploy_to: "/var/www/myshop"
    ansistrano_deploy_via: "copy"  # Copy from workspace (mounted volume)
    ansistrano_keep_releases: 3    # Fewer releases for local dev
    
    # Shared paths (persistent across deployments in Docker volumes)
    ansistrano_shared_paths:
      - var/log
      - var/sessions
      - public/uploads
    
    ansistrano_shared_files:
      - .env.local
    
    # Symfony-specific tasks
    ansistrano_before_symlink_tasks_file: "{{ playbook_dir }}/hooks/before-symlink.yml"
    ansistrano_after_symlink_tasks_file: "{{ playbook_dir }}/hooks/after-symlink.yml"
    
    # Docker execution settings
    target_container: "{{ container_name | default('myshop-test') }}"
    
  roles:
    - { role: ansistrano.deploy }

# deployment/hooks/before-symlink.yml
---
- name: Install Composer dependencies
  composer:
    command: install
    working_dir: "{{ ansistrano_release_path.stdout }}"
    no_dev: yes
    optimize_autoloader: yes

- name: Run database migrations
  command: php bin/console doctrine:migrations:migrate --no-interaction
  args:
    chdir: "{{ ansistrano_release_path.stdout }}"
  environment:
    APP_ENV: "{{ app_env }}"

- name: Warm up cache
  command: php bin/console cache:warmup --env={{ app_env }}
  args:
    chdir: "{{ ansistrano_release_path.stdout }}"

- name: Install assets
  command: php bin/console assets:install public --symlink --relative
  args:
    chdir: "{{ ansistrano_release_path.stdout }}"

# deployment/hooks/after-symlink.yml
---
- name: Reload PHP-FPM
  service:
    name: php8.3-fpm
    state: reloaded

- name: Clear OPcache
  command: php bin/console cache:pool:clear cache.global_clearer
  args:
    chdir: "{{ ansistrano_deploy_to }}/current"
```

**Directory Structure Created by Ansistrano**:
```
/var/www/myshop/
├── current -> releases/20260213140530/  # Symlink to active release
├── releases/
│   ├── 20260213140530/                  # Latest release
│   ├── 20260213130245/                  # Previous release
│   └── 20260213120130/                  # Older release
├── shared/                               # Persistent across deployments
│   ├── var/log/
│   ├── var/sessions/
│   ├── public/uploads/
│   └── .env.local
└── repo/                                 # Git repository cache
```

**Rollback Playbook**:
```yaml
# deployment/rollback.yml
---
- name: Rollback Symfony Application
  hosts: all
  vars:
    ansistrano_deploy_to: "/var/www/myshop"
  roles:
    - { role: ansistrano.rollback }
  post_tasks:
    - name: Reload PHP-FPM after rollback
      service:
        name: php8.3-fpm
        state: reloaded
```

**Alternatives Considered**:
- **Deployer (PHP)**: Rejected as team prefers Ansible ecosystem
- **Git-based deployment**: Rejected due to lack of zero-downtime semantics
- **Blue-Green deployment**: Overkill for local development, requires double resources

**Local Docker Inventory Configuration**:

```ini
# deployment/inventories/local-test/hosts
[test]
localhost ansible_connection=local container_name=myshop-test

[test:vars]
ansible_python_interpreter=/usr/bin/python3
app_env=test
deploy_via=copy
ansistrano_deploy_to=/var/www/myshop
# Deployment executes commands inside the container
execute_in_container=true

# deployment/inventories/local-production/hosts
[production]
localhost ansible_connection=local container_name=myshop-prod

[production:vars]
ansible_python_interpreter=/usr/bin/python3
app_env=prod
deploy_via=copy
ansistrano_deploy_to=/var/www/myshop
execute_in_container=true
```

**Docker Execution Module** (helper for executing commands in containers):

```yaml
# deployment/library/docker_container_command.py
# Custom Ansible module for executing commands inside Docker containers
#!/usr/bin/python3
from ansible.module_utils.basic import AnsibleModule
import subprocess

def run_command_in_container(container_name, command, chdir=None):
    """Execute command inside Docker container"""
    cmd = ["docker", "exec"]
    if chdir:
        cmd.extend(["-w", chdir])
    cmd.append(container_name)
    cmd.extend(command.split() if isinstance(command, str) else command)
    
    result = subprocess.run(cmd, capture_output=True, text=True)
    return result.returncode, result.stdout, result.stderr

def main():
    module = AnsibleModule(
        argument_spec=dict(
            container=dict(required=True, type='str'),
            command=dict(required=True, type='str'),
            chdir=dict(required=False, type='str', default=None)
        )
    )
    
    rc, stdout, stderr = run_command_in_container(
        module.params['container'],
        module.params['command'],
        module.params['chdir']
    )
    
    if rc == 0:
        module.exit_json(changed=True, stdout=stdout, stderr=stderr)
    else:
        module.fail_json(msg=f"Command failed: {stderr}", stdout=stdout, stderr=stderr, rc=rc)

if __name__ == '__main__':
    main()
```

**Updated Deployment Hooks for Docker**:

```yaml
# deployment/hooks/before-symlink-docker.yml
---
- name: Install Composer dependencies in container
  docker_container_command:
    container: "{{ target_container }}"
    command: "composer install --no-dev --optimize-autoloader --working-dir={{ ansistrano_release_path.stdout }}"

- name: Run database migrations in container
  docker_container_command:
    container: "{{ target_container }}"
    command: "php bin/console doctrine:migrations:migrate --no-interaction"
    chdir: "{{ ansistrano_release_path.stdout }}"
  environment:
    APP_ENV: "{{ app_env }}"

- name: Warm up cache in container
  docker_container_command:
    container: "{{ target_container }}"
    command: "php bin/console cache:warmup --env={{ app_env }}"
    chdir: "{{ ansistrano_release_path.stdout }}"

- name: Install assets in container
  docker_container_command:
    container: "{{ target_container }}"
    command: "php bin/console assets:install public --symlink --relative"
    chdir: "{{ ansistrano_release_path.stdout }}"
```

**Benefits of Local Docker Deployment**:
- **No SSH keys**: Eliminates key management complexity
- **No network latency**: Instant deployment, no remote connection delays
- **Reproducible**: Same Docker containers across all developers
- **Isolated**: Each container has independent releases and shared directories
- **Debuggable**: Easy to inspect container state with `docker exec`
- **Offline-capable**: Works without internet after initial setup

---
- **Capistrano**: Rejected due to Ruby dependency
- **Docker Swarm/Kubernetes**: Rejected as overkill for current scale
- **Manual rsync scripts**: Rejected due to lack of rollback capability

**Best Practices Applied**:
- **Shared directories** for logs, uploads, and configuration
- **Database migrations** run before symlink switch
- **Cache warming** to prevent cold-start performance issues
- **Service reload** instead of restart for zero downtime
- **Keep 5 releases** for quick rollback capability
- **Atomic symlink switch** ensures no partial deployments

---

## 3. Playwright: End-to-End Testing Strategy (Local Docker Targets)

### Decision: Playwright with Page Object Model Pattern

**Rationale**:
- **Cross-browser testing**: Chromium, Firefox, WebKit support
- **Auto-wait mechanisms**: Reduces flaky tests compared to Selenium
- **Fast execution**: Parallel test execution out of the box
- **Modern web support**: Better handling of SPAs and async operations
- **Developer experience**: TypeScript support, excellent debugging tools
- **Local Docker testing**: Tests run against containers at localhost:8081 (test) and localhost:8082 (prod)

**Implementation Pattern for Local Docker**:

```typescript
// tests/E2E/playwright.config.ts
import { defineConfig, devices } from '@playwright/test';

export default defineConfig({
  testDir: './tests',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: process.env.CI ? 4 : undefined,
  reporter: [
    ['html'],
    ['junit', { outputFile: 'results.xml' }]
  ],
  
  use: {
    // Target local Docker containers (test: 8081, prod: 8082)
    baseURL: process.env.BASE_URL || 'http://localhost:8081',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
    timeout: 10000,  // Shorter timeout for local tests (no network latency)
  },

  projects: [
    {
      name: 'test-environment',
      use: { 
        ...devices['Desktop Chrome'],
        baseURL: 'http://localhost:8081',  // myshop-test container
      },
    },
    {
      name: 'prod-environment',
      use: { 
        ...devices['Desktop Chrome'],
        baseURL: 'http://localhost:8082',  // myshop-prod container
      },
    },
    {
      name: 'mobile',
      use: { 
        ...devices['iPhone 13'],
        baseURL: 'http://localhost:8081',
      },
    },
  ],
});

// tests/E2E/tests/checkout.spec.ts
import { test, expect } from '@playwright/test';
import { CheckoutPage } from '../fixtures/pages/CheckoutPage';
import { CartPage } from '../fixtures/pages/CartPage';

test.describe('Checkout Flow', () => {
  test.beforeEach(async ({ page }) => {
    // Login and add items to cart
    await page.goto('/login');
    await page.fill('[name="email"]', 'test@example.com');
    await page.fill('[name="password"]', 'testpass123');
    await page.click('button[type="submit"]');
    await page.waitForURL('/');
  });

  test('should complete purchase successfully', async ({ page }) => {
    const cart = new CartPage(page);
    const checkout = new CheckoutPage(page);
    
    // Add product to cart
    await page.goto('/products/1');
    await page.click('[data-test="add-to-cart"]');
    await expect(page.locator('[data-test="cart-count"]')).toHaveText('1');
    
    // Navigate to checkout
    await cart.goto();
    await cart.proceedToCheckout();
    
    // Fill shipping information
    await checkout.fillShippingInfo({
      address: '123 Test St',
      city: 'Test City',
      postalCode: '12345',
      country: 'US'
    });
    
    // Complete payment
    await checkout.selectPaymentMethod('credit-card');
    await checkout.fillCardInfo({
      number: '4242424242424242',
      expiry: '12/27',
      cvc: '123'
    });
    
    await checkout.submitOrder();
    
    // Verify success
    await expect(page.locator('[data-test="order-confirmation"]'))
      .toBeVisible();
    await expect(page.locator('[data-test="order-number"]'))
      .toContainText(/ORD-\d+/);
  });

  test('should handle payment failure gracefully', async ({ page }) => {
    const checkout = new CheckoutPage(page);
    
    // Use card number that triggers decline
    await checkout.fillCardInfo({
      number: '4000000000000002',
      expiry: '12/27',
      cvc: '123'
    });
    
    await checkout.submitOrder();
    
    // Verify error message
    await expect(page.locator('[data-test="payment-error"]'))
      .toContainText('Payment declined');
  });
});

// tests/E2E/fixtures/pages/CheckoutPage.ts (Page Object Model)
import { Page, Locator } from '@playwright/test';

export class CheckoutPage {
  readonly page: Page;
  readonly addressInput: Locator;
  readonly cityInput: Locator;
  readonly submitButton: Locator;

  constructor(page: Page) {
    this.page = page;
    this.addressInput = page.locator('[name="shipping_address"]');
    this.cityInput = page.locator('[name="shipping_city"]');
    this.submitButton = page.locator('[data-test="submit-order"]');
  }

  async fillShippingInfo(info: ShippingInfo) {
    await this.addressInput.fill(info.address);
    await this.cityInput.fill(info.city);
    // ... more fields
  }

  async submitOrder() {
    await this.submitButton.click();
    await this.page.waitForLoadState('networkidle');
  }
}
```

**Jenkins Integration**:
```groovy
// .jenkins/stages/e2e.groovy
def runE2ETests(String environment) {
    dir('tests/E2E') {
        sh 'npm ci'
        sh "BASE_URL=https://${environment}.myshop.com npm test"
        
        publishHTML([
            allowMissing: false,
            alwaysLinkToLastBuild: true,
            keepAll: true,
            reportDir: 'playwright-report',
            reportFiles: 'index.html',
            reportName: "Playwright ${environment} Report"
        ])
    }
}
```

**Alternatives Considered**:
- **Selenium**: Rejected due to flakiness and slower execution
- **Cypress**: Rejected due to limited cross-browser support and iframe limitations
- **Puppeteer**: Rejected due to Chromium-only support
- **Manual testing**: Rejected due to slow feedback loop and human error

**Best Practices Applied**:
- **Page Object Model**: Encapsulates page logic for reusability
- **Test data fixtures**: Separates test data from test logic
- **Parallel execution**: Reduces total test time
- **Automatic retries**: Handles transient failures in CI
- **Visual regression**: Screenshots on failure for debugging
- **Mobile testing**: Validates responsive design

**Test Coverage Strategy**:
- **Critical user journeys**: Login, search, add to cart, checkout
- **Payment flows**: Successful payment, declined cards, 3D Secure
- **Error scenarios**: Network failures, validation errors
- **Cross-browser**: Chrome (primary), Firefox, Safari (WebKit)
- **Mobile**: Key flows on mobile viewport

---

## 4. Secrets Management with Ansible Vault (Local Docker Mode)

### Decision: Ansible Vault for Sensitive Configuration

**Rationale**:
- **Encrypted at rest**: Secrets stored encrypted in Git
- **Transparent decryption**: Ansible decrypts automatically during playbook run
- **Version controlled**: Encrypted secrets tracked with code
- **Access control**: Vault password managed securely (Jenkins credential store)
- **Environment-specific**: Separate vaults for test and production containers

**Implementation Pattern**:

```bash
# Create encrypted variables file for local test environment
ansible-vault create deployment/inventories/local-test/group_vars/all/vault.yml

# Content structure (decrypted view):
# deployment/inventories/local-test/group_vars/all/vault.yml
---
vault_database_password: "test_db_password_123"
vault_openai_api_key: "sk-test-..."
vault_redis_password: "test_redis_456"
vault_rabbitmq_password: "test_rabbit_789"
vault_app_secret: "test_symfony_secret_abc"

# deployment/inventories/local-production/group_vars/all/vault.yml
---
vault_database_password: "prod_db_password_123"
vault_openai_api_key: "sk-prod-..."
vault_redis_password: "prod_redis_456"
vault_rabbitmq_password: "prod_rabbit_789"
vault_app_secret: "prod_symfony_secret_abc"

# Reference in playbook (deployment/inventories/local-test/group_vars/all/main.yml)
---
database_password: "{{ vault_database_password }}"
openai_api_key: "{{ vault_openai_api_key }}"
redis_password: "{{ vault_redis_password }}"
rabbitmq_password: "{{ vault_rabbitmq_password }}"
app_secret: "{{ vault_app_secret }}"

# These get injected into Docker containers as environment variables
container_env:
  DATABASE_URL: "mysql://root:{{ database_password }}@mysql-{{ app_env }}:3306/myshop_{{ app_env }}"
  REDIS_URL: "redis://:{{ redis_password }}@redis-{{ app_env }}:6379"
  APP_SECRET: "{{ app_secret }}"
  OPENAI_API_KEY: "{{ openai_api_key }}"
```

**Jenkins Integration**:
```groovy
// In Jenkinsfile, use credentials binding
environment {
    ANSIBLE_VAULT_PASSWORD_FILE = credentials('ansible-vault-password')
}

steps {
    ansiblePlaybook(
        playbook: 'deployment/deploy.yml',
        inventory: 'deployment/inventories/production/hosts',
        vaultCredentialsId: 'ansible-vault-password'
    )
}
```

**Best Practices Applied**:
- **Separate vault files** for each environment
- **Never commit vault password** to repository
- **Use vault IDs** for multiple vault passwords
- **Rotate secrets** regularly
- **Audit trail** via Git history of encrypted files

---

## 5. Git Workflow Integration

### Decision: Git Flow with PR-based Deployment

**Rationale**:
- **Feature branches** isolate development work
- **Pull requests** enforce code review before merge
- **Automated PR checks** ensure quality before review
- **Master branch protected** with required status checks
- **Deployment triggered** only after merge to master

**Workflow**:

```
1. Developer creates feature branch: feature/add-payment-gateway
2. Push triggers Jenkins PR pipeline (Jenkinsfile.pr):
   - Runs unit tests
   - Runs integration tests  
   - Runs static analysis
   - Reports status to PR
3. Developer creates PR to master
4. Code review + all checks must pass
5. Merge to master triggers main pipeline (Jenkinsfile):
   - Runs full test suite
   - Deploys to test environment
   - Runs E2E tests on test
   - Manual approval for production
   - Deploys to production
   - Runs smoke tests
```

**PR Validation Pipeline**:
```groovy
// .jenkins/Jenkinsfile.pr
pipeline {
    agent { label 'php83' }
    
    stages {
        stage('Validate PR') {
            steps {
                sh 'composer validate'
                sh 'composer install'
            }
        }
        
        stage('Test') {
            parallel {
                stage('Unit') {
                    steps { sh 'php bin/phpunit --testsuite=unit' }
                }
                stage('Integration') {
                    steps { sh 'php bin/phpunit --testsuite=integration' }
                }
                stage('Static Analysis') {
                    steps {
                        sh 'vendor/bin/phpstan analyse'
                        sh 'vendor/bin/php-cs-fixer fix --dry-run'
                    }
                }
            }
        }
    }
    
    post {
        always {
            // Report status back to GitHub PR
            step([
                $class: 'GitHubCommitStatusSetter',
                contextSource: [$class: 'ManuallyEnteredCommitContextSource', context: 'CI/Jenkins']
            ])
        }
    }
}
```

**Branch Protection Rules**:
- Require PR reviews (min 1 approver)
- Require status checks to pass before merge
- Include administrators in restrictions
- Require branches to be up to date before merge

---

## 6. Performance & Optimization Considerations

### Pipeline Performance Targets

**Current Baseline** (estimated):
- Unit tests: ~2 minutes
- Integration tests: ~5 minutes
- Build & deploy: ~3 minutes
- E2E tests: ~10 minutes
- **Total pipeline time**: ~20 minutes

**Optimizations**:
- **Parallel test execution**: Run unit, integration, quality checks in parallel
- **Docker layer caching**: Cache Composer dependencies between builds
- **Distributed E2E**: Run Playwright tests across multiple containers
- **Incremental static analysis**: Only analyze changed files in PRs
- **Artifact reuse**: Build once, deploy artifact to test and prod

**Target**:
- **PR validation**: <5 minutes (fast feedback)
- **Full deployment pipeline**: <15 minutes (acceptable for production deployments)

---

## 7. Monitoring & Observability

### Decision: Pipeline Monitoring with Slack Integration

**Implementation**:
```bash
# .jenkins/scripts/notify-slack.sh
#!/bin/bash
STATUS="$1"
BUILD_URL="$2"
COMMIT_MSG="$3"

curl -X POST "$SLACK_WEBHOOK_URL" \
  -H 'Content-Type: application/json' \
  -d "{
    \"text\": \"Deployment ${STATUS}\",
    \"attachments\": [{
      \"color\": \"$([ \"$STATUS\" = \"success\" ] && echo \"good\" || echo \"danger\")\",
      \"fields\": [
        {\"title\": \"Project\", \"value\": \"MyShop\", \"short\": true},
        {\"title\": \"Branch\", \"value\": \"${GIT_BRANCH}\", \"short\": true},
        {\"title\": \"Commit\", \"value\": \"${COMMIT_MSG}\"},
        {\"title\": \"Build\", \"value\": \"<${BUILD_URL}|View Build>\"}
      ]
    }]
  }"
```

**Health Checks Post-Deployment**:
```bash
# scripts/deploy/smoke-test.sh
#!/bin/bash
ENVIRONMENT="$1"
BASE_URL="https://${ENVIRONMENT}.myshop.com"

# Check HTTP response
RESPONSE=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/health")
if [ "$RESPONSE" != "200" ]; then
    echo "Health check failed: HTTP $RESPONSE"
    exit 1
fi

# Check critical services
curl -sf "$BASE_URL/api/health/database" || exit 1
curl -sf "$BASE_URL/api/health/redis" || exit 1
curl -sf "$BASE_URL/api/health/rabbitmq" || exit 1

echo "All health checks passed!"
```

---

## 8. Disaster Recovery & Rollback

### Decision: Automated Rollback with Manual Trigger

**Rollback Procedure**:
```groovy
// Jenkins job for manual rollback
pipeline {
    agent any
    
    parameters {
        choice(name: 'ENVIRONMENT', choices: ['test', 'production'])
        string(name: 'RELEASE_VERSION', defaultValue: 'previous')
    }
    
    stages {
        stage('Confirm Rollback') {
            steps {
                input message: "Rollback ${params.ENVIRONMENT} to ${params.RELEASE_VERSION}?"
            }
        }
        
        stage('Execute Rollback') {
            steps {
                ansiblePlaybook(
                    playbook: 'deployment/rollback.yml',
                    inventory: "deployment/inventories/${params.ENVIRONMENT}/hosts"
                )
            }
        }
        
        stage('Verify') {
            steps {
                sh "bash scripts/deploy/smoke-test.sh ${params.ENVIRONMENT}"
            }
        }
    }
}
```

**Rollback Decision Criteria**:
- Smoke tests fail after deployment
- Critical bug detected in production
- Performance degradation >50%
- Database migration failure (requires manual intervention)

---

## Summary of Technology Decisions

| Component | Choice | Rationale |
|-----------|--------|-----------|
| **CI Orchestration** | Jenkins | Existing infrastructure, flexible plugin ecosystem |
| **Deployment Tool** | Ansistrano (Ansible) | Zero-downtime, idempotent, proven for Symfony |
| **E2E Testing** | Playwright | Modern, fast, cross-browser, excellent DX |
| **Secrets Management** | Ansible Vault | Native integration, Git-trackable, encrypted at rest |
| **Git Workflow** | Feature branches + PRs | Enforces review, automated quality gates |
| **Notification** | Slack webhooks | Real-time alerts, team visibility |
| **Monitoring** | Health checks + Smoke tests | Post-deploy verification, quick failure detection |

---

## Open Questions & Future Enhancements

### Resolved Questions
- ✅ **How to handle database migrations?** → Run in Ansistrano `before_symlink` hook
- ✅ **Where to store secrets?** → Ansible Vault with encrypted Git storage
- ✅ **How to ensure zero downtime?** → Ansistrano symlink switching + PHP-FPM reload
- ✅ **What if E2E tests fail on test?** → Block production deployment, require fix

### Future Enhancements (Out of Scope)
- **Blue-Green Deployment**: Full traffic switching between two identical environments
- **Canary Deployments**: Gradual rollout to subset of users
- **Feature Flags**: Toggle features without deployment
- **Container Orchestration**: Kubernetes for horizontal scaling
- **Performance Testing**: Load testing in CI pipeline

---

## Implementation Checklist

- [ ] Set up Jenkins master/agent with PHP 8.3
- [ ] Install Ansible and Ansistrano on Jenkins
- [ ] Create Jenkins credentials for Ansible Vault password
- [ ] Configure Git webhooks for Jenkins triggers
- [ ] Set up test and production server inventories
- [ ] Create Ansible playbooks (deploy, rollback)
- [ ] Write Jenkinsfile for main and PR pipelines
- [ ] Implement Playwright E2E test suite
- [ ] Configure Slack notifications
- [ ] Create rollback Jenkins job
- [ ] Document deployment process in quickstart.md
- [ ] Train team on CI/CD workflow

**Next Phase**: Generate data models and API contracts for pipeline state tracking and Jenkins integration.
