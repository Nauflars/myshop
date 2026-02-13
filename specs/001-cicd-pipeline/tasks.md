# Tasks: CI/CD Pipeline with Jenkins & Ansistrano (Local Docker Mode)

**Input**: Design documents from `/specs/001-cicd-pipeline/`  
**Feature Branch**: `001-cicd-pipeline`  
**Created**: 2026-02-13

## Overview

This task list implements a complete local Docker-based CI/CD pipeline from PR validation through production deployment. All operations occur locally - no external servers or SSH required. Tasks are organized by user story to enable independent implementation and testing.

**Infrastructure Mode**: Fully local execution using Docker Compose
- **Jenkins**: Docker container on port 8080
- **Test Environment**: Docker container `myshop-test` on port 8081  
- **Production Environment**: Docker container `myshop-prod` on port 8082
- **Deployment**: Ansible with `ansible_connection=local` or Docker exec

**Tests**: Not explicitly requested in specification, so test tasks focus on health checks and smoke tests rather than unit/integration test creation.

**User Story Priority Order**:
- P1 (MVP): Automated PR Validation - Immediate value, blocks bad code
- P2: Automated Test Deployment - Enables continuous delivery to local container
- P3: E2E Testing - Catches integration bugs on localhost:8081
- P4: Production Deployment - Completes full pipeline to localhost:8082
- P5: Rollback Capability - Safety net and disaster recovery

**MVP Definition**: Complete Phase 1 (Setup) + Phase 2 (Foundation) + Phase 3 (US1: PR Validation) = First 64 tasks

## Format: `[ID] [P?] [Story] Description`

- **Checkbox**: `- [ ]` (required for all tasks)
- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: User story label (US1-US5)
- **File paths**: Exact paths from plan.md structure

---

## Phase 1: Setup (Docker Infrastructure & Directories)

**Purpose**: Initialize Docker-based CI/CD infrastructure and project structure

- [ ] T001 Create `docker-compose.ci.yml` in repository root defining Jenkins, test, and prod containers with port mappings
- [ ] T002 Create `.jenkins/` directory structure (.jenkins/stages/, .jenkins/scripts/, .jenkins/configs/)
- [ ] T003 Create `.jenkins/Dockerfile.jenkins` for custom Jenkins image with Docker CLI, Ansible, Node.js, and Composer
- [ ] T004 Create `deployment/` directory structure (inventories/local-test/, inventories/local-production/, hooks/, library/)
- [ ] T005 Create `tests/E2E/` directory structure (tests/, fixtures/, configs/, playwright-report/)
- [ ] T006 Create `scripts/ci/` and `scripts/deploy/` directories for helper scripts
- [ ] T007 [P] Create `.github/` directory for PR templates and workflow integration

**Checkpoint**: Directory structure and Docker infrastructure files ready

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

### Docker & Jenkins Foundation

- [ ] T008 Configure Jenkins service in docker-compose.ci.yml with volume mounts and Docker socket access
- [ ] T009 Configure myshop-test service in docker-compose.ci.yml with port 8081 and volume mounts
- [ ] T010 Configure myshop-prod service in docker-compose.ci.yml with port 8082 and volume mounts
- [ ] T011 Configure MySQL, Redis, MongoDB services for test environment in docker-compose.ci.yml
- [ ] T012 Configure MySQL, Redis, MongoDB services for production environment in docker-compose.ci.yml
- [ ] T013 [P] Build custom Jenkins Docker image with required tools per .jenkins/Dockerfile.jenkins
- [ ] T014 [P] Create docker-compose network configuration for service communication

### Ansible Configuration

