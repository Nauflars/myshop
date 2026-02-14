# Quality Gates Setup & Prerequisites

**Constitution Reference**: [../.specify/memory/constitution.md](../.specify/memory/constitution.md) v1.1.0  
**Purpose**: Setup guide for all mandatory Quality Gates  
**Last Updated**: 2026-02-14

## Overview

This document describes all tools, configurations, and prerequisites needed to implement and enforce the Quality Gates defined in Constitution v1.1.0. Every item listed here is MANDATORY for Pull Request validation.

---

## 1. Testing Infrastructure

### 1.1 PHPUnit Configuration

**Status**: ✅ Already Configured

**Location**: `phpunit.xml.dist`

**Requirements**:
- PHPUnit 10.5+ installed via Composer
- Test suites defined (unit, integration, E2E)
- Coverage driver configured (PCOV or Xdebug)
- Bootstrap file configured
- Test directories properly structured

**Verification**:
```bash
docker exec myshop_php vendor/bin/phpunit --version
# Expected: PHPUnit 10.5.x
```

**Configuration Check**:
```bash
docker exec myshop_php php -m | grep pcov
# Expected: pcov
```

### 1.2 PCOV Extension (Code Coverage)

**Status**: ✅ Installed

**Purpose**: Generate code coverage reports (faster than Xdebug)

**Installation** (already in Dockerfile):
```dockerfile
RUN pecl install pcov && docker-php-ext-enable pcov
```

**Configuration**: `php.ini` (optional tuning)
```ini
pcov.enabled = 1
pcov.directory = /var/www/html/src
```

**Verification**:
```bash
docker exec myshop_php php --ri pcov
```

### 1.3 Test Coverage Thresholds

**Required Coverage**:
- Domain Layer: ≥90%
- Application Layer: ≥85%
- Infrastructure Layer: ≥70%
- Overall: No decrease from baseline

**Implementation**: 
Create `phpunit-coverage-check.php` script or configure in CI:

```php
// scripts/check-coverage.php
<?php
$coverageFile = 'var/coverage/clover.xml';
$xml = simplexml_load_file($coverageFile);
$metrics = $xml->project->metrics;

$coverage = ($metrics['coveredstatements'] / $metrics['statements']) * 100;

if ($coverage < 70) {
    echo "Coverage $coverage% is below threshold 70%\n";
    exit(1);
}
```

---

## 2. Static Analysis Tools

### 2.1 PHPStan

**Status**: ⚠️ REQUIRES SETUP

**Required Version**: 1.10+

**Installation**:
```bash
docker exec myshop_php composer require --dev phpstan/phpstan
docker exec myshop_php composer require --dev phpstan/extension-installer
docker exec myshop_php composer require --dev phpstan/phpstan-symfony
docker exec myshop_php composer require --dev phpstan/phpstan-doctrine
```

**Configuration**: Create `phpstan.neon` or `phpstan.neon.dist`

```neon
parameters:
    level: 8
    paths:
        - src
        - tests
    excludePaths:
        - src/Kernel.php
        - tests/bootstrap.php
    symfony:
        container_xml_path: var/cache/dev/App_KernelDevDebugContainer.xml
    doctrine:
        objectManagerLoader: tests/doctrine-bootstrap.php
    ignoreErrors:
        # Add specific ignores if needed for legacy code
    checkMissingIterableValueType: false
    checkGenericClassInNonGenericObjectType: false
```

**Verification**:
```bash
docker exec myshop_php vendor/bin/phpstan --version
docker exec myshop_php vendor/bin/phpstan analyse --dry-run
```

**Action Required**: ✅ Install and configure PHPStan

### 2.2 PHP CS Fixer (Code Style)

**Status**: ⚠️ REQUIRES SETUP

**Required Version**: 3.40+

**Installation**:
```bash
docker exec myshop_php composer require --dev friendsofphp/php-cs-fixer
```

**Configuration**: Create `.php-cs-fixer.dist.php`

