# Tasks: Pull Request Quality Gates Enforcement

**Feature**: 004-quality-gates-enforcement  
**Input**: [spec.md](spec.md), [plan.md](plan.md)  
**Status**: 29/70 Complete (41%) - Phase 1 & 2 ✅, Phase 3 partial ✅, Phase 6 & 7 partial ✅

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: User story this task belongs to (US1, US2, US3, US4)
- File paths relative to repository root

---

## Phase 1: Foundation (✅ COMPLETE)

**Purpose**: Core infrastructure and documentation

- [x] T001 [P] Update Constitution to v1.1.0 with comprehensive Quality Gates section in `.specify/memory/constitution.md`
- [x] T002 [P] Create validation script `scripts/quality-gates.sh` with all 10 quality gate checks
- [x] T003 [P] Add quality gate commands to `Makefile` (quality-gates, qa-*)
- [x] T004 [P] Replace PR template `.github/pull_request_template.md` with Constitution v1.1.0 checklist
- [x] T005 [P] Create comprehensive usage guide `docs/QUALITY_GATES.md`
- [x] T006 [P] Create setup & prerequisites guide `docs/QUALITY_GATES_SETUP.md`
- [x] T007 Update `README.md` with Quality Gates section and Contributing workflow
- [x] T008 Update `docs/DEVELOPER_GUIDE.md` with Constitution reference
- [x] T009 Update spec templates (plan, spec, tasks) for Constitution compliance
- [x] T010 [P] Create this feature specification `004-quality-gates-enforcement/spec.md`
- [x] T011 [P] Create implementation plan `004-quality-gates-enforcement/plan.md`
- [x] T012 Make validation script executable: `chmod +x scripts/quality-gates.sh`

**Checkpoint**: Foundation complete - Documentation and tooling ready for use

---

## Phase 2: Tool Configuration (✅ COMPLETE)

**Purpose**: Install and configure required quality gate tools

### User Story 1: Developer Pre-Push Validation

**Goal**: Enable developers to validate code locally before pushing

#### Configuration for User Story 1

- [x] T013 [P] [US1] Install PHPStan: `docker exec myshop_php composer require --dev phpstan/phpstan phpstan/extension-installer phpstan/phpstan-symfony phpstan/phpstan-doctrine`

- [x] T014 [P] [US1] Create PHPStan configuration file `phpstan.neon` with level 8, paths, Symfony/Doctrine extensions

- [x] T015 [P] [US1] Install PHP CS Fixer: `docker exec myshop_php composer require --dev friendsofphp/php-cs-fixer`

- [x] T016 [P] [US1] Create PHP CS Fixer configuration `.php-cs-fixer.dist.php` with PSR-12 and Symfony rules

- [x] T017 [US1] Test PHPStan runs successfully: `docker exec myshop_php vendor/bin/phpstan analyse`

- [x] T018 [US1] Test PHP CS Fixer runs successfully: `docker exec myshop_php vendor/bin/php-cs-fixer fix --dry-run`

- [x] T019 [US1] Fix any PHPStan errors in existing codebase or create baseline: `vendor/bin/phpstan analyse --generate-baseline`

- [x] T020 [US1] Fix any code style violations: `docker exec myshop_php vendor/bin/php-cs-fixer fix`

- [x] T021 [US1] Verify all Symfony lint commands work: container, yaml, twig, router

- [x] T022 [US1] Test complete quality gates script: `make quality-gates` - should pass all checks (⚠️ Composer warning: unbound version constraint for symfony/ai-open-ai-platform)

**Checkpoint**: Local validation fully functional

---

## Phase 3: CI/CD Integration (⚠️ IN PROGRESS)

**Purpose**: Automate quality gates validation in CI/CD pipeline

### User Story 2: Pull Request Automated Validation

**Goal**: Automatically validate all PRs in CI/CD before merge

#### Tests for User Story 2 (TDD - MANDATORY) 🔴

> **TDD REQUIREMENT: Write these tests FIRST, ensure they FAIL, then implement**

- [ ] T023 [P] [US2] Create test for CI workflow existence in `tests/Infrastructure/QualityGates/CIWorkflowTest.php`
  - Test: `.github/workflows/quality-gates.yml` file exists
  - Test: Workflow has all required jobs (tests, phpstan, style, etc.)

