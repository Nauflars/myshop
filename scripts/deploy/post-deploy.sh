#!/bin/bash
# Post-deployment script
# Cleanup and optimization tasks after deployment

set -e

ENVIRONMENT=${1:-test}
CONTAINER_NAME="myshop-${ENVIRONMENT}"

echo "======================================"
echo "Post-Deployment Tasks: $ENVIRONMENT"
echo "======================================"
echo ""

echo "→ Clearing application cache..."
docker exec "$CONTAINER_NAME" php /var/www/myshop/current/bin/console cache:clear --env="$ENVIRONMENT" --no-interaction || true

echo "→ Clearing OPcache..."
docker exec "$CONTAINER_NAME" php /var/www/myshop/current/bin/console cache:pool:clear cache.global_clearer || true

echo "→ Rotating logs (keeping last 10)..."
docker exec "$CONTAINER_NAME" find /var/www/myshop/current/var/log -name "*.log" -type f -mtime +10 -delete 2>/dev/null || true

echo "→ Cleaning old sessions..."
docker exec "$CONTAINER_NAME" find /var/www/myshop/shared/var/sessions -type f -mtime +7 -delete 2>/dev/null || true

echo "→ Verifying current symlink..."
current_target=$(docker exec "$CONTAINER_NAME" readlink -f /var/www/myshop/current)
echo "   Current release: $(basename "$current_target")"

echo ""
echo "✓ Post-deployment tasks completed"
