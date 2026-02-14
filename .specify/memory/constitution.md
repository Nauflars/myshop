<!--
  SYNC IMPACT REPORT - Last Constitution Update
  =============================================
  Version Change: 1.0.0 → 1.1.0 (MINOR: Quality Gate expansion)
  Previous Update: 2026-02-14 (Initial ratification)
  Current Update: 2026-02-14 (Quality Gate specification)
  
  Amendment Type: MINOR
  Rationale: Material expansion of Quality Gates section with comprehensive
  Pull Request requirements. No changes to core principles or governance.
  
  Sections Modified:
  ✅ Quality Gates - EXPANDED with comprehensive PR requirements
     - Pre-Commit Gate: Enhanced with PSR-12, no commented code
     - Pre-Merge Gate (Pull Request): NEW comprehensive specification including:
       * Mandatory automated checks (tests, static analysis, style)
       * Security requirements (no secrets/credentials)
       * Architecture and design validation (SOLID, DDD boundaries)
       * Performance requirements (no N+1, blocking operations)
       * Symfony-specific validation (container, yaml, twig, router)
       * Database migration requirements
       * Functional validation requirements
     - Pre-Deployment Gate: Maintained existing requirements
  
  ✅ Code Review Requirements - ENHANCED
     - Added functional verification requirement
     - Clarified approval and merge policy
     - Cross-referenced to Pre-Merge Gate specification
  
  Core Principles: UNCHANGED
  - I. Test-Driven Development (TDD) - NON-NEGOTIABLE
  - II. Clean Code Principles
  - III. SOLID Principles
  - IV. Domain-Driven Design (DDD) - Architectural Foundation
  - V. Test Coverage Excellence
  
  Templates Status:
  ✅ All templates remain aligned (no changes required)
  ✅ plan-template.md - Constitution Check still valid
  ✅ spec-template.md - TDD requirements still valid
  ✅ tasks-template.md - Test-first approach still valid
  
  Documentation Status:
  ✅ README.md - UPDATED with Quality Gates section and Contributing workflow
  ✅ docs/DEVELOPER_GUIDE.md - No update required (already references constitution)
  ✅ .github/pull_request_template.md - REPLACED with comprehensive Constitution v1.1.0 checklist
  ✅ docs/QUALITY_GATES.md - NEW comprehensive usage guide (500+ lines)
  ✅ docs/QUALITY_GATES_SETUP.md - NEW setup & prerequisites guide (800+ lines)
  
  Tooling Added:
  ✅ scripts/quality-gates.sh - NEW comprehensive pre-push validation script
  ✅ Makefile - EXPANDED with Quality Gates commands section:
     - make quality-gates (runs comprehensive validation)
     - make qa-* commands for individual checks
     - make qa-full (runs all checks without coverage report)
  
  Follow-up TODOs:
  - None - Amendment self-contained with complete tooling support
  
  Version History:
  - v1.0.0 (2026-02-14): Initial ratification with 5 core principles
  - v1.1.0 (2026-02-14): Comprehensive Pull Request Quality Gate specification
-->

# MyShop Constitution

## Core Principles

### I. Test-Driven Development (TDD) - NON-NEGOTIABLE

**Every feature MUST follow the TDD cycle before implementation:**

- Tests are written FIRST based on acceptance criteria from spec.md
- Tests MUST fail initially (Red phase)
- Only then is implementation code written to pass tests (Green phase)
- Code is refactored while keeping tests green (Refactor phase)
- No code ships without corresponding tests

**Rationale**: TDD ensures requirements are clear, testable, and met. It prevents feature creep, reduces debugging time, and serves as living documentation. This is non-negotiable because untested code introduces technical debt and regression risks that compound over time.

### II. Clean Code Principles

**All code MUST adhere to Clean Code practices:**

- Meaningful and intention-revealing names for classes, methods, and variables
- Functions do one thing and do it well (Single Responsibility at function level)
- Small, focused functions (typically < 20 lines, > 100 lines requires justification)
- No commented-out code in commits
- DRY principle: Don't Repeat Yourself - extract common logic
- Code should read like well-written prose
- Use early returns and guard clauses to reduce nesting
- Constants instead of magic numbers
- Self-documenting code preferred over comments (comments explain WHY, not WHAT)

