# Auto-detect Docker binary (QNAP vs standard)
QNAP_DOCKER := /share/CE_CACHEDEV1_DATA/.qpkg/container-station/usr/bin/.libs/docker
DOCKER := $(shell if [ -x "$(QNAP_DOCKER)" ]; then echo "$(QNAP_DOCKER)"; else echo "docker"; fi)
COMPOSE := $(DOCKER) compose

# Load .env if it exists
ifneq (,$(wildcard ./.env))
    include .env
    export
endif

.PHONY: up down build fresh migrate seed shell tinker logs lint test deploy-local deploy-qnap ssh-qnap

## Docker targets
up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

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