```php
<?php

$finder = PhpCsFixer\Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->exclude('var')
    ->exclude('vendor')
    ->name('*.php')
    ->notName('*.blade.php')
    ->ignoreDotFiles(true)
    ->ignoreVCS(true);

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR12' => true,
        '@Symfony' => true,
        'array_syntax' => ['syntax' => 'short'],
        'ordered_imports' => ['sort_algorithm' => 'alpha'],
        'no_unused_imports' => true,
        'not_operator_with_successor_space' => true,
        'trailing_comma_in_multiline' => true,
        'phpdoc_scalar' => true,
        'unary_operator_spaces' => true,
        'binary_operator_spaces' => true,
        'blank_line_before_statement' => [
            'statements' => ['break', 'continue', 'declare', 'return', 'throw', 'try'],
        ],
        'phpdoc_single_line_var_spacing' => true,
        'phpdoc_var_without_name' => true,
        'method_argument_space' => [
            'on_multiline' => 'ensure_fully_multiline',
            'keep_multiple_spaces_after_comma' => true,
        ],
        'single_trait_insert_per_statement' => true,
    ])
    ->setFinder($finder)
    ->setRiskyAllowed(true)
    ->setUsingCache(true);
```

**Verification**:
```bash
docker exec myshop_php vendor/bin/php-cs-fixer --version
docker exec myshop_php vendor/bin/php-cs-fixer fix --dry-run --diff
```

**Action Required**: ✅ Install and configure PHP CS Fixer

---

## 3. Composer Security

### 3.1 Composer Validate

**Status**: ✅ Built-in (no setup needed)

**Purpose**: Validate composer.json syntax and structure

**Command**:
```bash
docker exec myshop_php composer validate --strict
```

**Common Issues**:
- Missing required fields (name, description, license)
- Invalid version constraints
- Deprecated syntax

### 3.2 Composer Audit

**Status**: ✅ Built-in Composer 2.4+

**Purpose**: Check for known security vulnerabilities

**Command**:
```bash
docker exec myshop_php composer audit
```

**Configuration**: None needed (uses packagist security advisories)

**Response to Vulnerabilities**:
1. Review advisory details
2. Update vulnerable package: `composer update vendor/package`
3. If no update available, find alternative package
4. Document in PR if temporary exception needed

---

## 4. Symfony Validation

### 4.1 Container Linter

**Status**: ✅ Built-in Symfony

**Purpose**: Validate dependency injection container

**Command**:
```bash
docker exec myshop_php php bin/console lint:container
```

**Common Issues**:
- Service doesn't exist
- Circular dependencies
- Missing class type-hints
- Autoconfiguration issues

**Fix**: Properly configure `services.yaml` and add type-hints

### 4.2 YAML Linter

**Status**: ✅ Built-in Symfony

**Purpose**: Validate YAML syntax in config files

**Command**:
```bash
docker exec myshop_php php bin/console lint:yaml config/
```

**Common Issues**:
- Invalid indentation (must be 2 or 4 spaces, consistent)
- Duplicate keys
- Invalid service references

### 4.3 Twig Linter

**Status**: ✅ Built-in Symfony

**Purpose**: Validate Twig template syntax

**Command**:
```bash
docker exec myshop_php php bin/console lint:twig templates/
```

**Common Issues**:
- Undefined variables
- Invalid filters/functions
- Incorrect include/extends paths

**Recommendation**: Use Twig Language Server in IDE for real-time validation

### 4.4 Router Linter

**Status**: ✅ Built-in Symfony

**Purpose**: Validate route definitions

**Command**:
```bash
docker exec myshop_php php bin/console lint:router
```

**Common Issues**:
- Controller method doesn't exist
- Invalid parameter constraints
- Route conflicts (same path, same methods)

---

## 5. Security Tooling

### 5.1 Git Secrets Detection

**Status**: ⚠️ REQUIRES SETUP

**Purpose**: Prevent committing secrets

**Option 1: git-secrets (recommended)**

