# Quality Gates Guide

**Constitution Reference**: [../.specify/memory/constitution.md](../.specify/memory/constitution.md) v1.1.0  
**Setup & Prerequisites**: [QUALITY_GATES_SETUP.md](QUALITY_GATES_SETUP.md)  
**Last Updated**: 2026-02-14

> **⚠️ First Time Setup**: If you haven't configured the quality gates tools yet, start with [QUALITY_GATES_SETUP.md](QUALITY_GATES_SETUP.md) to install and configure all required tools (PHPStan, PHP CS Fixer, etc.)

## Overview

Quality Gates are mandatory validation checkpoints that ensure code reliability, maintainability, security, and production readiness before merging into protected branches. All Pull Requests MUST pass these gates before merge approval.

## Quick Start

### Run All Quality Gates

Before pushing your code:

```bash
make quality-gates
```

This script validates EVERYTHING defined in Constitution v1.1.0 and provides a clear pass/fail report.

### Individual Checks

Run specific quality gate checks:

```bash
# Tests
make qa-tests                # All tests (unit + integration + E2E)
make qa-coverage             # Coverage report with thresholds

# Static Analysis & Style
make qa-phpstan              # PHPStan static analysis
make qa-cs-fixer             # Code style check (PSR-12)
make qa-cs-fixer-fix         # Fix code style issues
make qa-composer-validate    # Validate composer.json
make qa-composer-audit       # Security vulnerability scan

# Symfony Validation
make qa-lint-container       # Validate DI container
make qa-lint-yaml            # Validate YAML configs
make qa-lint-twig            # Validate Twig templates
make qa-lint-router          # Validate routes
make qa-lint-all             # Run all Symfony linters

# Comprehensive
make qa-full                 # All checks except coverage report
```

## Quality Gate Checklist

### ✅ 1. Tests (MANDATORY)

**Requirements**:
- All unit tests MUST pass
- All integration tests MUST pass
- All E2E tests MUST pass
- Coverage MUST meet minimum thresholds:
  - Domain Layer: ≥90%
  - Application Layer: ≥85%
  - Infrastructure Layer: ≥70%
  - Overall: No decrease from baseline

**Commands**:
```bash
docker exec myshop_php php vendor/bin/phpunit
docker exec myshop_php php vendor/bin/phpunit --coverage-html var/coverage
```

**What it checks**:
- All test suites execute without errors
- Test coverage percentages by layer
- No skipped or incomplete tests (unless explicitly marked)

---

### ✅ 2. Static Analysis (PHPStan)

**Requirements**:
- PHPStan MUST pass at configured level
- No errors allowed
- Level 8 or higher recommended

**Command**:
```bash
docker exec myshop_php vendor/bin/phpstan analyse
```

**What it checks**:
- Type safety violations
- Undefined variables/properties/methods
- Dead code and unreachable statements
- Return type mismatches
- Array access violations

**Common Issues**:
- Missing type hints on parameters/returns
- Wrong return type declarations
- Accessing undefined object properties
- Calling non-existent methods

---

### ✅ 3. Code Style (PHP CS Fixer)

**Requirements**:
- Code MUST follow PSR-12 standard
- No style violations allowed
- Configured rules in `.php-cs-fixer.dist.php`

**Commands**:
```bash
# Check for violations (dry-run)
docker exec myshop_php vendor/bin/php-cs-fixer fix --dry-run --diff

# Auto-fix violations
docker exec myshop_php vendor/bin/php-cs-fixer fix
```

**What it checks**:
- Indentation (4 spaces)
- Line length (120 chars recommended)
- Braces placement
- Import statements organization
- Whitespace consistency
- Naming conventions

---

### ✅ 4. Composer Validation

**Requirements**:
- `composer.json` MUST be valid
- `composer.lock` MUST be in sync
- No security vulnerabilities

**Commands**:
```bash
# Validate composer files
docker exec myshop_php composer validate --strict

# Check for vulnerabilities
docker exec myshop_php composer audit
```

**What it checks**:
- JSON syntax validity
- Required fields present
- Version constraints valid
- Lock file synchronized
- Known security vulnerabilities in dependencies

**Common Issues**:
- Outdated `composer.lock` (run `composer update`)
- Invalid version constraints
- Vulnerable dependencies (update or replace)

---

### ✅ 5. Symfony Validation

