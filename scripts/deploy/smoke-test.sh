#!/bin/bash
# Smoke test script for post-deployment health verification
# Usage: ./smoke-test.sh [test|production]

set -e

ENVIRONMENT=${1:-test}
BASE_URL=""

# Determine base URL based on environment
if [ "$ENVIRONMENT" = "test" ]; then
    BASE_URL="http://localhost:8081"
elif [ "$ENVIRONMENT" = "production" ]; then
    BASE_URL="http://localhost:8082"
else
    echo "Error: Invalid environment. Use 'test' or 'production'"
    exit 1
fi

echo "======================================"
echo "Running smoke tests for: $ENVIRONMENT"
echo "Base URL: $BASE_URL"
echo "======================================"
echo ""

# Function to check HTTP endpoint
check_endpoint() {
    local endpoint=$1
    local expected_status=${2:-200}
    local description=$3
    
    echo -n "Checking $description... "
    
    response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL$endpoint" --max-time 10)
    
    if [ "$response" = "$expected_status" ]; then
        echo "✓ PASS (HTTP $response)"
        return 0
    else
        echo "✗ FAIL (HTTP $response, expected $expected_status)"
        return 1
    fi
}

# Track failures
FAILURES=0

# Overall health check
check_endpoint "/health" 200 "Overall health" || ((FAILURES++))

# Database connectivity
check_endpoint "/api/health/database" 200 "MySQL database" || ((FAILURES++))

# MongoDB connectivity
check_endpoint "/api/health/mongodb" 200 "MongoDB" || ((FAILURES++))

# Redis connectivity
check_endpoint "/api/health/redis" 200 "Redis cache" || ((FAILURES++))

# RabbitMQ connectivity
check_endpoint "/api/health/rabbitmq" 200 "RabbitMQ queue" || ((FAILURES++))

# Disk space check
check_endpoint "/api/health/disk" 200 "Disk space" || ((FAILURES++))

# Critical application endpoints (if they exist)
check_endpoint "/" 200 "Homepage" || ((FAILURES++))

echo ""
echo "======================================"
if [ $FAILURES -eq 0 ]; then
    echo "✓ All smoke tests passed!"
    echo "======================================"
    exit 0
else
    echo "✗ $FAILURES smoke test(s) failed"
    echo "======================================"
    exit 1
fi
