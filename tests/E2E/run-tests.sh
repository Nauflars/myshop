#!/bin/bash
# Script para ejecutar tests E2E desde WSL

cd /var/www2/myshop/tests/E2E

echo "==================================="
echo "MyShop E2E Tests Runner"
echo "==================================="

# Check if node_modules exists
if [ ! -d "node_modules" ]; then
    echo "❌ node_modules not found. Installing dependencies..."
    npm install
fi

# Check if browsers are installed
if [ ! -d "$HOME/.cache/ms-playwright" ]; then
    echo "📦 Installing Playwright browsers..."
    npx playwright install chromium
fi

echo ""
echo "🧪 Running E2E Tests..."
echo ""

# Run tests with simpler configuration
npx playwright test "$@" --reporter=list --timeout=30000

echo ""
echo "✅ Tests completed!"
