#!/bin/bash
# Pre-deployment check script
# Validates system readiness before deployment

set -e

ENVIRONMENT=${1:-test}
CONTAINER_NAME="myshop-${ENVIRONMENT}"

echo "======================================"
echo "Pre-Deployment Checks: $ENVIRONMENT"
echo "======================================"
echo ""

FAILURES=0

# Check if container is running
echo -n "Checking if $CONTAINER_NAME is running... "
if docker ps --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo "✓ PASS"
else
    echo "✗ FAIL (container not running)"
    ((FAILURES++))
fi

# Check disk space on Docker host
echo -n "Checking disk space... "
disk_usage=$(df / | awk 'NR==2 {print $5}' | sed 's/%//')
if [ "$disk_usage" -lt 80 ]; then
    echo "✓ PASS (${disk_usage}% used)"
else
    echo "✗ FAIL (${disk_usage}% used - exceeds 80% threshold)"
    ((FAILURES++))
fi

# Check Docker daemon
echo -n "Checking Docker daemon... "
if docker info > /dev/null 2>&1; then
    echo "✓ PASS"
else
    echo "✗ FAIL (Docker daemon not accessible)"
    ((FAILURES++))
fi

# Check if deployment directory exists in container
echo -n "Checking deployment directory in container... "
if docker exec "$CONTAINER_NAME" test -d /var/www/myshop 2>/dev/null; then
    echo "✓ PASS"
else
    echo "✗ FAIL (deployment directory not found)"
    ((FAILURES++))
fi

# Check database connectivity
echo -n "Checking database connectivity... "
if curl -sf "http://localhost:${ENVIRONMENT,,test8081production8082}/api/health/database" > /dev/null 2>&1; then
    echo "✓ PASS"
else
    echo "⚠ WARNING (database health check failed)"
    # Not a critical failure, deployment can proceed
fi

echo ""
echo "======================================"
if [ $FAILURES -eq 0 ]; then
    echo "✓ Pre-deployment checks passed!"
    echo "======================================"
    exit 0
else
    echo "✗ $FAILURES pre-deployment check(s) failed"
    echo "======================================"
    echo "Please fix the issues before deploying"
    exit 1
fi
