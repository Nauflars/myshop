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
