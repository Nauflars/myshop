#!/bin/bash
# Rollback verification script
# Verifies that rollback completed successfully

set -e

ENVIRONMENT=${1:-test}
CONTAINER_NAME="myshop-${ENVIRONMENT}"

echo "======================================"
echo "Verifying Rollback: $ENVIRONMENT"
echo "======================================"
echo ""

FAILURES=0

# Check if container is running
echo -n "→ Checking container status... "
if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "✓ PASS"
else
    echo "✗ FAIL (container not running)"
    ((FAILURES++))
fi

# Verify current symlink exists
echo -n "→ Checking current symlink... "
if docker exec "$CONTAINER_NAME" test -L /var/www/myshop/current; then
    CURRENT_LINK=$(docker exec "$CONTAINER_NAME" readlink -f /var/www/myshop/current)
    RELEASE_NAME=$(basename "$CURRENT_LINK")
    echo "✓ PASS (points to: $RELEASE_NAME)"
else
    echo "✗ FAIL (symlink missing)"
    ((FAILURES++))
fi

# Verify release directory exists
echo -n "→ Checking release directory... "
if docker exec "$CONTAINER_NAME" test -d "$CURRENT_LINK"; then
    echo "✓ PASS"
else
    echo "✗ FAIL (release directory not found)"
    ((FAILURES++))
fi

# Check PHP-FPM is running
echo -n "→ Checking PHP-FPM status... "
if docker exec "$CONTAINER_NAME" service php8.3-fpm status > /dev/null 2>&1; then
    echo "✓ PASS"
else
    echo "⚠ WARNING (PHP-FPM status check failed)"
fi

# Verify application responds
echo -n "→ Checking application response... "
PORT=8081
if [ "$ENVIRONMENT" = "production" ]; then
    PORT=8082
fi

HTTP_CODE=$(curl -s -o /dev/null -w "%{http_code}" "http://localhost:${PORT}/health" --max-time 10)
if [ "$HTTP_CODE" = "200" ]; then
    echo "✓ PASS (HTTP $HTTP_CODE)"
else
    echo "✗ FAIL (HTTP $HTTP_CODE)"
    ((FAILURES++))
fi

# List recent releases
echo ""
echo "→ Recent releases:"
docker exec "$CONTAINER_NAME" ls -lt /var/www/myshop/releases | head -5

echo ""
echo "======================================"
if [ $FAILURES -eq 0 ]; then
    echo "✓ Rollback verification passed!"
    echo "======================================"
    exit 0
else
    echo "✗ $FAILURES verification check(s) failed"
    echo "======================================"
    echo ""
    echo "Manual investigation required!"
    exit 1
fi
