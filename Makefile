.PHONY: help up down install migrate fixtures test bash logs clean

help: ## Show this help message
	@echo 'Usage: make [target]'
	@echo ''
	@echo 'Available targets:'
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-15s\033[0m %s\n", $$1, $$2}'

up: ## Start Docker containers
	docker-compose up -d
	@echo "Waiting for services to be ready..."
	@sleep 5
	@echo "Application is running at http://localhost"

down: ## Stop Docker containers
	docker-compose down

install: ## Install Composer dependencies
	docker-compose exec php composer install

migrate: ## Run database migrations
	docker-compose exec php php bin/console doctrine:migrations:migrate --no-interaction

fixtures: ## Load data fixtures
	docker-compose exec php php bin/console doctrine:fixtures:load --no-interaction

test: ## Run PHPUnit tests
	docker-compose exec php vendor/bin/phpunit

test-unit: ## Run unit tests only
	docker-compose exec php vendor/bin/phpunit --testsuite=unit

test-integration: ## Run integration tests only
	docker-compose exec php vendor/bin/phpunit --testsuite=integration

bash: ## Open bash shell in PHP container
	docker-compose exec php bash

logs: ## Tail container logs
	docker-compose logs -f

clean: ## Clean cache and logs
	docker-compose exec php php bin/console cache:clear
	docker-compose exec php rm -rf var/cache/* var/log/*

db-create: ## Create database
	docker-compose exec php php bin/console doctrine:database:create --if-not-exists

db-drop: ## Drop database
	docker-compose exec php php bin/console doctrine:database:drop --force --if-exists

db-reset: db-drop db-create migrate fixtures ## Reset database with migrations and fixtures

build: ## Build Docker images
	docker-compose build --no-cache

restart: down up ## Restart Docker containers

status: ## Show container status
	docker-compose ps

# ========================================
# Redis Commands
# ========================================

redis-cli: ## Open Redis CLI
	docker-compose exec redis redis-cli

redis-flush: ## Flush all Redis cache
	docker-compose exec redis redis-cli FLUSHALL
	@echo "Redis cache cleared"

redis-flush-db: ## Flush current Redis database
	docker-compose exec redis redis-cli FLUSHDB
	@echo "Current Redis database cleared"

redis-keys: ## Show all Redis keys
	docker-compose exec redis redis-cli KEYS '*'

redis-info: ## Show Redis info
	docker-compose exec redis redis-cli INFO

redis-monitor: ## Monitor Redis commands in real-time
	docker-compose exec redis redis-cli MONITOR

# ========================================
# MongoDB Commands
# ========================================

mongo-cli: ## Open MongoDB shell
	docker-compose exec mongodb mongosh myshop_db

mongo-stats: ## Show MongoDB statistics
	docker-compose exec mongodb mongosh myshop_db --eval "db.stats()"

mongo-collections: ## List all MongoDB collections
	docker-compose exec mongodb mongosh myshop_db --eval "db.getCollectionNames()"

mongo-drop-embeddings: ## Drop product embeddings collection
	docker-compose exec mongodb mongosh myshop_db --eval "db.product_embeddings.drop()"

mongo-drop-profiles: ## Drop user profiles collection
	docker-compose exec mongodb mongosh myshop_db --eval "db.user_profiles.drop()"

mongo-count-embeddings: ## Count product embeddings
	docker-compose exec mongodb mongosh myshop_db --eval "db.product_embeddings.countDocuments()"

mongo-count-profiles: ## Count user profiles
	docker-compose exec mongodb mongosh myshop_db --eval "db.user_profiles.countDocuments()"

mongo-backup: ## Backup MongoDB database
	docker-compose exec mongodb mongodump --db=myshop_db --out=/tmp/backup
	@echo "MongoDB backup created in /tmp/backup"

mongo-indexes: ## Show MongoDB indexes
	docker-compose exec mongodb mongosh myshop_db --eval "db.product_embeddings.getIndexes()"

# ========================================
# MySQL Commands
# ========================================

mysql-cli: ## Open MySQL CLI
	docker-compose exec mysql mysql -uroot -proot myshop_db

mysql-dump: ## Dump MySQL database
	docker-compose exec mysql mysqldump -uroot -proot myshop_db > backup_$$(date +%Y%m%d_%H%M%S).sql
	@echo "MySQL dump created"

mysql-import: ## Import MySQL dump (usage: make mysql-import FILE=backup.sql)
	docker-compose exec -T mysql mysql -uroot -proot myshop_db < $(FILE)

mysql-tables: ## List all MySQL tables
	docker-compose exec mysql mysql -uroot -proot myshop_db -e "SHOW TABLES;"

mysql-status: ## Show MySQL status
	docker-compose exec mysql mysql -uroot -proot -e "SHOW STATUS;"

mysql-processes: ## Show MySQL processes
	docker-compose exec mysql mysql -uroot -proot -e "SHOW PROCESSLIST;"

mysql-size: ## Show database size
	docker-compose exec mysql mysql -uroot -proot -e "SELECT table_schema AS 'Database', ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'Size (MB)' FROM information_schema.TABLES WHERE table_schema = 'myshop_db' GROUP BY table_schema;"

# ========================================
# Symfony Commands
# ========================================

symfony-cache-clear: ## Clear Symfony cache
	docker-compose exec php php bin/console cache:clear
	docker-compose exec php php bin/console cache:warmup
	@echo "Symfony cache cleared and warmed up"

symfony-cache-pools: ## List Symfony cache pools
	docker-compose exec php php bin/console cache:pool:list

symfony-cache-clear-pool: ## Clear specific cache pool (usage: make symfony-cache-clear-pool POOL=cache.app)
	docker-compose exec php php bin/console cache:pool:clear $(POOL)

symfony-routes: ## List all Symfony routes
	docker-compose exec php php bin/console debug:router

symfony-services: ## List all Symfony services
	docker-compose exec php php bin/console debug:container

symfony-config: ## Dump Symfony configuration
	docker-compose exec php php bin/console debug:config

symfony-env: ## Show Symfony environment variables
	docker-compose exec php php bin/console debug:container --env-vars

symfony-events: ## List Symfony events
	docker-compose exec php php bin/console debug:event-dispatcher

symfony-messenger-consume: ## Consume Symfony messenger messages
	docker-compose exec php php bin/console messenger:consume async -vv

symfony-messenger-failed: ## Show failed Symfony messenger messages
	docker-compose exec php php bin/console messenger:failed:show

symfony-messenger-retry: ## Retry failed Symfony messenger messages
	docker-compose exec php php bin/console messenger:failed:retry

symfony-assets-install: ## Install Symfony assets
	docker-compose exec php php bin/console assets:install public --symlink

# ========================================
# PHP Commands
# ========================================

php-version: ## Show PHP version
	docker-compose exec php php -v

php-info: ## Show PHP info
	docker-compose exec php php -i

php-modules: ## List PHP modules
	docker-compose exec php php -m

php-composer-update: ## Update Composer dependencies
	docker-compose exec php composer update

php-composer-dump: ## Dump Composer autoload
	docker-compose exec php composer dump-autoload

php-cs-fixer: ## Run PHP CS Fixer
	docker-compose exec php vendor/bin/php-cs-fixer fix src

php-stan: ## Run PHPStan static analysis
	docker-compose exec php vendor/bin/phpstan analyse src tests

php-rector: ## Run Rector
	docker-compose exec php vendor/bin/rector process src

# ========================================
# Database Commands
# ========================================

db-validate: ## Validate database schema
	docker-compose exec php php bin/console doctrine:schema:validate

db-update: ## Update database schema (use with caution!)
	docker-compose exec php php bin/console doctrine:schema:update --force

db-diff: ## Generate migration diff
	docker-compose exec php php bin/console doctrine:migrations:diff

db-status: ## Show migration status
	docker-compose exec php php bin/console doctrine:migrations:status

db-execute: ## Execute specific migration (usage: make db-execute VERSION=Version20240101120000)
	docker-compose exec php php bin/console doctrine:migrations:execute $(VERSION)

# ========================================
# Testing Commands
# ========================================

test-coverage: ## Run tests with coverage report
	docker-compose exec php vendor/bin/phpunit --coverage-html var/coverage

test-coverage-text: ## Run tests with coverage (text format)
	docker-compose exec php vendor/bin/phpunit --coverage-text

test-filter: ## Run specific test (usage: make test-filter TEST=UserTest)
	docker-compose exec php vendor/bin/phpunit --filter $(TEST)

test-watch: ## Watch tests (requires phpunit-watcher)
	docker-compose exec php vendor/bin/phpunit-watcher watch

# ========================================
# Application Specific Commands
# ========================================

app-search-index: ## Create search indexes in MongoDB
	docker-compose exec php php bin/console app:product-embeddings:create-indexes

app-sync-embeddings: ## Sync product embeddings
	docker-compose exec php php bin/console app:product-embeddings:sync

app-refresh-profiles: ## Refresh user profiles
	docker-compose exec php php bin/console app:user-profile:refresh --all

app-test-profile: ## Test profile flow (usage: make app-test-profile EMAIL=user@example.com)
	docker-compose exec php php bin/console app:test-profile-flow $(EMAIL)

app-simulate-search: ## Simulate search for user (usage: make app-simulate-search EMAIL=user@example.com)
	docker-compose exec php php bin/console app:simulate-search $(EMAIL)

# ========================================
# Admin Commands
# ========================================

admin-create-user: ## Create admin user
	docker-compose exec php php bin/console app:create-admin

admin-list-users: ## List all users
	docker-compose exec php php bin/console app:list-users

# ========================================
# Maintenance Commands
# ========================================

flush-all: redis-flush symfony-cache-clear ## Flush all caches (Redis + Symfony)
	@echo "All caches flushed"

full-reset: down clean up db-reset redis-flush mongo-drop-embeddings mongo-drop-profiles ## Full application reset
	@echo "Full reset completed"

health-check: ## Check health of all services
	@echo "Checking services health..."
	@docker-compose ps
	@echo "\nRedis status:"
	@docker-compose exec redis redis-cli PING || echo "Redis not responding"
	@echo "\nMySQL status:"
	@docker-compose exec mysql mysqladmin -uroot -proot ping || echo "MySQL not responding"
	@echo "\nMongoDB status:"
	@docker-compose exec mongodb mongosh --eval "db.adminCommand('ping')" || echo "MongoDB not responding"
	@echo "\nPHP status:"
	@docker-compose exec php php -v | head -n 1 || echo "PHP not responding"