**Requirements**:
- Dependency Injection container MUST be valid
- YAML configuration MUST be valid
- Twig templates MUST be valid
- Routes MUST be valid

**Commands**:
```bash
docker exec myshop_php php bin/console lint:container
docker exec myshop_php php bin/console lint:yaml config/
docker exec myshop_php php bin/console lint:twig templates/
docker exec myshop_php php bin/console lint:router
```

**What each checks**:

#### Container
- Service definitions exist
- Dependency injection valid
- Circular dependencies absent
- Autoconfiguration correct

#### YAML
- YAML syntax valid
- No duplicate keys
- Proper indentation
- Valid service references

#### Twig
- Template syntax valid
- Variables exist in context
- Filters/functions defined
- Include/extends paths exist

#### Router
- Route definitions valid
- Controller methods exist
- Parameter constraints valid
- No route conflicts

---

### ✅ 6. Security Requirements

**Requirements**:
- NO secrets, API keys, passwords, tokens in code
- NO hardcoded credentials
- NO security bypasses or commented authentication

**Manual Checks** (automated in `quality-gates.sh`):
```bash
# Search for potential secrets in staged files
git diff --cached | grep -iE "(api[_-]?key|password|secret|token|credential)"

# Search for debug statements
git diff --cached | grep -iE "(var_dump|dd\(|dump\(|console\.log|error_log)"

# Search for commented code
git diff --cached | grep -E "^\+.*//.*\$"
```

**Violation Response**:
- PRs with secrets are IMMEDIATELY BLOCKED
- Secrets MUST be rotated before merge
- Use environment variables for sensitive config

---

### ✅ 7. Architecture & Design

**Requirements**:
- DDD boundaries respected (Domain → Application → Infrastructure)
- Business logic NOT in controllers (controllers MUST be thin)
- SOLID principles followed
- No unnecessary complexity or anti-patterns

**Manual Verification** (in PR review):
- [ ] Domain entities have no infrastructure imports
- [ ] Use cases orchestrate without HTTP/Database knowledge
- [ ] Repository interfaces in Domain, implementations in Infrastructure
- [ ] Controllers delegate to use cases
- [ ] DTOs used for cross-layer data transfer
- [ ] Single Responsibility per class
- [ ] Dependencies injected via constructor, type-hinted to interfaces

**Common Violations**:
- Business logic in controllers
- Domain importing from Infrastructure
- Direct database access from controllers
- God classes (too many responsibilities)
- Tight coupling to frameworks

---

### ✅ 8. Performance Requirements

**Requirements**:
- NO N+1 query problems
- NO unnecessary/redundant database queries
- NO inefficient blocking operations without justification
- Appropriate eager loading for relations

**Validation**:
```bash
# Enable Symfony Profiler in dev environment
# Test the feature manually
# Check profiler for:
# - Query count (should be minimal)
# - Duplicate queries (should be none)
# - Query execution time (< 100ms per query ideally)
```

**Common Issues**:
- Lazy loading in loops (N+1 problem)
- Fetching entire collections when filtering needed
- Missing database indexes
- Synchronous operations that should be async (use message queue)

**Solutions**:
- Use `JOIN` or `fetch` in DQL for relations
- Add `->addSelect()` for eager loading
- Define indexes in migrations
- Use Symfony Messenger for async operations

---

### ✅ 9. Database Migrations (if applicable)

**Requirements**:
- Migration MUST execute successfully
- Migration SHOULD be reversible (`down()` method)
- Migration MUST NOT cause data loss
- Migration tested in isolated environment

**Commands**:
```bash
# Test migration
docker exec myshop_php php bin/console doctrine:migrations:migrate --dry-run

# Execute migration
docker exec myshop_php php bin/console doctrine:migrations:migrate

# Test rollback
docker exec myshop_php php bin/console doctrine:migrations:migrate prev
```

**Checklist**:
- [ ] Migration generates correct SQL
- [ ] Migration tested on copy of production data
- [ ] Down migration reverses changes
- [ ] No accidental `DROP` statements
- [ ] Foreign keys maintained
- [ ] Indexes updated if needed

---

### ✅ 10. Functional Validation

**Requirements**:
- Feature/fix MUST behave as expected per acceptance criteria
- NO regressions in existing functionality
- Manual testing completed in dev environment

**Process**:
1. Review acceptance criteria from spec
2. Test happy path
3. Test edge cases
4. Test error scenarios
5. Verify no side effects on existing features

