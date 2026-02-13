#!/bin/bash
# Docker container health check script
# Verifies all required containers are running and healthy

set -e

echo "======================================"
echo "Docker Container Health Check"
echo "======================================"
echo ""

# Function to check container status
check_container() {
    local container_name=$1
    local description=$2
    
    echo -n "Checking $description ($container_name)... "
    
    # Check if container is running
    if ! docker ps --format '{{.Names}}' | grep -q "^${container_name}$"; then
        echo "✗ FAIL (not running)"
        return 1
    fi
    
    # Check container health status
    health_status=$(docker inspect --format='{{.State.Health.Status}}' "$container_name" 2>/dev/null || echo "none")
    
    if [ "$health_status" = "healthy" ] || [ "$health_status" = "none" ]; then
        echo "✓ PASS (running, health: $health_status)"
        return 0
    else
        echo "✗ FAIL (health: $health_status)"
        return 1
    fi
}

# Track failures
FAILURES=0

# Check Jenkins
check_container "myshop-jenkins" "Jenkins CI" || ((FAILURES++))

# Check test environment
check_container "myshop-test" "Test Environment" || ((FAILURES++))
check_container "myshop-mysql-test" "Test MySQL" || ((FAILURES++))
check_container "myshop-redis-test" "Test Redis" || ((FAILURES++))
check_container "myshop-mongodb-test" "Test MongoDB" || ((FAILURES++))
check_container "myshop-rabbitmq-test" "Test RabbitMQ" || ((FAILURES++))

# Check production environment
check_container "myshop-prod" "Production Environment" || ((FAILURES++))
check_container "myshop-mysql-prod" "Production MySQL" || ((FAILURES++))
check_container "myshop-redis-prod" "Production Redis" || ((FAILURES++))
check_container "myshop-mongodb-prod" "Production MongoDB" || ((FAILURES++))
check_container "myshop-rabbitmq-prod" "Production RabbitMQ" || ((FAILURES++))

echo ""
echo "======================================"
if [ $FAILURES -eq 0 ]; then
    echo "✓ All containers are healthy!"
    echo "======================================"
    exit 0
else
    echo "✗ $FAILURES container(s) unhealthy or not running"
    echo "======================================"
    echo ""
    echo "To start all containers:"
    echo "  docker-compose -f docker-compose.ci.yml up -d"
    exit 1
fi
