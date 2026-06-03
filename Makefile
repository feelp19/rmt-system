# rmt-system — Laravel (Octane/FrankenPHP API) + Nuxt 4 SSR + PrimeVue
# Run `make` or `make help` to list targets.

COMPOSE := docker compose
# APP: non-interactive exec (scripts/CI safe). APP_IT: interactive (TTY: shell, tinker).
APP     := $(COMPOSE) exec -T app
APP_IT  := $(COMPOSE) exec app
ARTISAN := $(APP) php artisan

.DEFAULT_GOAL := help
.PHONY: help up build start stop down restart ps logs logs-app logs-nuxt logs-horizon \
        migrate migrate-fresh seed fresh rollback test shell tinker artisan \
        optimize optimize-clear octane-reload key setup env down-volumes prune \
        front-install front-dev front-build front-preview

## ─── Help ───────────────────────────────────────────────────────────────────
help: ## Show this help
	@grep -E '^[a-zA-Z0-9_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-16s\033[0m %s\n", $$1, $$2}'

## ─── Docker stack ───────────────────────────────────────────────────────────
up: ## Build (if needed) and start the full stack detached
	$(COMPOSE) up --build -d

build: ## Build all images
	$(COMPOSE) build

start: ## Start existing containers
	$(COMPOSE) start

stop: ## Stop containers (keep them)
	$(COMPOSE) stop

down: ## Stop and remove containers (volumes kept)
	$(COMPOSE) down

restart: ## Restart all services
	$(COMPOSE) restart

ps: ## Show service status
	$(COMPOSE) ps

logs: ## Tail logs for all services
	$(COMPOSE) logs -f

logs-app: ## Tail Laravel/Octane (app) logs
	$(COMPOSE) logs -f app

logs-nuxt: ## Tail Nuxt SSR logs
	$(COMPOSE) logs -f nuxt

logs-horizon: ## Tail Horizon (queue worker) logs
	$(COMPOSE) logs -f horizon

## ─── Laravel (inside app container) ─────────────────────────────────────────
migrate: ## Run database migrations
	$(ARTISAN) migrate --force

migrate-fresh: ## Drop all tables and re-run migrations
	$(ARTISAN) migrate:fresh --force

seed: ## Run database seeders
	$(ARTISAN) db:seed --force

fresh: ## Fresh migrate + seed
	$(ARTISAN) migrate:fresh --seed --force

rollback: ## Roll back the last migration batch
	$(ARTISAN) migrate:rollback --force

test: ## Run the Laravel test suite
	$(ARTISAN) test

optimize: ## Cache config/routes/events (run octane-reload after)
	$(ARTISAN) config:cache && $(ARTISAN) route:cache && $(ARTISAN) event:cache

optimize-clear: ## Clear all caches
	$(ARTISAN) optimize:clear

octane-reload: ## Zero-downtime reload of Octane workers
	$(ARTISAN) octane:reload

key: ## Generate APP_KEY on the host (.env)
	php artisan key:generate

shell: ## Open a shell in the app container
	$(APP_IT) sh

tinker: ## Open Laravel Tinker
	$(APP_IT) php artisan tinker

# Pass-through: make artisan c="route:list"
artisan: ## Run any artisan command: make artisan c="route:list"
	$(ARTISAN) $(c)

## ─── First-time setup ───────────────────────────────────────────────────────
env: ## Create .env from .env.example and generate APP_KEY (host)
	@test -f .env || cp .env.example .env
	php artisan key:generate

setup: ## First run: build, start, then migrate
	$(COMPOSE) up --build -d
	@echo "waiting for app/db to be ready..."
	@sleep 8
	$(ARTISAN) migrate --force
	@echo "ready -> http://localhost"

## ─── Frontend (Nuxt, host) ──────────────────────────────────────────────────
front-install: ## Install Nuxt dependencies
	cd frontend && npm install

front-dev: ## Run Nuxt dev server (http://localhost:3000, proxies /api -> :8000)
	cd frontend && npm run dev

front-build: ## Build Nuxt for production (.output)
	cd frontend && npm run build

front-preview: ## Preview the production Nuxt build
	cd frontend && npm run preview

## ─── Cleanup (destructive) ──────────────────────────────────────────────────
down-volumes: ## Stop and remove containers AND named volumes (DB DATA LOST)
	$(COMPOSE) down -v

prune: ## Remove dangling Docker images/build cache
	docker system prune -f