---

## CI/CD Integration

The CI pipeline automatically runs all quality gates on every Pull Request:

```yaml
# Example CI workflow (conceptual)
- Run Tests (PHPUnit)
- Check Coverage Thresholds
- Run PHPStan
- Run PHP CS Fixer (check mode)
- Validate Composer
- Audit Dependencies
- Lint Symfony (container, yaml, twig, router)
- Check for Secrets
- Build Docker Image
- Deploy to Staging (if applicable)
```

**Pull Request Status**:
- ✅ All checks pass → Ready for review
- ❌ Any check fails → Merge BLOCKED

---

## Troubleshooting

### Issue: Tests Fail Locally

**Solution**:
```bash
# Ensure containers are running
docker-compose ps

# Clear cache
make clean

# Run specific failing test with verbose output
docker exec myshop_php php vendor/bin/phpunit --filter YourFailingTest --verbose
```

### Issue: PHPStan Errors

**Solution**:
1. Read error message carefully (file, line, issue)
2. Add type hints where missing
3. Fix return type declarations
4. Add PHPDoc if type inference needed
5. Generate PHPStan baseline for legacy code only: `vendor/bin/phpstan analyse --generate-baseline`

### Issue: Code Style Violations

**Solution**:
```bash
# Auto-fix most issues
make qa-cs-fixer-fix

# Review changes
git diff

# Manually fix complex cases
```

### Issue: Composer Audit Vulnerabilities

**Solution**:
```bash
# Update specific package
docker exec myshop_php composer update vendor/package

# Or update all dependencies
docker exec myshop_php composer update

# If vulnerability in transitive dependency, may need to update parent package
```

### Issue: Symfony Container Invalid

**Solution**:
```bash
# Clear cache
make clean

# Check service definition
docker exec myshop_php php bin/console debug:container --show-private

# Verify service exists and type-hints match
```

---

## Best Practices

### Pre-Commit

Run quick checks before committing:
```bash
make test
make qa-phpstan
make qa-cs-fixer
```

### Pre-Push

Run comprehensive validation:
```bash
make quality-gates
```

This ensures you won't push code that will fail CI.

### Pre-PR

Before creating a Pull Request:
1. Run `make quality-gates` and ensure all pass
2. Generate coverage report: `make qa-coverage`
3. Review your own code changes
4. Fill out PR template completely
5. Add screenshots/evidence if applicable

### During Development

Enable Symfony Profiler and check:
- Query count after implementing features
- Memory usage
- Request/response time

---

## Resources

- **Setup Guide**: [QUALITY_GATES_SETUP.md](QUALITY_GATES_SETUP.md) - Install and configure all tools
- **Constitution**: [../.specify/memory/constitution.md](../.specify/memory/constitution.md)
- **PR Template**: [../.github/pull_request_template.md](../.github/pull_request_template.md)
- **Quality Gates Script**: [../scripts/quality-gates.sh](../scripts/quality-gates.sh)
- **Developer Guide**: [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md)
- **PHPStan Documentation**: https://phpstan.org/
- **PHP CS Fixer**: https://github.com/PHP-CS-Fixer/PHP-CS-Fixer
- **Symfony Best Practices**: https://symfony.com/doc/current/best_practices.html

---

## FAQ

**Q: Can I skip a quality gate if I'm in a hurry?**  
A: No. Quality gates are NON-NEGOTIABLE per Constitution Principle I (TDD). PRs that fail quality gates are automatically blocked.

**Q: What if PHPStan reports errors in vendor code?**  
A: PHPStan should be configured to exclude vendor. Check `phpstan.neon` configuration.

**Q: Can I commit code that fails locally if I'll fix it later?**  
A: No. All commits should pass quality gates. This keeps the commit history clean and enables easy bisecting.

**Q: What if a quality gate check is flaky/unreliable?**  
A: Report the issue to the team. Quality gates should be deterministic. Flaky tests should be fixed or removed.

**Q: How long should quality gates take?**  
A: Typically 2-5 minutes for comprehensive validation. If much longer, investigate slow tests or performance issues.

**Q: Can I run quality gates in parallel?**  
A: The `make quality-gates` script runs checks sequentially for clear output. For parallel execution, use CI/CD pipeline.

---

**Last Updated**: 2026-02-14  
**Version**: Aligned with Constitution v1.1.0
