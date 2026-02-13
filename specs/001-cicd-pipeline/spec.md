# Feature Specification: CI/CD Pipeline with Jenkins & Ansistrano

**Feature Branch**: `001-cicd-pipeline`  
**Created**: 2026-02-13  
**Status**: Draft  
**Input**: User description: "Complete CI/CD workflow from push to production deployment, including PR validation, automated testing, deployment orchestration with Jenkins and Ansistrano, and E2E testing with Playwright"

## Infrastructure Context (Local Development Mode)

**Deployment Model**: Fully local execution using Docker Compose - no external servers required

**Environment Simulation**:
- **Test Environment**: Simulated via Docker Compose service `myshop-test` (separate container with own database)
- **Production Environment**: Simulated via Docker Compose service `myshop-prod` (separate container with own database)
- **Jenkins**: Runs in Docker container `myshop-jenkins` with Docker-in-Docker or Docker socket access
- **All services**: Communicate via Docker internal networking (no SSH required)

**Deployment Mechanism**:
- **Ansible Connection**: Uses `ansible_connection=local` for local container deployments OR Docker exec commands
- **Zero-Downtime**: Simulated using container health checks and rolling updates
- **Rollback**: Docker container tags and volume snapshots for instant rollback
- **No Remote SSH**: All operations occur within Docker network using local connections

**Benefits**:
- ✅ **Portable**: Entire pipeline runs on any machine with Docker
- ✅ **Fast feedback**: No network latency, instant deployments
- ✅ **Cost-effective**: No cloud infrastructure required for development/testing
- ✅ **Reproducible**: Identical environments across all developer machines
- ✅ **Safe experimentation**: Break things locally without affecting real servers

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Automated PR Validation (Priority: P1) 🎯 MVP

When a developer pushes code to a feature branch with an open pull request, the CI pipeline automatically runs tests and code quality checks. The PR cannot be merged until all checks pass, ensuring code quality and preventing bugs from reaching the main branch.

**Why this priority**: This is the foundation of the entire CI/CD pipeline. Without automated PR validation, we can't enforce quality gates and the rest of the pipeline has no value. This is the minimum viable product - a working PR validation pipeline.

**Independent Test**: Can be fully tested by creating a feature branch, opening a PR, and verifying that Jenkins runs tests automatically. Delivers immediate value by catching bugs before code review.

**Acceptance Scenarios**:

1. **Given** a developer has pushed code to a feature branch, **When** they create a pull request to master, **Then** Jenkins automatically triggers a PR validation pipeline that runs unit tests, integration tests, and static analysis
2. **Given** a PR validation pipeline is running, **When** all tests and checks pass, **Then** the PR status shows green checkmarks and merge is allowed
3. **Given** a PR validation pipeline is running, **When** any test or check fails, **Then** the PR status shows red X and merge is blocked
4. **Given** a developer pushes new commits to an existing PR, **When** the push completes, **Then** the PR validation pipeline re-runs automatically
5. **Given** PR validation completes, **When** viewing the PR in GitHub, **Then** detailed test results and coverage reports are accessible via links

---

### User Story 2 - Automated Test Environment Deployment (Priority: P2)

After code is merged to the master branch and all tests pass, the application automatically deploys to a local Docker container simulating the test/staging environment. Deployment uses Ansistrano with local Ansible connection for zero-downtime updates. This allows QA and stakeholders to test new features locally in an environment that mirrors production.

**Why this priority**: Automated test deployment is critical for continuous delivery. Manual deployments are error-prone and slow. This story builds on the PR validation foundation and enables faster iteration cycles. Local Docker-based deployment provides instant feedback without cloud infrastructure costs.

