PROJECT_DIR := $(shell pwd)

COMPOSER := docker run --rm -v "$(PROJECT_DIR):/app" -w /app/wipop composer:2 composer
PHP := docker run --rm -v "$(PROJECT_DIR):/app" -w /app/wipop php:8.1-cli php

.PHONY: help up down logs ps-cache-clear composer-install cs-check cs-fix phpstan header-stamp autoindex hooks

help:
	@echo "Wipöp PrestaShop module (dev helpers)"
	@echo ""
	@echo "Docker:"
	@echo "  make up             Start PrestaShop + MySQL"
	@echo "  make down           Stop containers"
	@echo "  make logs           Follow prestashop logs"
	@echo "  make cli            Open a bash shell in prestashop container"
	@echo "  make ps-cache-clear Clear PrestaShop cache"
	@echo ""
	@echo "Module tooling (runs in Docker):"
	@echo "  make composer-install  Install module dev dependencies"
	@echo "  make cs-check          PHP CS Fixer (dry-run)"
	@echo "  make cs-fix            PHP CS Fixer (apply fixes)"
	@echo "  make phpstan           PHPStan inside prestashop container"
	@echo "  make header-stamp      Apply PrestaShop license headers"
	@echo "  make autoindex         Add index.php to subfolders"
	@echo "  make hooks             Install git pre-commit hook"

up:
	docker compose up -d

down:
	docker compose down

logs:
	docker compose logs -f prestashop

ps-cache-clear:
	docker compose exec prestashop bash -lc 'rm -rf var/cache/*'

composer-install:
	$(COMPOSER) install

cs-check:
	$(PHP) ../tools/vendor/bin/php-cs-fixer fix --dry-run --diff

cs-fix:
	$(PHP) ../tools/vendor/bin/php-cs-fixer fix

phpstan:
	@echo "Run PHPStan on the host using the local PrestaShop core"
	@echo "  cd wipop && _PS_ROOT_DIR_=$$(pwd)/../prestashop-core php ../tools/vendor/bin/phpstan --configuration=tests/phpstan/phpstan.neon analyse ."

header-stamp:
	$(PHP) ../tools/vendor/bin/header-stamp --license=assets/afl.txt --exclude=vendor,node_modules

autoindex:
	$(PHP) ../tools/vendor/bin/autoindex prestashop:add:index .

hooks:
	@echo "Installing GrumPHP git hooks"
	cd wipop && composer grumphp-init

cli:
	docker compose exec prestashop bash