- [ ] T024 [P] [US2] Create test for validation script exit codes in `tests/Infrastructure/QualityGates/ValidationScriptTest.php`
  - Test: Script exits 0 when all checks pass
  - Test: Script exits 1 when any check fails
  - Test: Script outputs correct failure count

- [ ] T025 [US2] Create integration test for PR workflow in test repository
  - Test: PR with failing tests blocks merge
  - Test: PR with passing tests allows merge

#### Implementation for User Story 2

- [x] T026 [P] [US2] Create GitHub Actions workflow `.github/workflows/quality-gates.yml`:
  - Setup PHP 8.3 with extensions (mbstring, xml, mysql, mongodb, redis, pcov)
  - Setup services (MySQL, MongoDB, Redis, RabbitMQ)
  - Install Composer dependencies
  - Run all quality gate checks
  - Upload coverage report
  - Report status back to PR

- [x] T027 [P] [US2] Create Docker Compose override for CI: `docker-compose.ci.yml` (if not exists)

- [ ] T028 [US2] Test GitHub Actions workflow in test PR
  - Create test branch with intentional failures
  - Verify workflow runs and reports failures
  - Fix failures and verify workflow passes

- [ ] T029 [US2] Configure secrets in GitHub repository settings:
  - CODECOV_TOKEN (if using Codecov)
  - Other CI secrets as needed

- [x] T030 [US2] Add CI badge to README.md showing workflow status

**Checkpoint**: CI/CD automatically validates all PRs

---

## Phase 4: Branch Protection (⚠️ PRIORITY 1 - ENFORCEMENT)

**Purpose**: Enforce quality gates at infrastructure level

### User Story 4: Merge Policy Enforcement

**Goal**: Prevent merging code that doesn't pass quality gates

#### Configuration for User Story 4

- [ ] T031 [US4] Enable branch protection on `main` branch in GitHub/GitLab settings:
  - Require pull request reviews before merging
  - Require status checks to pass before merging
  - Require branches to be up to date before merging
  - Include administrators in restrictions

- [ ] T032 [US4] Configure required status checks:
  - `quality-gates / tests`
  - `quality-gates / phpstan`
  - `quality-gates / code-style`
  - `quality-gates / security`
  - `quality-gates / build`

- [ ] T033 [US4] Enable branch protection on `develop` branch (if exists) with same rules

- [ ] T034 [US4] Test branch protection:
  - Create PR with failing check
  - Verify merge button is disabled
  - Fix check and verify merge enabled

- [ ] T035 [US4] Document branch protection configuration in `docs/QUALITY_GATES_SETUP.md`

**Checkpoint**: Quality gates enforced by infrastructure

---

## Phase 5: Documentation Enhancement (⚠️ PRIORITY 2 - RECOMMENDED)

**Purpose**: Additional scripts and helpers

### User Story 3: Pull Request Reviewer Guidance

**Goal**: Provide comprehensive PR review checklists and guidance

#### Enhancements for User Story 3

- [ ] T036 [P] [US3] Create PR review checklist generator script `scripts/generate-pr-checklist.sh`

- [ ] T037 [P] [US3] Add examples of good/bad PRs to documentation

- [ ] T038 [P] [US3] Create video/screencast tutorial for quality gates usage

- [ ] T039 [P] [US3] Add troubleshooting section to README with common issues

- [ ] T040 [US3] Create team training presentation with slides

**Checkpoint**: Team fully trained on quality gates

---

## Phase 6: Advanced Features (🔮 FUTURE - NICE TO HAVE)

**Purpose**: Automated tools for complex validations

- [x] T041 [P] Add pre-commit hooks script `.git/hooks/pre-commit` that runs basic checks

- [ ] T042 [P] Install git-secrets for secret detection: `brew install git-secrets` / `apt-get install git-secrets`

- [ ] T043 [P] Create migration testing script `scripts/test-migration.sh` for automated migration validation

- [x] T044 [P] Create coverage check script `scripts/check-coverage.php` for threshold validation

- [ ] T045 Create automated architecture validator to check DDD boundaries (future enhancement)

- [ ] T046 Add automated N+1 query detector using Symfony Profiler API (future enhancement)

- [ ] T047 Integrate performance regression testing (future enhancement)

- [ ] T048 Add automated accessibility testing with Pa11y (future enhancement)

**Checkpoint**: Advanced automation features available

---

## Phase 7: Testing & Validation (⚠️ PRIORITY 1 - QUALITY ASSURANCE)

