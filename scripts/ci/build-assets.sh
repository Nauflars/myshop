#!/bin/bash
# Build and compile frontend assets
# This script handles JavaScript/CSS compilation, minification, and optimization

set -e

PROJECT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)

cd "$PROJECT_DIR"

echo "======================================"
echo "Building Frontend Assets"
echo "======================================"
echo ""

# Check if Node.js is available
if ! command -v node &> /dev/null; then
    echo "⚠ Node.js not found, skipping asset build"
    exit 0
fi

# Check if package.json exists
if [ ! -f "package.json" ]; then
    echo "⚠ package.json not found, skipping asset build"
    exit 0
fi

echo "→ Installing npm dependencies..."
npm ci --silent

echo "→ Building assets..."
if [ -f "webpack.config.js" ]; then
    npm run build --if-present
elif [ -f "vite.config.js" ]; then
    npm run build --if-present
else
    echo "⚠ No build configuration found"
fi

echo "→ Optimizing images (if available)..."
# Add image optimization here if needed
# e.g., imagemin, sharp, etc.

echo ""
echo "✓ Asset build completed!"
