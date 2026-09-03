# Makefile for Symfony 8.1 Demo (PDF Editor Bundle)

.PHONY: help up down build install shell logs test test-coverage cache-clear update-bundle ensure-up verify assets assets-install assets-bundle restart fixtures-sync

BUNDLE_ROOT := $(abspath $(dir $(lastword $(MAKEFILE_LIST)))/../..)
COMPOSE := $(shell docker compose version >/dev/null 2>&1 && echo "docker compose" || echo "docker-compose")
SERVICE_PHP := php

help:
	@echo "PDF Editor Bundle - Symfony 8.1 Demo"
	@echo ""
	@echo "  make up              Start containers + install + build Vite assets"
	@echo "  make down            Stop containers"
	@echo "  make build           Build image (no cache)"
	@echo "  make install         Composer + bundle assets + Pentatrion build"
	@echo "  make assets          pnpm install + Vite build (Pentatrion)"
	@echo "  make assets-install  Publish bundle static assets to public/bundles/"
	@echo "  make shell           Open shell in container"
	@echo "  make logs            Show logs"
	@echo "  make test            Run tests"
	@echo "  make test-coverage   Run tests with coverage"
	@echo "  make cache-clear     Clear Symfony cache"
	@echo "  make update-bundle   Update bundle from path and clear cache"
	@echo "  make verify          Start and check HTTP 200"
	@echo ""

fixtures-sync:
	@mkdir -p fixtures
	@if ls ../fixtures/*.pdf >/dev/null 2>&1; then \
		cp -f ../fixtures/*.pdf fixtures/; \
	else \
		echo "Generating demo fixtures..."; \
		node "$(BUNDLE_ROOT)/demo/scripts/generate-fixtures.mjs"; \
		cp -f ../fixtures/*.pdf fixtures/; \
	fi

up: fixtures-sync
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	$(COMPOSE) up -d --build --force-recreate
	@echo "Waiting for container to be ready..."
	@sleep 5
	@echo "Installing dependencies..."
	@$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 -e COMPOSER_MAX_PARALLEL_HTTP=4 php composer install --no-interaction --prefer-dist
	@$(MAKE) assets-install
	@$(MAKE) assets
	@PORT=$$(grep "^PORT=" .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	[ -z "$$PORT" ] && PORT=$$(grep "^PORT=" .env.example 2>/dev/null | cut -d= -f2 | tr -d '\r'); \
	echo "Demo started at: http://localhost:$$PORT"

down:
	$(COMPOSE) down

restart: ensure-up fixtures-sync
	$(COMPOSE) restart
	@$(MAKE) cache-clear

build:
	$(COMPOSE) build --no-cache

ensure-up:
	@if ! $(COMPOSE) exec -T php true 2>/dev/null; then \
		$(COMPOSE) up -d --build; \
		sleep 3; \
	fi

install: ensure-up
	$(COMPOSE) exec -T -e COMPOSER_MEMORY_LIMIT=-1 -e COMPOSER_MAX_PARALLEL_HTTP=4 php composer install --no-interaction --prefer-dist
	@$(MAKE) assets-install
	@$(MAKE) assets

assets-install: ensure-up
	$(COMPOSE) exec -T php php bin/console assets:install public --symlink --relative --no-interaction

assets-bundle:
	$(COMPOSE) exec -T -e CI=true php sh -c 'cd /var/pdf-editor-bundle && pnpm install --frozen-lockfile && pnpm run build'

assets: ensure-up assets-bundle
	$(COMPOSE) exec -T -e CI=true php pnpm install --frozen-lockfile
	$(COMPOSE) exec -T php pnpm rebuild esbuild
	$(COMPOSE) exec -T php pnpm run build
	@echo "Restarting FrankenPHP worker so Pentatrion reloads entrypoints.json..."
	$(COMPOSE) restart php
	@sleep 3

shell: ensure-up
	$(COMPOSE) exec php sh

logs:
	$(COMPOSE) logs -f

test: ensure-up
	$(COMPOSE) exec -e APP_ENV=test php composer test

test-coverage: ensure-up
	$(COMPOSE) exec php composer test-coverage

cache-clear: ensure-up
	$(COMPOSE) exec php php bin/console cache:clear

update-bundle: ensure-up
	$(COMPOSE) exec php composer update nowo-tech/pdf-editor-bundle --no-interaction
	@$(MAKE) assets-install
	@$(MAKE) assets
	$(COMPOSE) exec php php bin/console cache:clear
	@echo "Bundle updated."

verify:
	@if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || true; fi
	@port=$$(grep '^PORT=' .env 2>/dev/null | cut -d= -f2 | tr -d '\r'); [ -z "$$port" ] && port=8031; \
	$(COMPOSE) up -d 2>/dev/null; sleep 8; \
	code=$$(curl -s -o /dev/null -w "%{http_code}" http://127.0.0.1:$$port 2>/dev/null || echo "000"); \
	if [ "$$code" = "200" ]; then echo "OK (HTTP $$code)"; else echo "Failed (HTTP $$code)"; exit 1; fi

# REQ-MAKE-008: update-deps (REQ-MAKE-008)
# Optional: monorepo helper absent on standalone GitHub Actions checkout (REQ-MAKE-009).
-include $(BUNDLE_ROOT)/../.scripts/Makefile.demo-update-deps.mk