**Purpose**: Ensure quality gates work correctly and reliably

### Tests for Complete System

- [ ] T049 [P] Unit test for script helper functions in `tests/Infrastructure/QualityGates/ScriptHelpersTest.php`

- [ ] T050 [P] Integration test for full quality gates execution with real Docker container

- [ ] T051 [P] Test secret detection with sample secrets in test files

- [ ] T052 [P] Test debug statement detection with sample debug code

- [ ] T053 Test PHPStan with intentional errors to verify blocking

- [ ] T054 Test PHP CS Fixer with intentional style violations to verify blocking

- [ ] T055 Test coverage threshold with artificially lowered coverage

- [ ] T056 Test Composer audit with known vulnerable package (in test environment)

- [ ] T057 End-to-end test: Create PR → CI runs → Checks pass → Merge succeeds

- [ ] T058 End-to-end test: Create PR → CI runs → Checks fail → Merge blocked

- [ ] T059 Performance test: Measure quality gates execution time (should be < 5 minutes)

- [ ] T060 Load test: Run quality gates on 10 concurrent PRs in CI

#### Tests for User Story 2 (TDD - COMPLETED ✅)

- [x] T023 [P] [US2] Create test for CI workflow existence in `tests/Infrastructure/QualityGates/CIWorkflowTest.php`
  - Test: `.github/workflows/quality-gates.yml` file exists
  - Test: Workflow has all required jobs (tests, phpstan, style, etc.)

- [x] T024 [P] [US2] Create test for validation script exit codes in `tests/Infrastructure/QualityGates/ValidationScriptTest.php`
  - Test: Script exits 0 when all checks pass
  - Test: Script exits 1 when any check fails
  - Test: Script outputs correct failure count

- [ ] T025 [US2] Create integration test for PR workflow in test repository
  - Test: PR with failing tests blocks merge
  - Test: PR with passing tests allows merge

**Checkpoint**: Quality gates system fully tested and validated

---

## Phase 8: Rollout & Training (⚠️ PRIORITY 1 - ADOPTION)

**Purpose**: Team adoption and continuous improvement

- [ ] T061 Conduct team training session on quality gates (1-2 hours)

- [ ] T062 Create onboarding checklist for new team members including quality gates setup

- [ ] T063 Monitor first 10 PRs for issues and collect feedback

- [ ] T064 Create FAQ document based on common questions/issues

- [ ] T065 Optimize slow checks based on performance metrics

- [ ] T066 Add caching strategies to CI/CD for faster execution

- [ ] T067 Schedule monthly quality gates review meeting

- [ ] T068 Track metrics: bug escape rate, code review time, developer satisfaction

- [ ] T069 Create quarterly report on quality gates effectiveness

- [ ] T070 Plan improvements based on feedback and metrics

**Checkpoint**: Quality gates fully adopted and continuously improving

---

## Task Summary

**Total Tasks**: 70  
**Completed**: 12 (17%)  
**Priority 1 (Critical)**: 35 tasks  
**Priority 2 (Recommended)**: 5 tasks  
**Future Enhancements**: 8 tasks  
**Testing & Validation**: 12 tasks  
**Rollout & Training**: 10 tasks

**Estimated Effort**:
- Phase 2 (Tool Configuration): 2-4 hours
- Phase 3 (CI/CD Integration): 4-6 hours  
- Phase 4 (Branch Protection): 1 hour
- Phase 5 (Documentation): 2-3 hours
- Phase 6 (Advanced Features): 8-12 hours (future)
- Phase 7 (Testing): 4-6 hours
- Phase 8 (Rollout): 4-6 hours
- **Total Priority 1**: 15-20 hours
- **Total with Future**: 25-35 hours

---

**Next Immediate Actions** (Do this first):

1. ✅ Run `make quality-gates` to see current status
2. ⚠️ Install PHPStan (T013)
3. ⚠️ Create PHPStan config (T014)
4. ⚠️ Install PHP CS Fixer (T015)
5. ⚠️ Create PHP CS Fixer config (T016)
6. ⚠️ Fix any blocking issues (T019, T020)
7. ⚠️ Verify complete validation works (T022)
8. ⚠️ Create GitHub Actions workflow (T026)
9. ⚠️ Enable branch protection (T031)
10. ⚠️ Train team (T061)

---

**Document Version**: 1.0  
**Last Updated**: 2026-02-14  
**Status**: Ready for Phase 2 Implementation
