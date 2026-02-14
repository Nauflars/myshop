# Pull Request

## Description
<!-- Provide a clear and concise description of the changes -->

## Related Issue/Feature
<!-- Link to related issue, spec, or user story -->
- Closes #
- Spec: `.specify/specs/###-feature-name/`

## Type of Change
<!-- Check all that apply -->
- [ ] Bug fix (non-breaking change which fixes an issue)
- [ ] New feature (non-breaking change which adds functionality)
- [ ] Breaking change (fix or feature that would cause existing functionality to not work as expected)
- [ ] Database migration included
- [ ] Documentation update

## Constitution Compliance Checklist

### Core Principles (MANDATORY)

#### TDD (Test-Driven Development)
- [ ] Tests were written FIRST before implementation
- [ ] All tests pass: `docker exec myshop_php php vendor/bin/phpunit`
- [ ] Coverage maintained/increased: `docker exec myshop_php php vendor/bin/phpunit --coverage-text`

#### DDD (Domain-Driven Design)
- [ ] Domain entities have no infrastructure dependencies
- [ ] Use cases orchestrate without infrastructure knowledge
- [ ] Repository interfaces defined in Domain layer
- [ ] Controllers are thin, delegating to use cases

#### SOLID Principles
- [ ] Single Responsibility: Each class has one clear purpose
- [ ] Open/Closed: Extension points use interfaces/abstractions
- [ ] Dependency Inversion: Dependencies injected via constructor

#### Clean Code
- [ ] Intention-revealing names for classes, methods, variables
- [ ] Functions are small and focused (< 20 lines typical)
- [ ] No commented-out code
- [ ] No debug statements (var_dump, dd, console.log)

## Quality Gates (MANDATORY - Auto-checked by CI)

### 1. Tests
- [ ] Unit tests pass
- [ ] Integration tests pass
- [ ] E2E tests pass (if applicable)
- [ ] Coverage: Domain ≥90%, Application ≥85%, Infrastructure ≥70%

### 2. Static Analysis
- [ ] PHPStan passes: `docker exec myshop_php vendor/bin/phpstan analyse`
- [ ] PHP CS Fixer passes: `docker exec myshop_php vendor/bin/php-cs-fixer fix --dry-run`
- [ ] Composer validate passes: `docker exec myshop_php composer validate --strict`
- [ ] No vulnerabilities: `docker exec myshop_php composer audit`

### 3. Symfony Validation
- [ ] Container: `docker exec myshop_php php bin/console lint:container`
- [ ] YAML: `docker exec myshop_php php bin/console lint:yaml config/`
- [ ] Twig: `docker exec myshop_php php bin/console lint:twig templates/`
- [ ] Router: `docker exec myshop_php php bin/console lint:router`

### 4. Security
- [ ] No secrets, API keys, passwords, or tokens in code
- [ ] No hardcoded credentials
- [ ] No security bypasses or commented authentication code

### 5. Performance
- [ ] No N+1 query problems (verified via Symfony Profiler)
- [ ] No redundant database queries
- [ ] Appropriate eager loading for relations
- [ ] No inefficient blocking operations

### 6. Database Migration (if applicable)
- [ ] Migration executes successfully
- [ ] Rollback (down) migration provided when possible
- [ ] No unintended data loss
- [ ] Tested in isolated environment

## Functional Validation
- [ ] Feature/fix behaves as expected per acceptance criteria
- [ ] No regressions introduced in existing functionality
- [ ] Manually tested in development environment

## Screenshots/Evidence (if applicable)
<!-- Add screenshots, logs, or test output demonstrating functionality -->

## Additional Notes
<!-- Any additional context, decisions, or trade-offs made -->

## Reviewer Checklist
<!-- For reviewers only -->
- [ ] Code review completed
- [ ] Constitution compliance verified
- [ ] All quality gates passed
- [ ] Functional verification completed
- [ ] Ready to merge

---

**Constitution Reference**: [.specify/memory/constitution.md](../.specify/memory/constitution.md) v1.1.0