- [ ] T015 Create Ansible configuration file in deployment/ansible.cfg with local connection defaults
- [ ] T016 Create Ansible role requirements in deployment/roles/requirements.yml (ansistrano.deploy, ansistrano.rollback)
- [ ] T017 [P] Create local test inventory in deployment/inventories/local-test/hosts with ansible_connection=local
- [ ] T018 [P] Create local production inventory in deployment/inventories/local-production/hosts with ansible_connection=local
- [ ] T019 [P] Create test environment variables in deployment/inventories/local-test/group_vars/all.yml
- [ ] T020 [P] Create production environment variables in deployment/inventories/local-production/group_vars/all.yml
- [ ] T021 [P] Create encrypted vault for test secrets in deployment/inventories/local-test/group_vars/all/vault.yml
- [ ] T022 [P] Create encrypted vault for production secrets in deployment/inventories/local-production/group_vars/all/vault.yml

### Ansible Deployment Playbooks

- [ ] T023 Create main Ansistrano deployment playbook in deployment/deploy-local.yml with Docker target support
- [ ] T024 Create Ansistrano rollback playbook in deployment/rollback-local.yml
- [ ] T025 Create before-symlink hook in deployment/hooks/before-symlink.yml (composer install, migrations, cache warmup, assets)
- [ ] T026 Create after-symlink hook in deployment/hooks/after-symlink.yml (PHP-FPM reload, cache clear)
- [ ] T027 [P] Create custom Ansible module in deployment/library/docker_container_command.py for Docker exec operations

### Health Check Endpoints

- [ ] T028 Create HealthController in src/Controller/Api/HealthController.php
- [ ] T029 [P] Implement /health endpoint (overall health check) in HealthController
- [ ] T030 [P] Implement /api/health/database endpoint (MySQL + MongoDB connectivity) in HealthController
- [ ] T031 [P] Implement /api/health/redis endpoint (cache connectivity) in HealthController
- [ ] T032 [P] Implement /api/health/rabbitmq endpoint (message queue connectivity) in HealthController
- [ ] T033 [P] Implement /api/health/disk endpoint (disk usage check) in HealthController
- [ ] T034 Configure health check routes in config/routes.yaml

### Helper Scripts

- [ ] T035 [P] Create smoke test script in scripts/deploy/smoke-test.sh for post-deployment health verification
- [ ] T036 [P] Create Docker health check script in scripts/deploy/docker-health.sh for container verification
- [ ] T037 [P] Create pre-deployment check script in scripts/deploy/pre-deploy.sh (disk space, container status)
- [ ] T038 [P] Create post-deployment script in scripts/deploy/post-deploy.sh (cache clearing, log rotation)

### Playwright E2E Configuration

