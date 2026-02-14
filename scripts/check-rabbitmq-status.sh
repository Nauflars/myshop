#!/bin/bash
# RabbitMQ Queue Status Check
# Quick verification script for RabbitMQ queues

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo "========================================"
echo "  RabbitMQ Queue Status Check"
echo "========================================"
echo ""

# Configuration
RABBITMQ_HOST="${RABBITMQ_HOST:-localhost}"
RABBITMQ_PORT="${RABBITMQ_PORT:-15672}"
RABBITMQ_USER="${RABBITMQ_USER:-myshop_user}"
RABBITMQ_PASS="${RABBITMQ_PASSWORD:-myshop_pass}"
RABBITMQ_VHOST="${RABBITMQ_VHOST:-%2F}"

API_URL="http://${RABBITMQ_HOST}:${RABBITMQ_PORT}/api"

# Check if RabbitMQ is accessible
echo "Checking RabbitMQ connectivity..."
if ! curl -s -f -u "${RABBITMQ_USER}:${RABBITMQ_PASS}" "${API_URL}/overview" > /dev/null 2>&1; then
    echo -e "${RED}✗ ERROR: Cannot connect to RabbitMQ Management API${NC}"
    echo "  URL: ${API_URL}"
    echo "  Make sure RabbitMQ is running: docker-compose ps rabbitmq"
    exit 1
fi

echo -e "${GREEN}✓ RabbitMQ is accessible${NC}"
echo ""

# Get queue statistics
echo "=== Queue Statistics ==="
echo ""

QUEUES=$(curl -s -u "${RABBITMQ_USER}:${RABBITMQ_PASS}" "${API_URL}/queues/${RABBITMQ_VHOST}")

# Parse and display queue stats
echo "$QUEUES" | jq -r '.[] | "Queue: \(.name)\n  Messages: \(.messages // 0)\n  Ready: \(.messages_ready // 0)\n  Unacked: \(.messages_unacknowledged // 0)\n  Consumers: \(.consumers // 0)\n"' 2>/dev/null || {
    echo -e "${YELLOW}⚠ jq not installed, showing raw output:${NC}"
    echo "$QUEUES"
}

# Check for issues
FAILED_COUNT=$(echo "$QUEUES" | jq '[.[] | select(.name == "failed") | .messages // 0] | add // 0' 2>/dev/null)
TOTAL_MESSAGES=$(echo "$QUEUES" | jq '[.[] | .messages // 0] | add // 0' 2>/dev/null)

echo "=== Summary ==="
echo "Total Messages in All Queues: ${TOTAL_MESSAGES:-N/A}"
echo "Failed Messages (DLQ): ${FAILED_COUNT:-N/A}"
echo ""

# Warnings
if [ "${FAILED_COUNT:-0}" -gt 0 ]; then
    echo -e "${RED}⚠ WARNING: ${FAILED_COUNT} failed messages in DLQ!${NC}"
    echo "  Run: docker-compose exec php php bin/console messenger:failed:retry --force"
    echo ""
fi

# Check if workers are running
echo "=== Worker Status ==="
WORKER_STATUS=$(docker-compose ps worker 2>/dev/null | grep -i "up" || echo "")
if [ -n "$WORKER_STATUS" ]; then
    echo -e "${GREEN}✓ Worker container is running${NC}"
    echo ""
    echo "Recent worker logs:"
    docker-compose logs --tail=5 worker 2>/dev/null || echo "Cannot fetch worker logs"
else
    echo -e "${RED}✗ Worker container is NOT running${NC}"
    echo "  Start with: docker-compose up -d worker"
fi

echo ""
echo "=== RabbitMQ Management UI ==="
echo "URL: http://${RABBITMQ_HOST}:${RABBITMQ_PORT}"
echo "Username: ${RABBITMQ_USER}"
echo "Password: ${RABBITMQ_PASS}"
echo ""
echo "========================================"
