#!/bin/bash
set -e

echo "Waiting for MySQL to be ready..."
until mysql -h mysql -u root -p${MYSQL_ROOT_PASSWORD:-rootpassword} -e "SELECT 1" > /dev/null 2>&1; do
  echo "MySQL is unavailable - sleeping"
  sleep 2
done

echo "MySQL is up - executing migrations"

cd /var/www/html

# Install dependencies if needed
if [ ! -d "vendor" ]; then
    echo "Installing Composer dependencies..."
    composer install --no-interaction --optimize-autoloader
fi

# Run migrations
echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration

# Load fixtures in dev environment
if [ "${APP_ENV}" = "dev" ]; then
    echo "Loading data fixtures..."
    php bin/console doctrine:fixtures:load --no-interaction
fi

echo "Database initialization complete!"
