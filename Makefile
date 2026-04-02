.PHONY: help setup up down restart logs shell mysql redis test fresh seed

DOCKER_COMPOSE = docker compose

help: ## 도움말 표시
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-20s\033[0m %s\n", $$1, $$2}'

setup: ## 최초 프로젝트 설치 (한 번만 실행)
	$(DOCKER_COMPOSE) build --no-cache
	$(DOCKER_COMPOSE) run --rm app composer install
	$(DOCKER_COMPOSE) run --rm app cp -n .env.example .env || true
	$(DOCKER_COMPOSE) run --rm app php artisan key:generate
	$(DOCKER_COMPOSE) up -d
	$(DOCKER_COMPOSE) exec app php artisan migrate --force
	@echo "\n✅ 설치 완료! http://localhost:8080 에서 확인하세요"

up: ## 모든 컨테이너 시작
	$(DOCKER_COMPOSE) up -d

down: ## 모든 컨테이너 중지
	$(DOCKER_COMPOSE) down

restart: ## 모든 컨테이너 재시작
	$(DOCKER_COMPOSE) restart

logs: ## 컨테이너 로그 확인
	$(DOCKER_COMPOSE) logs -f

shell: ## 앱 컨테이너 쉘 접속
	$(DOCKER_COMPOSE) exec app sh

mysql: ## MySQL CLI 접속
	$(DOCKER_COMPOSE) exec mysql mysql -uapp_user -papp_password order_orchestrator

redis: ## Redis CLI 접속
	$(DOCKER_COMPOSE) exec redis redis-cli

test: ## 테스트 실행
	$(DOCKER_COMPOSE) exec app php artisan test

fresh: ## 마이그레이션 초기화 + 시드 실행
	$(DOCKER_COMPOSE) exec app php artisan migrate:fresh --seed

seed: ## 시더 실행
	$(DOCKER_COMPOSE) exec app php artisan db:seed

composer-install: ## Composer 의존성 설치
	$(DOCKER_COMPOSE) run --rm app composer install

artisan: ## Artisan 명령어 실행 (사용법: make artisan cmd="migrate")
	$(DOCKER_COMPOSE) exec app php artisan $(cmd)