```bash
# Install git-secrets
brew install git-secrets  # Mac
apt-get install git-secrets  # Linux

# Configure for repository
git secrets --install
git secrets --register-aws
git secrets --add 'api[_-]?key'
git secrets --add 'password'
git secrets --add 'secret'
git secrets --add '[a-zA-Z0-9]{32,}'  # Generic token pattern
```

**Option 2: Manual grep (already in quality-gates.sh)**

```bash
git diff --cached | grep -iE "(api[_-]?key|password|secret|token|credential)"
```

**Action Required**: ⚠️ Consider installing git-secrets for team

### 5.2 Pre-commit Hooks

**Status**: ⚠️ OPTIONAL (recommended)

**Purpose**: Run quality gates automatically before commit

**Installation**:

Create `.git/hooks/pre-commit`:

```bash
#!/bin/bash
# Pre-commit hook - Run basic quality gates

echo "🔍 Running pre-commit quality gates..."

# Run quality gates script
bash scripts/quality-gates.sh

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Commit blocked - Fix quality gate failures above"
    exit 1
fi

echo "✅ Pre-commit checks passed"
exit 0
```

Make executable:
```bash
chmod +x .git/hooks/pre-commit
```

**Action Required**: ⚠️ Optional but recommended for team

---

## 6. Database Migration Tools

### 6.1 Doctrine Migrations

**Status**: ✅ Installed

**Purpose**: Version-controlled database changes

**Verification**:
```bash
docker exec myshop_php php bin/console doctrine:migrations:status
```

**Best Practices**:
- Always test migrations in isolated environment first
- Write reversible migrations (implement `down()` method)
- Never drop columns with data without backup
- Add indexes for foreign keys and frequently queried columns

### 6.2 Migration Testing Script

**Status**: ⚠️ RECOMMENDED

**Purpose**: Automated migration testing

Create `scripts/test-migration.sh`:

```bash
#!/bin/bash
# Test migration in isolated Docker environment

set -e

echo "🧪 Testing database migration..."

# Backup current database
docker exec myshop_mysql mysqldump -uroot -prootpassword myshop > backup_pre_migration.sql

# Run migration
docker exec myshop_php php bin/console doctrine:migrations:migrate --no-interaction

echo "✅ Migration UP successful"

# Test rollback
docker exec myshop_php php bin/console doctrine:migrations:migrate prev --no-interaction

echo "✅ Migration DOWN successful"

# Restore original state
docker exec -i myshop_mysql mysql -uroot -prootpassword myshop < backup_pre_migration.sql

echo "✅ Database restored"
rm backup_pre_migration.sql
```

**Action Required**: ⚠️ Create migration testing script

---

## 7. Performance Monitoring

### 7.1 Symfony Profiler

**Status**: ✅ Enabled in dev environment

**Purpose**: Monitor database queries, performance metrics

**Access**:
```
http://localhost/_profiler
```

**Key Metrics to Check**:
- Database query count (should be minimal)
- Duplicate queries (should be zero)
- Query execution time (< 100ms per query ideal)
- Memory usage (< 128MB typical)
- Request time (< 500ms ideal)

**Configuration**: `config/packages/dev/web_profiler.yaml`

### 7.2 Doctrine Query Logging

**Status**: ✅ Available in dev

**Purpose**: Identify N+1 queries

**Enable in dev** (`config/packages/dev/doctrine.yaml`):

```yaml
doctrine:
    dbal:
        logging: true
        profiling: true
```

**Check logs**:
```bash
docker exec myshop_php tail -f var/log/dev.log | grep "SELECT"
```

---

## 8. CI/CD Configuration

### 8.1 GitHub Actions / GitLab CI

**Status**: ⚠️ REQUIRES SETUP

**Purpose**: Automated quality gate validation on PR

**Example GitHub Actions** (`.github/workflows/quality-gates.yml`):

