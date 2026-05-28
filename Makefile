# Auto-detect Docker binary (QNAP vs standard)
DOCKER := $(shell if [ -x "$(QNAP_DOCKER_BINARY)" ]; then echo "$(QNAP_DOCKER_BINARY)"; else echo "docker"; fi)
COMPOSE := $(DOCKER) compose

# Load .env if it exists
ifneq (,$(wildcard ./.env))
    include .env
    export
endif

.PHONY: up up-dev down build build-dev fresh migrate seed shell tinker logs lint test deploy-local deploy-qnap ssh-qnap

## Docker targets (production by default)
up:
	$(COMPOSE) up -d

# Local dev: layers docker-compose.dev.yml on top (bind-mounts backend/frontend for hot reload)
up-dev:
	$(COMPOSE) -f docker-compose.yml -f docker-compose.dev.yml up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

build-dev:
	$(COMPOSE) -f docker-compose.yml -f docker-compose.dev.yml build

## Laravel targets (run inside frankenphp container)
fresh:
	$(COMPOSE) exec frankenphp php artisan migrate:fresh --seed

migrate:
	$(COMPOSE) exec frankenphp php artisan migrate

seed:
	$(COMPOSE) exec frankenphp php artisan db:seed

shell:
	$(COMPOSE) exec frankenphp sh

tinker:
	$(COMPOSE) exec frankenphp php artisan tinker

## Logging
logs:
	$(COMPOSE) logs -f --tail=100

## Linting
lint:
	$(COMPOSE) exec frankenphp ./vendor/bin/pint --test
	$(COMPOSE) exec frankenphp ./vendor/bin/phpstan analyse --level=8
	cd frontend && npx eslint src/ --ext .vue,.js
	cd frontend && npx prettier --check "src/**/*.{vue,js,css,json}"
	cd frontend && npx stylelint "src/**/*.{css,vue}"

## Testing
test:
	$(COMPOSE) exec frankenphp php artisan test

## Deployment
deploy-local:
	./deploy.sh local

deploy-qnap:
	./deploy.sh qnap

## SSH
ssh-qnap:
	ssh $(QNAP_USER)@$(QNAP_HOST)
