#!/bin/bash
#
# Git pre-commit hook for MyShop Quality Gates
# Runs lightweight checks before allowing commit
#
# Installation:
#   chmod +x scripts/pre-commit-hook.sh
#   ln -sf ../../scripts/pre-commit-hook.sh .git/hooks/pre-commit
#
# To bypass (emergency only): git commit --no-verify
#

set -e

echo "🔍 Running pre-commit quality checks..."

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Get list of staged PHP files
STAGED_PHP_FILES=$(git diff --cached --name-only --diff-filter=ACM | grep '\.php$' || true)

if [ -z "$STAGED_PHP_FILES" ]; then
    echo -e "${GREEN}✓${NC} No PHP files to check"
    exit 0
fi

echo -e "${YELLOW}→${NC} Checking staged PHP files..."

# 1. PHP Syntax Check
echo -e "\n${YELLOW}1/4${NC} PHP Syntax Check..."
for FILE in $STAGED_PHP_FILES; do
    php -l "$FILE" > /dev/null 2>&1 || {
        echo -e "${RED}✗ PHP syntax error in $FILE${NC}"
        exit 1
    }
done
echo -e "${GREEN}✓${NC} PHP syntax valid"

# 2. Secret Detection (Critical patterns only)
echo -e "\n${YELLOW}2/4${NC} Secret Detection..."
SECRET_PATTERNS=(
    "password\s*=\s*['\"][^'\"]+['\"]"
    "api_key\s*=\s*['\"][^'\"]+['\"]"
    "secret\s*=\s*['\"][^'\"]+['\"]"
    "token\s*=\s*['\"][^'\"]+['\"]"
    "BEGIN (RSA|DSA|EC|OPENSSH) PRIVATE KEY"
)

for FILE in $STAGED_PHP_FILES; do
    for PATTERN in "${SECRET_PATTERNS[@]}"; do
        if grep -iE "$PATTERN" "$FILE" > /dev/null 2>&1; then
            echo -e "${RED}✗ Potential secret detected in $FILE${NC}"
            echo -e "${YELLOW}  Pattern: $PATTERN${NC}"
            echo -e "${YELLOW}  Use 'git commit --no-verify' to bypass (NOT RECOMMENDED)${NC}"
            exit 1
        fi
    done
done
echo -e "${GREEN}✓${NC} No secrets detected"

# 3. Debug Statements Check
echo -e "\n${YELLOW}3/4${NC} Debug Statement Detection..."
DEBUG_PATTERNS=(
    "var_dump\("
    "dd\("
    "dump\("
    "print_r\("
    "error_log\("
    "console\.log\("
)

for FILE in $STAGED_PHP_FILES; do
    for PATTERN in "${DEBUG_PATTERNS[@]}"; do
        if grep -n "$PATTERN" "$FILE" > /dev/null 2>&1; then
            echo -e "${YELLOW}⚠ Debug statement found in $FILE:${NC}"
            grep -n "$PATTERN" "$FILE" | head -3
            echo -e "${YELLOW}  Consider removing debug code before committing${NC}"
            echo -e "${YELLOW}  To commit anyway, use: git commit --no-verify${NC}"
            # Warning only, don't block
        fi
    done
done
echo -e "${GREEN}✓${NC} Debug check complete"

# 4. TODO/FIXME Detection
echo -e "\n${YELLOW}4/4${NC} TODO/FIXME Detection..."
TODO_COUNT=$(grep -En 'TODO|FIXME' $STAGED_PHP_FILES | wc -l || true)
if [ "$TODO_COUNT" -gt 0 ]; then
    echo -e "${YELLOW}⚠ Found $TODO_COUNT TODO/FIXME comments${NC}"
    grep -Hn 'TODO|FIXME' $STAGED_PHP_FILES | head -5 || true
    echo -e "${YELLOW}  Remember to address these before merging to main${NC}"
fi

echo -e "\n${GREEN}✓ Pre-commit checks passed!${NC}"
echo -e "${YELLOW}→${NC} Run 'make quality-gates' before pushing for full validation"
echo ""

exit 0