**Rationale**: Clean Code reduces cognitive load, accelerates onboarding, minimizes bugs, and makes maintenance sustainable. Code is read 10x more than it's written - optimize for reading.

### III. SOLID Principles

**All classes and components MUST follow SOLID design principles:**

- **S**ingle Responsibility: Each class has one reason to change
- **O**pen/Closed: Open for extension, closed for modification (use interfaces, inheritance, composition)
- **L**iskov Substitution: Derived classes must be substitutable for their base classes
- **I**nterface Segregation: Many specific interfaces are better than one general-purpose interface
- **D**ependency Inversion: Depend on abstractions (interfaces), not concretions

**Application in MyShop**:
- Domain entities are pure, depend only on abstractions
- Application use cases depend on repository interfaces, not implementations
- Infrastructure provides concrete implementations of interfaces
- Controllers depend on use cases, not directly on repositories

**Rationale**: SOLID enables modular, testable, maintainable architecture. It allows us to swap implementations (e.g., switch from MySQL to MongoDB) without changing business logic. It's the foundation of our DDD architecture.

### IV. Domain-Driven Design (DDD) - Architectural Foundation

**The project MUST strictly adhere to DDD layered architecture:**

**Domain Layer** (`src/Domain/`):
- Pure business logic with ZERO infrastructure dependencies
- Entities (User, Product, Cart, Order) contain business rules
- Value Objects (Email, Money) enforce domain invariants
- Repository interfaces define contracts
- No framework annotations, no database concerns

**Application Layer** (`src/Application/`):
- Use cases orchestrate domain logic (CreateUser, AddProductToCart, Checkout)
- DTOs for data transfer across layer boundaries
- Depends only on domain interfaces
- No HTTP, no database, no external API concerns

**Infrastructure Layer** (`src/Infrastructure/`):
- Concrete implementations (Doctrine repositories, Symfony controllers)
- Framework integrations and external services
- Chatbot tools and API clients
- Configuration and dependency injection

**Boundaries are enforced**:
- Domain NEVER imports from Application or Infrastructure
- Application NEVER imports from Infrastructure
- Infrastructure can import from Domain and Application

**Rationale**: DDD keeps business logic portable, testable, and technology-agnostic. It prevents vendor lock-in and enables independent evolution of business rules and technical implementations.

### V. Test Coverage Excellence

**After every feature integration, test coverage MUST increase or be maintained:**

**Coverage Requirements**:
- **Domain Layer**: 90%+ coverage REQUIRED (pure business logic is fully testable)
- **Application Layer**: 85%+ coverage REQUIRED (use cases must be validated)
- **Infrastructure Layer**: 70%+ coverage MINIMUM (includes integration tests)
- **Overall Project**: Never decrease below current baseline

**Test Pyramid**:
- **Unit Tests**: Fast, isolated, test single units (70% of tests)
  - Domain entities, value objects, business rules
  - Application use cases with mocked dependencies
  
- **Integration Tests**: Test component interactions (25% of tests)
  - Repository implementations with real database
  - API endpoints with real HTTP requests
  - Message queue consumers
  
- **E2E Tests**: Test complete user journeys (5% of tests)
  - Registration → Login → Add to Cart → Checkout
  - Admin workflows
  - Chatbot interactions

**Every pull request MUST**:
- Include tests for new code
- Not decrease overall coverage percentage
- Pass all existing tests
- Generate coverage report: `php vendor/bin/phpunit --coverage-html var/coverage`

**Rationale**: High test coverage catches regressions early, enables confident refactoring, and serves as executable documentation. The pyramid shape ensures fast feedback loops while maintaining integration confidence.

## Architecture Standards

### DDD Implementation Checklist

Every feature implementation MUST verify:

- [ ] Domain entities are pure PHP with no infrastructure dependencies
- [ ] Repository interfaces are defined in Domain layer
- [ ] Use cases orchestrate domain logic without knowing about HTTP/Database
- [ ] Controllers are thin, delegating to use cases
- [ ] DTOs are used for data transfer across layers
- [ ] Value Objects enforce domain invariants (Email validates format, Money handles currency)
- [ ] Domain events are used for cross-bounded-context communication

### SOLID Compliance Checklist

Every class MUST verify:

- [ ] Single Responsibility: Class name clearly describes ONE responsibility
- [ ] Open/Closed: Extension points use interfaces or abstract classes
- [ ] Liskov Substitution: Subclasses honor parent contracts
- [ ] Interface Segregation: Interfaces are role-specific and cohesive
- [ ] Dependency Inversion: Dependencies are injected via constructor, type-hinted to interfaces

## Development Workflow

### Feature Development Process

1. **Specification** (`/speckit.spec`): Define user stories with acceptance criteria
2. **Planning** (`/speckit.plan`): Technical design, DDD layer assignment, interface contracts
3. **Task Breakdown** (`/speckit.tasks`): Organize by user story priority (P1, P2, P3)
4. **Test First**: Write failing tests for acceptance criteria
5. **Implementation**: Write minimum code to pass tests
6. **Refactor**: Clean up while keeping tests green
7. **Integration**: Merge when coverage maintained/increased and all tests pass

### Code Review Requirements

Every pull request MUST include:

- Tests demonstrating functionality (unit + integration as applicable)
- Coverage report showing no decrease
- SOLID compliance verification in description
- DDD layer boundary verification (no improper dependencies)
- Clean Code self-review checklist completed
- Functional verification that feature/fix behaves as expected
- Confirmation that no regressions were introduced

**Pull Request Approval and Merge**:

- Pull requests require approval according to repository review policy
- All Pre-Merge Quality Gates (see below) MUST pass before merge
- Reviewers MUST verify constitution compliance
- Merge is BLOCKED if any NON-NEGOTIABLE principle is violated

## Quality Gates

### Pre-Commit Gate (Developer Local)

Before committing code, developers MUST verify:

- [ ] All tests pass locally: `docker exec myshop_php php vendor/bin/phpunit`
- [ ] Code follows PSR-12 coding standard: `docker exec myshop_php vendor/bin/php-cs-fixer fix --dry-run`
- [ ] No commented-out code in the changeset
- [ ] No debug statements (var_dump, dd, console.log) in the changeset
- [ ] No secrets or credentials in the changeset

### Pre-Merge Gate (Pull Request) - MANDATORY

**Purpose**: Ensure code reliability, maintainability, security, and production readiness before merge.

**Merge Policy**: A Pull Request MAY ONLY be merged if ALL mandatory checks pass and the PR has been approved according to repository review policy.

#### 1. Tests (MANDATORY)

- [ ] All unit tests MUST pass
- [ ] All integration tests MUST pass
- [ ] All end-to-end (E2E) tests MUST pass
- [ ] Code coverage MUST meet or exceed minimum threshold:
  - Domain Layer: 90%+
  - Application Layer: 85%+
  - Infrastructure Layer: 70%+
  - Overall: No decrease from baseline

#### 2. Static Analysis and Code Style (MANDATORY)

- [ ] **PHPStan** MUST pass at configured level with no errors:
  ```bash
  docker exec myshop_php vendor/bin/phpstan analyse
  ```

- [ ] **PHP CS Fixer** MUST pass with no violations:
  ```bash
  docker exec myshop_php vendor/bin/php-cs-fixer fix --dry-run --diff
  ```

- [ ] **Composer validate** MUST pass with no errors:
  ```bash
  docker exec myshop_php composer validate --strict
  ```

- [ ] **Composer audit** MUST report no known vulnerabilities:
  ```bash
  docker exec myshop_php composer audit
  ```

#### 3. Symfony Validation (MANDATORY)

The following Symfony lint checks MUST pass without errors:

- [ ] **Container validation**:
  ```bash
  docker exec myshop_php php bin/console lint:container
  ```

- [ ] **YAML configuration validation**:
  ```bash
  docker exec myshop_php php bin/console lint:yaml config/
  ```

- [ ] **Twig template validation**:
  ```bash
  docker exec myshop_php php bin/console lint:twig templates/
  ```

- [ ] **Router validation**:
  ```bash
  docker exec myshop_php php bin/console lint:router
  ```

#### 4. Database Changes (if applicable)

If the Pull Request includes database migrations:

- [ ] Migration MUST execute successfully without errors
- [ ] Migration SHOULD be reversible when technically possible (down migration)
- [ ] Migration MUST NOT introduce unintended data loss
- [ ] Migration MUST NOT introduce schema corruption
- [ ] Migration tested in isolated environment before PR submission