```yaml
name: Quality Gates

on:
  pull_request:
    branches: [main, develop]

jobs:
  quality-gates:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: rootpassword
          MYSQL_DATABASE: myshop_test
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s
      
      mongodb:
        image: mongo:7.0
        env:
          MONGO_INITDB_ROOT_USERNAME: root
          MONGO_INITDB_ROOT_PASSWORD: rootpassword
        ports:
          - 27017:27017
      
      redis:
        image: redis:7
        ports:
          - 6379:6379

    steps:
      - uses: actions/checkout@v4
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, xml, mysql, mongodb, redis, pcov
          coverage: pcov
      
      - name: Install dependencies
        run: composer install --prefer-dist --no-progress
      
      - name: Run Quality Gates
        run: |
          # Tests
          vendor/bin/phpunit
          
          # Coverage
          vendor/bin/phpunit --coverage-clover coverage.xml
          
          # Static Analysis
          vendor/bin/phpstan analyse
          
          # Code Style
          vendor/bin/php-cs-fixer fix --dry-run --diff
          
          # Composer
          composer validate --strict
          composer audit
          
          # Symfony
          php bin/console lint:container
          php bin/console lint:yaml config/
          php bin/console lint:twig templates/
          php bin/console lint:router
      
      - name: Upload coverage
        uses: codecov/codecov-action@v3
        with:
          files: ./coverage.xml
```

**Action Required**: ⚠️ Setup CI/CD pipeline

### 8.2 Branch Protection Rules

**Status**: ⚠️ REQUIRES SETUP

**Purpose**: Enforce quality gates before merge

**GitHub Settings** → **Branches** → **Branch protection rules**:

- ✅ Require pull request reviews before merging
- ✅ Require status checks to pass before merging:
  - `quality-gates` job
  - `tests` job
  - `phpstan` job
  - `code-style` job
- ✅ Require branches to be up to date before merging
- ✅ Require conversation resolution before merging
- ✅ Do not allow bypassing the above settings

**Action Required**: ⚠️ Configure branch protection

---

## 9. Development Environment

### 9.1 Docker Configuration

**Status**: ✅ Configured

**Requirements**:
- Docker 20+
- Docker Compose 2+
- PHP 8.3 container with all extensions
- MySQL 8.0
- MongoDB 7.0
- Redis 7.0
- RabbitMQ (if using queues)

**Verification**:
```bash
docker --version
docker-compose --version
docker-compose ps
```

### 9.2 IDE Configuration

**Recommended**: PhpStorm or VS Code with extensions

**PhpStorm Setup**:
1. Configure PHP interpreter (Docker)
2. Enable PHPStan inspection
3. Enable PHP CS Fixer on save
4. Configure Symfony plugin
5. Enable Doctrine support

**VS Code Extensions**:
- PHP Intelephense
- PHPStan
- PHP CS Fixer
- Symfony Support
- Twig Language 2

---

## 10. Documentation

### 10.1 Required Documentation

**Status**: ✅ Created

- [x] Constitution (.specify/memory/constitution.md)
- [x] Quality Gates Guide (docs/QUALITY_GATES.md)
- [x] Quality Gates Setup (docs/QUALITY_GATES_SETUP.md - this file)
- [x] Quality Gates Script (scripts/quality-gates.sh)
- [x] PR Template (.github/pull_request_template.md)
- [x] README with Quality Gates section

### 10.2 Team Onboarding Checklist

For new team members:

- [ ] Read Constitution v1.1.0
- [ ] Setup local development environment (Docker)
- [ ] Install all quality gate tools
- [ ] Run `make quality-gates` successfully
- [ ] Review Quality Gates guide
- [ ] Complete sample PR with all checklists
- [ ] Understand DDD architecture (Domain/Application/Infrastructure)
- [ ] Review SOLID principles examples in codebase

---

## Setup Checklist

Use this checklist to ensure all quality gates are properly configured:

### Testing
- [x] PHPUnit installed and configured
- [x] PCOV installed for coverage
- [x] Test suites defined (unit, integration, E2E)
- [ ] Coverage thresholds configured in CI
- [ ] Coverage check script created

### Static Analysis
- [ ] PHPStan installed
- [ ] PHPStan configuration file created (phpstan.neon)
- [ ] PHPStan integrated in CI
- [ ] PHP CS Fixer installed
- [ ] PHP CS Fixer configuration file created
- [ ] PHP CS Fixer integrated in CI

