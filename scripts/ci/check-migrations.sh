#!/bin/bash
# Check database migrations status before running tests
# Ensures database is in sync with code

set -e

PROJECT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)

cd "$PROJECT_DIR"

echo "======================================"
echo "Checking Database Migrations"
echo "======================================"
echo ""

# Check if there are pending migrations
echo "→ Checking migration status..."
php bin/console doctrine:migrations:status --no-interaction

# Get list of pending migrations
PENDING=$(php bin/console doctrine:migrations:list --no-interaction | grep -c "not migrated" || true)

if [ "$PENDING" -gt 0 ]; then
    echo ""
    echo "⚠ WARNING: $PENDING pending migration(s) found"
    echo ""
    echo "Run migrations before tests:"
    echo "  php bin/console doctrine:migrations:migrate --no-interaction"
    echo ""
    exit 1
else
    echo ""
    echo "✓ Database is up to date (no pending migrations)"
    exit 0
fi