#### 5. Security Requirements (MANDATORY)

Pull Requests MUST NOT include secrets or sensitive information:

- [ ] No API keys, passwords, access tokens, or private credentials
- [ ] No hardcoded sensitive environment configuration
- [ ] No exposure of internal system details in error messages
- [ ] No commented-out credentials or authentication bypasses
- [ ] No security-sensitive debugging code (authentication skips, etc.)

**Violation Response**: PRs containing secrets are IMMEDIATELY BLOCKED. Secrets must be rotated before merge.

#### 6. Architecture and Code Design (MANDATORY)

- [ ] Code respects DDD architectural boundaries (Domain → Application → Infrastructure)
- [ ] Business logic is NOT in controllers (controllers MUST be thin)
- [ ] Code follows SOLID principles (verified in PR description)
- [ ] Separation of concerns maintained
- [ ] No unnecessary complexity or anti-patterns introduced
- [ ] Repository interfaces defined in Domain, implementations in Infrastructure
- [ ] Use cases orchestrate without infrastructure knowledge

#### 7. Performance Requirements (MANDATORY)

Pull Requests MUST NOT introduce known performance issues:

- [ ] No N+1 database query problems (use Doctrine Query profiling)
- [ ] No unnecessary or redundant database queries
- [ ] No inefficient blocking operations without justification
- [ ] Eager loading used appropriately for relations
- [ ] Indexes defined for frequently queried columns (if schema changes)

**Performance Validation**:
```bash
# Enable Symfony Profiler in dev environment
# Verify query count and execution time in profiler after testing feature
```

#### 8. CI/CD Requirements (MANDATORY)

- [ ] Pull Request MUST pass complete CI pipeline
- [ ] Project MUST build successfully without errors:
  ```bash
  docker-compose build
  docker-compose up -d
  ```
- [ ] Code MUST be deployable to staging environment (if applicable)

#### 9. Functional Validation (MANDATORY)

- [ ] Changes introduced MUST be functionally verified (manual testing)
- [ ] Feature or fix MUST behave as expected per acceptance criteria
- [ ] Pull Request MUST NOT introduce regressions in existing functionality
- [ ] E2E tests cover critical user journeys affected by changes

### Pre-Deployment Gate (Staging/Production)

- [ ] All Pre-Merge gates passed
- [ ] All tests pass in staging environment
- [ ] Manual smoke testing of affected features completed
- [ ] Performance benchmarks maintained (no degradation)
- [ ] Rollback plan documented and tested
- [ ] Database migration tested in staging with production-like data volume
- [ ] Monitoring and alerting configured for new features

## Governance

### Constitution Authority

- This Constitution supersedes all other development practices and guidelines
- All code reviews, pull requests, and feature implementations MUST comply
- Violation of NON-NEGOTIABLE principles (TDD, DDD) blocks merge
- Exceptions to non-mandatory principles require explicit justification in PR description

### Amendment Process

- Amendments require documentation of rationale and impact analysis
- Version increment follows semantic versioning:
  - **MAJOR**: Backward-incompatible changes to core principles
  - **MINOR**: New principles or material expansions
  - **PATCH**: Clarifications, typo fixes, non-semantic refinements
- Amendments must propagate to all templates in `.specify/templates/`
- Impact report must document template consistency verification

### Compliance Review

- Constitution compliance is reviewed in every pull request
- Quarterly constitution review to identify improvements
- Templates checked for alignment after any amendment
- All team members are responsible for upholding these principles

### Runtime Guidance

For AI-assisted development guidance that respects this constitution, refer to:
- Project context: `.specify/docs/project.md`
- Templates: `.specify/templates/*.md`
- Existing specs: `.specify/specs/*/`

### Quality Gates Implementation

For setup and configuration of all Quality Gates tools and infrastructure:
- **Setup Guide**: `docs/QUALITY_GATES_SETUP.md` - Prerequisites, configuration, installation steps
- **Usage Guide**: `docs/QUALITY_GATES.md` - How to run quality gates, troubleshooting
- **Validation Script**: `scripts/quality-gates.sh` - Automated pre-push validation
- **Makefile Commands**: `make quality-gates`, `make qa-*` - Quick access commands

**Version**: 1.1.0 | **Ratified**: 2026-02-14 | **Last Amended**: 2026-02-14
