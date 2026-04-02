.PHONY: help setup up down restart logs shell mysql redis test fresh seed

DOCKER_COMPOSE = docker compose

help: ## Show this help
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

setup: ## Initial project setup (first time only)
	$(DOCKER_COMPOSE) build --no-cache
	$(DOCKER_COMPOSE) run --rm app composer install
	$(DOCKER_COMPOSE) run --rm app cp -n .env.example .env || true
	$(DOCKER_COMPOSE) run --rm app php artisan key:generate
	$(DOCKER_COMPOSE) up -d
	$(DOCKER_COMPOSE) exec app php artisan migrate --force
	@echo "\n✅ Setup complete! Access the app at http://localhost:8080"

up: ## Start all containers
	$(DOCKER_COMPOSE) up -d

down: ## Stop all containers
	$(DOCKER_COMPOSE) down

restart: ## Restart all containers
	$(DOCKER_COMPOSE) restart

logs: ## View container logs
	$(DOCKER_COMPOSE) logs -f

shell: ## Open a shell in the app container
	$(DOCKER_COMPOSE) exec app sh

mysql: ## Open MySQL CLI
	$(DOCKER_COMPOSE) exec mysql mysql -uapp_user -papp_password order_orchestrator

redis: ## Open Redis CLI
	$(DOCKER_COMPOSE) exec redis redis-cli

test: ## Run tests
	$(DOCKER_COMPOSE) exec app php artisan test

fresh: ## Fresh migration + seed
	$(DOCKER_COMPOSE) exec app php artisan migrate:fresh --seed

seed: ## Run seeders
	$(DOCKER_COMPOSE) exec app php artisan db:seed

composer-install: ## Install composer dependencies
	$(DOCKER_COMPOSE) run --rm app composer install

artisan: ## Run artisan command (usage: make artisan cmd="migrate")
	$(DOCKER_COMPOSE) exec app php artisan $(cmd)