### Composer Security
- [x] Composer 2.4+ installed
- [x] `composer validate` working
- [x] `composer audit` working

### Symfony Validation
- [x] All Symfony linters working (container, yaml, twig, router)
- [x] Linters integrated in quality gates script

### Security
- [ ] Git secrets detection configured (optional but recommended)
- [ ] Pre-commit hooks installed (optional)
- [x] Security checks in quality-gates.sh

### Database
- [x] Doctrine migrations configured
- [ ] Migration testing script created

### Performance
- [x] Symfony Profiler enabled in dev
- [x] Doctrine query logging enabled in dev

### CI/CD
- [ ] CI pipeline configured (GitHub Actions/GitLab CI)
- [ ] Branch protection rules configured
- [ ] All quality gates automated in CI

### Documentation
- [x] All documentation created
- [ ] Team trained on quality gates
- [ ] Onboarding checklist used for new members

---

## Next Steps

### Priority 1: CRITICAL (Required for Constitution compliance)

1. **Install PHPStan**:
   ```bash
   docker exec myshop_php composer require --dev phpstan/phpstan \
       phpstan/extension-installer \
       phpstan/phpstan-symfony \
       phpstan/phpstan-doctrine
   ```

2. **Create PHPStan configuration**: `phpstan.neon` (see section 2.1)

3. **Install PHP CS Fixer**:
   ```bash
   docker exec myshop_php composer require --dev friendsofphp/php-cs-fixer
   ```

4. **Create PHP CS Fixer configuration**: `.php-cs-fixer.dist.php` (see section 2.2)

5. **Test all quality gates**:
   ```bash
   make quality-gates
   ```

### Priority 2: HIGH (Automation)

6. **Setup CI/CD Pipeline**: Create `.github/workflows/quality-gates.yml`

7. **Configure Branch Protection**: Enable in GitHub/GitLab settings

8. **Create coverage check script**: `scripts/check-coverage.php`

### Priority 3: MEDIUM (Recommended)

9. **Install git-secrets**: For secret detection

10. **Setup pre-commit hooks**: Automate local validation

11. **Create migration testing script**: `scripts/test-migration.sh`

### Priority 4: LOW (Nice to have)

12. **IDE configuration guide**: PhpStorm/VS Code setup

13. **Team training session**: Walk through quality gates

14. **Update onboarding docs**: Include quality gates setup

---

## Troubleshooting

### Issue: PHPStan not installed

```bash
docker exec myshop_php composer show | grep phpstan
# If empty, install
docker exec myshop_php composer require --dev phpstan/phpstan
```

### Issue: PHP CS Fixer not installed

```bash
docker exec myshop_php composer show | grep php-cs-fixer
# If empty, install
docker exec myshop_php composer require --dev friendsofphp/php-cs-fixer
```

### Issue: PCOV not enabled

```bash
docker exec myshop_php php -m | grep pcov
# If empty, rebuild Dockerfile with PCOV
docker-compose build --no-cache
```

### Issue: Symfony linters fail

```bash
# Clear cache
docker exec myshop_php php bin/console cache:clear

# Rebuild container
docker exec myshop_php php bin/console cache:warmup
```

---

## Resources

- **Constitution**: [../.specify/memory/constitution.md](../.specify/memory/constitution.md)
- **Quality Gates Guide**: [QUALITY_GATES.md](QUALITY_GATES.md)
- **PHPStan Documentation**: https://phpstan.org/
- **PHP CS Fixer**: https://github.com/PHP-CS-Fixer/PHP-CS-Fixer
- **Symfony Best Practices**: https://symfony.com/doc/current/best_practices.html
- **Doctrine Best Practices**: https://www.doctrine-project.org/projects/doctrine-orm/en/current/reference/best-practices.html

---

**Status Summary**:
- ✅ Ready: 60%
- ⚠️ Requires Setup: 40%

**Estimated Setup Time**: 2-4 hours for complete implementation

**Last Updated**: 2026-02-14  
**Version**: Aligned with Constitution v1.1.0
