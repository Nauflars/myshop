#!/bin/bash
# Quality Gates Pre-Push Validation Script
# Runs all mandatory checks defined in Constitution v1.1.0 before pushing code
# Usage: ./scripts/quality-gates.sh

set -e  # Exit on first error

echo "========================================"
echo "🔍 MyShop Quality Gates Validator"
echo "Constitution v1.1.0"
echo "========================================"
echo ""

# Color codes
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Track failures
FAILURES=0

# Helper functions
run_check() {
    local name="$1"
    local command="$2"
    
    echo -n "⏳ $name... "
    if eval "$command" > /dev/null 2>&1; then
        echo -e "${GREEN}✓ PASS${NC}"
        return 0
    else
        echo -e "${RED}✗ FAIL${NC}"
        FAILURES=$((FAILURES + 1))
        return 1
    fi
}

run_check_with_output() {
    local name="$1"
    local command="$2"
    
    echo "⏳ $name..."
    if eval "$command"; then
        echo -e "${GREEN}✓ PASS${NC}"
        echo ""
        return 0
    else
        echo -e "${RED}✗ FAIL${NC}"
        echo ""
        FAILURES=$((FAILURES + 1))
        return 1
    fi
}

echo "════════════════════════════════════════"
echo "1️⃣  TESTS"
echo "════════════════════════════════════════"

run_check_with_output "Running PHPUnit test suite" \
    "docker exec myshop_php php vendor/bin/phpunit"

run_check "Checking test coverage thresholds" \
    "docker exec myshop_php php vendor/bin/phpunit --coverage-text | grep -E '(Domain|Application|Infrastructure)' | grep -vE '(0\.00%|[1-8][0-9]\.[0-9]+%)'"

echo "════════════════════════════════════════"
echo "2️⃣  STATIC ANALYSIS & CODE STYLE"
echo "════════════════════════════════════════"

run_check "PHPStan static analysis" \
    "docker exec myshop_php vendor/bin/phpstan analyse --no-progress"

run_check "PHP CS Fixer code style" \
    "docker exec myshop_php vendor/bin/php-cs-fixer fix --dry-run --diff --verbose"

run_check "Composer validate" \
    "docker exec myshop_php composer validate --strict"

run_check "Composer audit (security vulnerabilities)" \
    "docker exec myshop_php composer audit"

echo "════════════════════════════════════════"
echo "3️⃣  SYMFONY VALIDATION"
echo "════════════════════════════════════════"

run_check "Lint: Container" \
    "docker exec myshop_php php bin/console lint:container"

run_check "Lint: YAML configuration" \
    "docker exec myshop_php php bin/console lint:yaml config/"

run_check "Lint: Twig templates" \
    "docker exec myshop_php php bin/console lint:twig templates/"

run_check "Lint: Router" \
    "docker exec myshop_php php bin/console lint:router"

echo "════════════════════════════════════════"
echo "4️⃣  SECURITY CHECKS"
echo "════════════════════════════════════════"

# Check for common secret patterns in staged files
echo -n "⏳ Checking for secrets/credentials... "
if git diff --cached --name-only | xargs grep -inE "(api[_-]?key|password|secret|token|credential|auth[_-]?token)" 2>/dev/null; then
    echo -e "${RED}✗ FAIL - Potential secrets found!${NC}"
    FAILURES=$((FAILURES + 1))
else
    echo -e "${GREEN}✓ PASS${NC}"
fi

# Check for debug statements
echo -n "⏳ Checking for debug statements... "
if git diff --cached --name-only | xargs grep -inE "(var_dump|dd\(|dump\(|console\.log|error_log|print_r)" 2>/dev/null; then
    echo -e "${RED}✗ FAIL - Debug statements found!${NC}"
    FAILURES=$((FAILURES + 1))
else
    echo -e "${GREEN}✓ PASS${NC}"
fi

# Check for commented code
echo -n "⏳ Checking for commented-out code... "
if git diff --cached | grep -E "^\+.*//.*\$" | grep -vE "(http://|https://|TODO|FIXME)" > /dev/null; then
    echo -e "${YELLOW}⚠ WARNING - Commented code found (review needed)${NC}"
else
    echo -e "${GREEN}✓ PASS${NC}"
fi

echo ""
echo "════════════════════════════════════════"
echo "📊 SUMMARY"
echo "════════════════════════════════════════"

if [ $FAILURES -eq 0 ]; then
    echo -e "${GREEN}✓ All quality gates passed!${NC}"
    echo ""
    echo "✅ Ready to push and create Pull Request"
    echo ""
    echo "Next steps:"
    echo "  1. git push origin <branch-name>"
    echo "  2. Create Pull Request on GitHub"
    echo "  3. Fill out PR template checklist"
    echo "  4. Wait for CI/CD validation"
    exit 0
else
    echo -e "${RED}✗ $FAILURES quality gate(s) failed${NC}"
    echo ""
    echo "❌ Fix the issues above before pushing"
    echo ""
    echo "Reference: .specify/memory/constitution.md (Quality Gates section)"
    exit 1
fi
