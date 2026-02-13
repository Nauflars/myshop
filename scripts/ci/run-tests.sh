#!/bin/bash
# Helper script to run test suites with proper exit codes
# Usage: ./run-tests.sh [unit|integration|all]

set -e

TEST_SUITE=${1:-all}
PROJECT_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)

cd "$PROJECT_DIR"

echo "======================================"
echo "Running tests: $TEST_SUITE"
echo "======================================"
echo ""

# Ensure log directory exists
mkdir -p var/log/phpunit

# Run tests based on suite
case $TEST_SUITE in
    unit)
        echo "→ Running unit tests..."
        php bin/phpunit --testsuite=unit \
            --log-junit var/log/phpunit/unit-results.xml \
            --coverage-text
        ;;
    
    integration)
        echo "→ Running integration tests..."
        php bin/phpunit --testsuite=integration \
            --log-junit var/log/phpunit/integration-results.xml
        ;;
    
    all)
        echo "→ Running all tests..."
        php bin/phpunit \
            --log-junit var/log/phpunit/all-results.xml \
            --coverage-text
        ;;
    
    *)
        echo "Error: Unknown test suite '$TEST_SUITE'"
        echo "Usage: $0 [unit|integration|all]"
        exit 1
        ;;
esac

EXIT_CODE=$?

echo ""
if [ $EXIT_CODE -eq 0 ]; then
    echo "✓ Tests passed!"
else
    echo "✗ Tests failed!"
fi

exit $EXIT_CODE
