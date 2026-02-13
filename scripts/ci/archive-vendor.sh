#!/bin/bash
# Archive vendor directory for faster deployments
# This creates a tar.gz of vendor/ to avoid re-downloading dependencies

set -e

PROJECT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)

cd "$PROJECT_DIR"

echo "======================================"
echo "Archiving Vendor Directory"
echo "======================================"
echo ""

# Create artifacts directory
mkdir -p artifacts

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "⚠ vendor directory not found, skipping archive"
    exit 0
fi

ARCHIVE_NAME="vendor-$(git rev-parse --short HEAD)-$(date +%Y%m%d%H%M%S).tar.gz"

echo "→ Creating archive: $ARCHIVE_NAME..."
tar -czf "artifacts/$ARCHIVE_NAME" vendor/ --exclude=vendor/bin/.phpunit

ARCHIVE_SIZE=$(du -h "artifacts/$ARCHIVE_NAME" | cut -f1)
echo "→ Archive size: $ARCHIVE_SIZE"

# Create symlink to latest
ln -sf "$ARCHIVE_NAME" artifacts/vendor-latest.tar.gz

echo ""
echo "✓ Vendor archive created: artifacts/$ARCHIVE_NAME"