- [ ] T039 Create package.json in tests/E2E/ with Playwright and dependencies
- [ ] T040 Create main Playwright config in tests/E2E/playwright.config.ts with browser and reporter settings
- [ ] T041 [P] Create test environment config in tests/E2E/configs/local-test.config.ts (baseURL: http://localhost:8081)
- [ ] T042 [P] Create production environment config in tests/E2E/configs/local-prod.config.ts (baseURL: http://localhost:8082)

### GitHub Integration

- [ ] T043 [P] Create pull request template in .github/pull_request_template.md with checklist

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - Automated PR Validation (Priority: P1) 🎯 MVP

**Goal**: When developers push to PR, tests run automatically and block merge if failing

**Independent Test**: Create feature branch, open PR, verify Jenkins runs tests and reports status to GitHub

### Jenkins PR Pipeline

- [ ] T044 [US1] Create Jenkinsfile.pr in .jenkins/Jenkinsfile.pr with PR validation pipeline definition
- [ ] T045 [US1] Add agent configuration in Jenkinsfile.pr (Docker agent or Jenkins node selector)
- [ ] T046 [US1] Add Validate stage in Jenkinsfile.pr (composer validate, syntax check, install dependencies)
- [ ] T047 [US1] Add parallel Test stage in Jenkinsfile.pr with substages for unit, integration, and static analysis
- [ ] T048 [US1] Configure PHPUnit execution in Test stage (php bin/phpunit --testsuite=unit --log-junit results.xml)
- [ ] T049 [US1] Configure PHPStan execution in Test stage (vendor/bin/phpstan analyse --level=8 --error-format=junit)
- [ ] T050 [US1] Configure PHP-CS-Fixer execution in Test stage (vendor/bin/php-cs-fixer fix --dry-run --diff)
- [ ] T051 [US1] Add post-build actions in Jenkinsfile.pr (publish JUnit XML, archive artifacts, cleanup workspace)
- [ ] T052 [US1] Add stage timeout configuration in Jenkinsfile.pr (Validate: 10min, Test: 15min)
- [ ] T053 [US1] Add failure notification in Jenkinsfile.pr post section (Slack notification on failure)

### Jenkins Job Configuration

- [ ] T054 [US1] Create Jenkins multibranch pipeline job for repository
- [ ] T055 [US1] Configure branch discovery strategy (discover PRs from origin, exclude closed PRs)
- [ ] T056 [US1] Configure build triggers (GitHub webhook for pull_request events)
- [ ] T057 [US1] Add GitHub SSH credential to Jenkins credential store (ID: github-ssh-key)
- [ ] T058 [US1] Configure Jenkins GitHub plugin to report build status to PRs
- [ ] T059 [US1] Add Ansible Vault credential to Jenkins (ID: ansible-vault-password, for future deployment stages)

### GitHub Protection Rules

- [ ] T060 [US1] Configure branch protection on master branch requiring PR validation status check
- [ ] T061 [US1] Configure GitHub webhook to trigger Jenkins on pull_request events (opened, synchronize, reopened)

### Testing Infrastructure

- [ ] T062 [US1] Create script in scripts/ci/run-tests.sh to execute test suites with proper exit codes
- [ ] T063 [US1] Create script in scripts/ci/check-migrations.sh to verify migration status before tests
- [ ] T064 [US1] Configure PHPUnit to generate JUnit XML reports in var/log/phpunit/results.xml

**Checkpoint**: At this point, PRs automatically run tests and merge is blocked on failures - **MVP COMPLETE**

---

## Phase 4: User Story 2 - Automated Test Environment Deployment (Priority: P2)

**Goal**: After merge to master, automatically deploy to local Docker test container (localhost:8081)

**Independent Test**: Merge PR to master, verify automatic deployment to myshop-test container, check http://localhost:8081/health

### Main Jenkins Pipeline

- [ ] T065 [US2] Create main Jenkinsfile in .jenkins/Jenkinsfile for master branch deployments
- [ ] T066 [US2] Add Build stage in Jenkinsfile (composer install, asset compilation, build artifact creation)
- [ ] T067 [US2] Add Test stage in Jenkinsfile (reuse PR validation tests for master branch)
- [ ] T068 [US2] Create deploy test stage script in .jenkins/stages/deploy.groovy for deployment logic
- [ ] T069 [US2] Add Deploy to Test stage in Jenkinsfile calling Ansible playbook with test inventory
- [ ] T070 [US2] Configure Ansible playbook execution in Deploy to Test stage (ansible-playbook deployment/deploy-local.yml -i deployment/inventories/local-test/hosts)
- [ ] T071 [US2] Add container health check in Deploy to Test stage (verify myshop-test container is running before deployment)
- [ ] T072 [US2] Add disk space check in Deploy to Test stage (abort if >80% usage on Docker volumes)

### Deployment Hooks Implementation

- [ ] T073 [US2] Implement composer install task in deployment/hooks/before-symlink.yml using docker_container_command module
- [ ] T074 [US2] Implement database migration task in deployment/hooks/before-symlink.yml with docker exec
- [ ] T075 [US2] Implement cache warmup task in deployment/hooks/before-symlink.yml
- [ ] T076 [US2] Implement asset installation task in deployment/hooks/before-symlink.yml
- [ ] T077 [US2] Implement PHP-FPM reload task in deployment/hooks/after-symlink.yml using docker exec
- [ ] T078 [US2] Implement OPcache clear task in deployment/hooks/after-symlink.yml

### Post-Deployment Validation

- [ ] T079 [US2] Add health check verification in Jenkinsfile after Deploy to Test (curl http://localhost:8081/health)
- [ ] T080 [US2] Implement smoke test execution in Jenkinsfile (bash scripts/deploy/smoke-test.sh test)
- [ ] T081 [US2] Add Slack notification on deployment success in Jenkinsfile post section
- [ ] T082 [US2] Add Slack notification on deployment failure in Jenkinsfile post section with rollback recommendation

### Build Artifacts

- [ ] T083 [US2] Create build assets script in scripts/ci/build-assets.sh (compile JavaScript, minify CSS, optimize images)
- [ ] T084 [US2] Create vendor archive script in scripts/ci/archive-vendor.sh for faster deployments (cache vendor/ directory)
- [ ] T085 [US2] Configure artifact retention in Jenkinsfile (keep last 10 builds, 30 days retention)

**Checkpoint**: Test container deployment fully automated, accessible at http://localhost:8081

---

## Phase 5: User Story 3 - E2E Testing on Test Environment (Priority: P3)

**Goal**: After test deployment, automatically run Playwright E2E tests against localhost:8081

**Independent Test**: Deploy to test container, verify Playwright tests run automatically, check test reports in Jenkins

### Playwright Test Suite

- [ ] T086 [US3] Create authentication test spec in tests/E2E/tests/auth.spec.ts (login, logout, session management)
- [ ] T087 [US3] Create cart test spec in tests/E2E/tests/cart.spec.ts (add to cart, update quantity, remove items)
- [ ] T088 [US3] Create checkout test spec in tests/E2E/tests/checkout.spec.ts (complete purchase flow end-to-end)
- [ ] T089 [US3] Create search test spec in tests/E2E/tests/search.spec.ts (semantic search, filters, product details)
- [ ] T090 [P] [US3] Create LoginPage page object in tests/E2E/fixtures/pages/LoginPage.ts
- [ ] T091 [P] [US3] Create CartPage page object in tests/E2E/fixtures/pages/CartPage.ts
- [ ] T092 [P] [US3] Create CheckoutPage page object in tests/E2E/fixtures/pages/CheckoutPage.ts
- [ ] T093 [P] [US3] Create SearchPage page object in tests/E2E/fixtures/pages/SearchPage.ts

### Test Data Fixtures

- [ ] T094 [P] [US3] Create test user fixtures in tests/E2E/fixtures/users.json
- [ ] T095 [P] [US3] Create test product fixtures in tests/E2E/fixtures/products.json
- [ ] T096 [P] [US3] Create test payment fixtures in tests/E2E/fixtures/payments.json

### Jenkins E2E Integration

- [ ] T097 [US3] Create E2E test stage script in .jenkins/stages/e2e.groovy
- [ ] T098 [US3] Add E2E Tests stage in Jenkinsfile after Deploy to Test stage
- [ ] T099 [US3] Configure Playwright installation in E2E stage (cd tests/E2E && npm install && npx playwright install)
- [ ] T100 [US3] Configure Playwright execution in E2E stage targeting localhost:8081 (BASE_URL=http://localhost:8081 npm test)
- [ ] T101 [US3] Add parallel browser execution in E2E stage (Chromium, Firefox, WebKit)
- [ ] T102 [US3] Configure test retry logic (retry failed tests up to 2 times to handle flakiness)
- [ ] T103 [US3] Add Playwright HTML report publishing in Jenkinsfile post section
- [ ] T104 [US3] Add screenshot artifact archiving on test failure
- [ ] T105 [US3] Add E2E test result condition to block production deployment on failure

**Checkpoint**: E2E tests run automatically after test deployment, reports available in Jenkins

---

## Phase 6: User Story 4 - Production Deployment with Manual Approval (Priority: P4)

**Goal**: After E2E tests pass, require manual approval before deploying to localhost:8082

**Independent Test**: Complete test deployment with passing E2E tests, approve production deployment, verify http://localhost:8082

### Production Deployment Pipeline

- [ ] T106 [US4] Add manual input stage in Jenkinsfile for production approval (input message: "Deploy to Production?")
- [ ] T107 [US4] Configure approval timeout in Jenkinsfile (24 hours, then abort)
- [ ] T108 [US4] Configure authorized approvers in Jenkinsfile (DevOps team members only)
- [ ] T109 [US4] Add Deploy to Production stage in Jenkinsfile after manual approval
- [ ] T110 [US4] Configure Ansible playbook execution for production (ansible-playbook deployment/deploy-local.yml -i deployment/inventories/local-production/hosts)
- [ ] T111 [US4] Add container health check in Deploy to Production stage (verify myshop-prod container is running)
- [ ] T112 [US4] Add migration verification in Deploy to Production stage (check pending migrations before deployment)

### Production-Specific Hooks

- [ ] T113 [US4] Add production-specific environment variables in deployment/inventories/local-production/group_vars/all.yml
- [ ] T114 [US4] Configure production database credentials in Ansible Vault
- [ ] T115 [US4] Configure production API keys and secrets in Ansible Vault (OpenAI, payment gateway, etc.)

### Post-Production Validation

- [ ] T116 [US4] Add Smoke Tests stage in Jenkinsfile after Deploy to Production
- [ ] T117 [US4] Implement comprehensive smoke test in scripts/deploy/smoke-test.sh production (test all health endpoints)
- [ ] T118 [US4] Add database connectivity verification in smoke tests (curl http://localhost:8082/api/health/database)
- [ ] T119 [US4] Add Redis connectivity verification in smoke tests (curl http://localhost:8082/api/health/redis)
- [ ] T120 [US4] Add MongoDB connectivity verification in smoke tests (curl http://localhost:8082/api/health/mongodb)
- [ ] T121 [US4] Add RabbitMQ connectivity verification in smoke tests (curl http://localhost:8082/api/health/rabbitmq)
- [ ] T122 [US4] Add critical endpoint verification in smoke tests (test key API endpoints)

### Notifications & Audit Trail

- [ ] T123 [US4] Add production deployment success notification to Slack with deployment details (commit SHA, deployer, timestamp)
- [ ] T124 [US4] Add production deployment failure notification to Slack with rollback instructions
- [ ] T125 [US4] Create deployment audit log entry in logs/deployments.log (who deployed, when, what version)
- [ ] T126 [US4] Create deployment tag in Git repository (tag deployed commit as "prod-v1.2.3-20260213")

**Checkpoint**: Production container deployment requires approval, fully automated with smoke tests

---

## Phase 7: User Story 5 - Rollback Capability (Priority: P5)

**Goal**: Enable quick rollback to previous release when production issues occur

**Independent Test**: Deploy to test container, trigger rollback job, verify previous release is restored

### Rollback Pipeline

- [ ] T127 [US5] Create separate Rollback Jenkins job (myshop-rollback) using deployment/rollback-local.yml
- [ ] T128 [US5] Add environment selection parameter in rollback job (test or production container)
- [ ] T129 [US5] Add optional release version parameter in rollback job (default: previous release)
- [ ] T130 [US5] Add rollback reason parameter (required text input for audit trail)
- [ ] T131 [US5] Add manual confirmation step in rollback job ("Are you sure you want to rollback?")
- [ ] T132 [US5] Configure Ansible rollback playbook execution (ansible-playbook deployment/rollback-local.yml)
- [ ] T133 [US5] Add container health verification before rollback (ensure target container is running)
- [ ] T134 [US5] Add release existence check (verify target release exists in container's releases directory)

### Rollback Validation

- [ ] T135 [US5] Implement rollback script in scripts/deploy/rollback-verify.sh (check symlink switched correctly)
- [ ] T136 [US5] Add health check execution after rollback in rollback job
- [ ] T137 [US5] Add smoke test execution after rollback (verify all services operational)
- [ ] T138 [US5] Add automatic cache clearing after rollback (clear OPcache, Redis, application cache)

### Rollback Notifications & Logging

- [ ] T139 [US5] Add rollback success notification to Slack with rollback details (user, reason, restored version, timestamp)
- [ ] T140 [US5] Add rollback failure notification to Slack with troubleshooting instructions
- [ ] T141 [US5] Create rollback audit log entry in logs/rollbacks.log (who, when, why, what version)
- [ ] T142 [US5] Add rollback metric tracking (increment rollback counter, track time between deployment and rollback)

### Rollback Testing

- [ ] T143 [US5] Create rollback test scenario documentation in deployment/docs/rollback-procedure.md
- [ ] T144 [US5] Add rollback dry-run capability (--check flag to simulate rollback without executing)
- [ ] T145 [US5] Create rollback monitoring dashboard widget (display recent rollbacks and success rate)

**Checkpoint**: Rollback fully functional, tested, and documented

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Final improvements, documentation, and operational readiness

### Documentation

- [ ] T146 [P] Create comprehensive README in .jenkins/README.md explaining pipeline structure and stages
- [ ] T147 [P] Create troubleshooting guide in deployment/docs/troubleshooting.md for common deployment issues
- [ ] T148 [P] Create runbook in deployment/docs/runbook.md for operational procedures (deploy, rollback, emergency procedures)
- [ ] T149 [P] Update main project README.md with CI/CD pipeline documentation and links
- [ ] T150 [P] Create docker-compose.ci.yml usage guide explaining how to start/stop CI/CD infrastructure

### Monitoring & Observability

- [ ] T151 [P] Add Jenkins pipeline metrics collection (build duration, success rate, deployment frequency)
- [ ] T152 [P] Create deployment dashboard in Grafana or Jenkins Blue Ocean views
- [ ] T153 [P] Add pipeline failure alerting rules (notify on repeated failures, long-running builds)
- [ ] T154 [P] Configure log aggregation for deployment logs (centralize logs from Jenkins, Ansible, containers)

### Security & Secrets

- [ ] T155 [P] Audit Ansible Vault files to ensure all secrets are encrypted
- [ ] T156 [P] Rotate Ansible Vault password and update Jenkins credential
- [ ] T157 [P] Add secrets scanning in PR validation (detect accidentally committed secrets)
- [ ] T158 [P] Configure credential expiration reminders (database passwords, API keys)

### Performance Optimization

- [ ] T159 [P] Implement composer cache in Jenkins to speed up dependency installation
- [ ] T160 [P] Implement npm cache in Jenkins for faster Playwright installation
- [ ] T161 [P] Optimize Docker image build with layer caching strategy
- [ ] T162 [P] Implement parallel stage execution where possible (reduce total pipeline time)

### Environment Parity

- [ ] T163 [P] Verify test and production containers have identical PHP versions and extensions
- [ ] T164 [P] Verify test and production containers have identical MySQL, Redis, MongoDB versions
- [ ] T165 [P] Create script to verify environment parity in scripts/deploy/verify-parity.sh
- [ ] T166 [P] Document environment differences (if any justified differences exist)

### Disaster Recovery

- [ ] T167 [P] Create backup procedure for Docker volumes in deployment/docs/backup-procedure.md
- [ ] T168 [P] Test full pipeline restoration from backup (verify Jenkins jobs, configs, and secrets can be restored)
- [ ] T169 [P] Document Jenkins configuration backup strategy (backup Jenkins home directory)
- [ ] T170 [P] Create automated Docker volume backup script in scripts/deploy/backup-volumes.sh

**Final Checkpoint**: Pipeline is production-ready, documented, monitored, and secure

---

## Dependencies & Execution Order

### Critical Path (Must complete in order)

1. **Phase 1 (Setup)** → **Phase 2 (Foundation)** → **Phase 3 (US1: PR Validation)**
2. **Phase 3 (US1)** → **Phase 4 (US2: Test Deployment)** → **Phase 5 (US3: E2E Tests)**
3. **Phase 5 (US3)** → **Phase 6 (US4: Prod Deployment)**
4. **Phase 6 (US4)** → **Phase 7 (US5: Rollback)**
5. **Phase 8 (Polish)** can be executed in parallel with any user story after Phase 2

### Parallel Execution Opportunities

**After Phase 2 completes, these can run in parallel**:

- US1 (T044-T064): PR validation pipeline
- Health endpoint implementation (T028-T034) - already in Phase 2
- Helper scripts (T035-T038) - already in Phase 2
- Playwright configuration (T039-T042) - already in Phase 2

**After US2 (Test Deployment) completes**:

- US3 (T086-T105): E2E test suite creation
- US5 (T127-T145): Rollback pipeline setup (independent of US4)

**Polish tasks (Phase 8) can run anytime after Phase 2**:
- Documentation (T146-T150)
- Monitoring (T151-T154)
- Security audit (T155-T158)
- Performance optimization (T159-T162)
- Environment verification (T163-T166)
- Disaster recovery (T167-T170)

### Dependency Graph

```
Setup (Phase 1)
    ↓
Foundation (Phase 2)
    ↓
    ├─→ US1: PR Validation (Phase 3) ─→ [MVP COMPLETE]
    │       ↓
    ├─→ US2: Test Deployment (Phase 4)
    │       ↓
    ├─→ US3: E2E Tests (Phase 5)
    │       ↓
    └─→ US4: Production Deployment (Phase 6)
            ↓
        US5: Rollback (Phase 7)
            ↓
        Polish (Phase 8)
```

---

## Task Summary

**Total Tasks**: 170 implementation tasks

**Breakdown by Phase**:
- Phase 1 (Setup): 7 tasks
- Phase 2 (Foundation): 36 tasks  
- Phase 3 (US1 - PR Validation): 21 tasks - **MVP = 64 tasks total**
- Phase 4 (US2 - Test Deployment): 21 tasks
- Phase 5 (US3 - E2E Testing): 20 tasks
- Phase 6 (US4 - Production Deployment): 21 tasks
- Phase 7 (US5 - Rollback): 19 tasks
- Phase 8 (Polish): 25 tasks

**Parallelizable Tasks**: 72 tasks marked with [P] can run in parallel once dependencies are met

**MVP Scope** (Phases 1-3): 64 tasks
- Focus: Get PR validation working first
- Delivers: Automated testing and merge protection
- Timeline Estimate: 1-2 weeks for experienced team

**Full Implementation Estimate**: 4-6 weeks for complete pipeline with all 5 user stories

---

## Implementation Strategy

### Week 1: MVP (PR Validation)
- Days 1-2: Setup Docker infrastructure (Phase 1)
- Days 3-4: Build foundation (Phase 2)
- Day 5: Implement PR validation pipeline (Phase 3)
- **Deliverable**: Working PR validation that blocks bad code

### Week 2: Deployment Automation
- Days 1-2: Test environment deployment (Phase 4)
- Days 3-5: E2E testing framework (Phase 5)
- **Deliverable**: Automated deployments to test container with E2E coverage

### Week 3: Production Pipeline
- Days 1-3: Production deployment with approval (Phase 6)
- Days 4-5: Rollback capability (Phase 7)
- **Deliverable**: Full production deployment pipeline

### Week 4: Polish & Hardening
- Days 1-5: Documentation, monitoring, security, optimization (Phase 8)
- **Deliverable**: Production-ready, documented, monitored pipeline

### Incremental Delivery Approach

1. **Ship MVP first** (PR validation) - immediate value
2. **Add test deployment** - enables continuous delivery
3. **Add E2E tests** - catches integration bugs
4. **Add production deployment** - completes pipeline
5. **Add rollback** - safety net
6. **Polish** - operational excellence

This approach ensures the team gets value quickly and can provide feedback to guide remaining implementation.