**Independent Test**: Can be tested by merging a PR to master and verifying the application automatically deploys to the `myshop-test` Docker container. The test URL (http://localhost:8081) should show the latest changes. Delivers value by eliminating manual deployment steps and providing instant local feedback.

**Acceptance Scenarios**:

1. **Given** a PR has been merged to master and passed all tests, **When** the merge completes, **Then** Jenkins automatically triggers deployment to the `myshop-test` Docker container
2. **Given** deployment to test is in progress, **When** Ansistrano runs the deployment playbook with local connection, **Then** database migrations execute in the test container, cache warms up, and assets compile without errors
3. **Given** deployment to test completes successfully, **When** the deployment finishes, **Then** the test container's current symlink points to the new release and health checks at http://localhost:8081/health return 200 OK
4. **Given** deployment to test fails, **When** any deployment step fails, **Then** the pipeline stops, previous container state remains active, and team receives Slack notification
5. **Given** multiple releases exist in the test container, **When** viewing the releases directory via docker exec, **Then** the last 3 releases are retained for potential rollback

---

### User Story 3 - End-to-End Testing on Test Environment (Priority: P3)

After successful deployment to the local test Docker container, Playwright automatically runs end-to-end tests that verify critical user journeys work correctly in the deployed application. Playwright runs directly on the host machine and connects to the test container via localhost port mapping (http://localhost:8081). This catches integration issues and UI bugs before production deployment.

**Why this priority**: E2E tests provide confidence that the entire application works as expected from a user's perspective. Without this, we might deploy broken features to production despite passing unit tests. Local execution means fast feedback without network overhead.

**Independent Test**: Can be tested by triggering a test deployment and verifying Playwright tests run automatically against http://localhost:8081. Test reports should be accessible in Jenkins. Delivers value by catching UI and integration bugs with instant local feedback.

**Acceptance Scenarios**:

1. **Given** deployment to test container completed successfully, **When** the deployment finishes, **Then** Jenkins automatically triggers the Playwright E2E test suite targeting http://localhost:8081
2. **Given** E2E tests are running, **When** Playwright executes test scenarios against the local test container, **Then** tests run in parallel across multiple browsers (Chromium, Firefox, WebKit)
3. **Given** E2E tests complete, **When** all tests pass, **Then** the pipeline proceeds to production (local prod container) deployment approval stage
4. **Given** E2E tests fail on test container, **When** any test fails, **Then** production container deployment is blocked and test failure report with screenshots is published
5. **Given** E2E tests have failures, **When** viewing the Jenkins job, **Then** HTML test report with screenshots and trace files is accessible for debugging

---

### User Story 4 - Production Deployment with Manual Approval (Priority: P4)

After E2E tests pass on the test container, the pipeline pauses and requests manual approval before deploying to the local production-simulated Docker container (`myshop-prod`). An authorized team member reviews test results and approves the deployment, which then executes using Ansistrano with local connection for zero-downtime deployment.

**Why this priority**: Manual approval for production is a critical safety gate. Automated deployments are great, but we need human oversight before releasing to customers. This story completes the full deployment pipeline. Local production container simulates real production deployment workflow.

**Independent Test**: Can be tested by completing a full test deployment with passing E2E tests, then approving the production deployment request. The `myshop-prod` container at http://localhost:8082 should then deploy automatically. Delivers value by enabling safe production release workflows locally.

**Acceptance Scenarios**:

1. **Given** E2E tests passed on test container, **When** the test stage completes, **Then** Jenkins displays a manual approval prompt asking "Deploy to Production?"
2. **Given** a production deployment approval is pending, **When** an authorized user (DevOps team) clicks "Deploy", **Then** Jenkins triggers Ansistrano deployment to the `myshop-prod` Docker container using local connection
3. **Given** production deployment is in progress, **When** Ansistrano executes via docker exec or local connection, **Then** database migrations run in prod container, cache warms up, symlink switches atomically, and PHP-FPM reloads gracefully
4. **Given** production deployment completes, **When** the deployment finishes, **Then** smoke tests verify health checks return 200 OK on http://localhost:8082/health for all services (database, Redis, MongoDB, RabbitMQ)
5. **Given** production deployment succeeds, **When** the pipeline completes, **Then** Slack notification with deployment details (commit SHA, deployer, timestamp) is sent to the team
6. **Given** production deployment approval is pending, **When** 24 hours pass without approval, **Then** the approval request expires and pipeline is aborted

---

### User Story 5 - Rollback Capability (Priority: P5)

When a production container deployment causes issues (failed health checks, critical bugs, performance problems), an authorized team member can trigger a rollback job in Jenkins that uses Ansistrano to instantly restore the previous working release in the `myshop-prod` container without downtime.

**Why this priority**: Rollback is essential disaster recovery. Despite all our testing, production issues can occur. The ability to quickly rollback minimizes customer impact and gives the team confidence to deploy frequently. Local container rollback provides instant recovery.

**Independent Test**: Can be tested by deploying a "bad" release to test container, then triggering rollback. The previous release should be restored and health checks at http://localhost:8081/health should pass. Delivers value by providing safety net for deployments.

**Acceptance Scenarios**:

1. **Given** a production container deployment has completed, **When** health checks fail or critical bugs are discovered, **Then** a team member can trigger the "myshop-rollback" Jenkins job
2. **Given** rollback job is triggered, **When** the job runs, **Then** user must confirm the rollback with environment selection (test/production container) and optionally specify target release
3. **Given** rollback is executed, **When** Ansistrano rollback playbook runs via local connection to container, **Then** the current symlink switches to the previous release, PHP-FPM reloads in container, and cache clears
4. **Given** rollback completes, **When** the previous release is restored, **Then** smoke tests verify all health checks at http://localhost:8082/health return 200 OK
5. **Given** rollback completes successfully, **When** the job finishes, **Then** Slack notification with rollback details (reason, user, timestamp, restored version) is sent to the team
6. **Given** no previous releases exist in the container, **When** rollback is attempted, **Then** the job fails with clear error message "No previous releases available for rollback"

---

### Edge Cases

- **What happens when deployment fails mid-migration?** Ansistrano aborts before symlink switch in container, previous release remains active, manual intervention required for migration rollback via docker exec
- **What happens when multiple developers merge to master simultaneously?** Jenkins queues builds sequentially, each deployment to containers waits for previous to complete
- **What happens when test container is down during deployment?** Health checks fail, deployment marked as failed, Slack alert sent, previous container state remains active
- **What happens when GitHub webhook fails to trigger Jenkins?** Jenkins container has fallback poll SCM every 5 minutes, manual trigger always available via Jenkins UI
- **What happens when E2E tests are flaky?** Jenkins configured to retry failed tests up to 2 times, persistent failures block deployment and require investigation
- **What happens when disk space runs out on host during deployment?** Ansistrano's pre-task checks detect insufficient disk space (>80% usage via Docker volume inspection) and abort deployment before starting
- **What happens when production approval expires?** Pipeline is aborted, can re-run from beginning by triggering new build
- **What happens when Ansible Vault password is wrong?** Deployment fails immediately with "Decryption failed" error, Jenkins credential needs update
- **What happens when Docker containers aren't running?** Jenkins job checks container health before deployment, fails fast with clear error if containers are stopped
- **What happens when Docker network is misconfigured?** Health check connections fail early, deployment aborts before making changes

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST automatically trigger PR validation pipeline when code is pushed to any feature branch with an open pull request
- **FR-002**: System MUST block PR merge when any test or quality check fails in the PR validation pipeline
- **FR-003**: System MUST automatically deploy to local test Docker container within 5 minutes of successful merge to master branch
- **FR-004**: System MUST execute database migrations in target container before switching to new release during deployment
- **FR-005**: System MUST maintain zero downtime during local production container deployments (users experience no service interruption via rolling updates)
- **FR-006**: System MUST run Playwright E2E tests automatically against test container (http://localhost:8081) after deployment completes
- **FR-007**: System MUST require manual approval from authorized users before deploying to local production container
- **FR-008**: System MUST send Slack notifications for deployment success, failure, and approval requests
- **FR-009**: System MUST run smoke tests (health checks) after production container deployment to verify all services are operational
- **FR-010**: System MUST support rollback to previous release within 2 minutes of triggering rollback job on local containers
- **FR-011**: System MUST keep minimum 5 previous releases in production container for rollback capability
- **FR-012**: System MUST encrypt sensitive configuration (database passwords, API keys) using Ansible Vault
- **FR-013**: System MUST log all deployments with deployer identity, timestamp, commit SHA, and deployment status
- **FR-014**: System MUST abort deployment if disk usage exceeds 80% on Docker host
- **FR-015**: System MUST retry flaky E2E tests up to 2 times before marking as failed
- **FR-016**: System MUST use ansible_connection=local or Docker exec for all deploy operations (no SSH required)
- **FR-017**: System MUST verify Docker containers are healthy before attempting deployment
- **FR-018**: Jenkins container MUST have access to Docker socket or Docker-in-Docker for managing deployments
- **FR-019**: Test and production containers MUST be accessible via port mapping (8081 for test, 8082 for prod)
- **FR-020**: All deployment operations MUST work offline after initial Docker image pull (no internet dependency)

### Key Entities

- **Pipeline Execution**: Represents a single CI/CD pipeline run, tracking branch, commit, status, duration, and stages
- **Stage**: Individual pipeline stage (build, test, deploy) with start time, end time, status, and logs
- **Deployment**: Represents a deployment operation to an environment (test/production) with version, deployer, and health check results
- **Test Result**: Captures test execution results including passed/failed counts, coverage, and failure details
- **Rollback**: Records rollback operations with reason, target version, and post-rollback health status
- **Health Check**: Post-deployment verification of application and service health
- **Artifact**: Build outputs (vendor packages, compiled assets, test reports) produced by pipeline stages

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: PR validation pipeline completes within 5 minutes of push (unit tests + integration tests + static analysis)
- **SC-002**: Test environment deployment completes within 10 minutes from merge to master
- **SC-003**: E2E test suite completes within 20 minutes with test reports published to Jenkins
- **SC-004**: Production deployment completes within 10 minutes after manual approval
- **SC-005**: Rollback operation completes within 2 minutes and restores application to working state
- **SC-006**: Zero production downtime during deployments (100% uptime measured by health checks)
- **SC-007**: 100% of production deployments have audit trail (who deployed, when, what commit)
- **SC-008**: All deployment failures trigger immediate Slack notifications within 30 seconds
- **SC-009**: Health checks verify all critical services (database, Redis, MongoDB, RabbitMQ) are operational post-deployment
- **SC-010**: Deployment success rate exceeds 95% (no false failures due to infrastructure issues)
- **SC-011**: Team can deploy to production at least 5 times per day when needed (demonstrating deployment speed and confidence)
- **SC-012**: Failed deployments automatically preserve previous working release (0% of failed deployments leave application in broken state)
