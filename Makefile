# =============================================================================
# DevelopmentTranslation Bridge - Makefile
# =============================================================================
# Standardized commands for development workflow
# Run 'make help' to see available targets
# =============================================================================

.PHONY: setup install verify composer-validate composer-install composer-audit python-install test test-php test-coverage test-watch test-unit test-integration test-python gutenberg-smoke lint-php docker-up docker-down docker-logs docker-restart docker-clean admin-dev admin-install admin-build admin-lint admin-typecheck admin-audit release-package-smoke whitespace-check clean clean-all version help

COMPOSER ?= $(if $(wildcard composer.phar),php composer.phar,composer)
NPM ?= npm
PYTHON ?= python3.11
PYTEST ?= $(PYTHON) -m pytest

# Default target
.DEFAULT_GOAL := help

# -----------------------------------------------------------------------------
# Setup & Installation
# -----------------------------------------------------------------------------

setup: ## Run initial setup script
	@./setup.sh

install: ## Install all dependencies (PHP + Node)
	@$(COMPOSER) install --prefer-dist --no-progress
	@if [ -d "admin" ]; then cd admin && $(NPM) ci; fi

verify: composer-validate composer-install composer-audit test-php python-install test-python gutenberg-smoke admin-install admin-lint admin-typecheck admin-build admin-audit release-package-smoke whitespace-check ## Run the full local release verification suite

composer-validate: ## Validate Composer metadata
	@$(COMPOSER) validate --no-check-all

composer-install: ## Install PHP dependencies
	@$(COMPOSER) install --prefer-dist --no-progress

composer-audit: ## Audit PHP dependencies
	@$(COMPOSER) audit

python-install: ## Install Python package and development test extras
	@$(PYTHON) -m pip install -e ".[dev]"

# -----------------------------------------------------------------------------
# Testing
# -----------------------------------------------------------------------------

test: test-unit test-integration test-python ## Run all tests (PHP + Python)

test-php: ## Run PHPUnit tests
	@$(COMPOSER) test

test-coverage: ## Run tests with coverage report
	@mkdir -p coverage
	@$(COMPOSER) test:coverage
	@echo "Coverage report: coverage/index.html"

test-watch: ## Run tests in watch mode
	@$(COMPOSER) test:watch 2>/dev/null || vendor/bin/phpunit --no-coverage --testdox

test-unit: ## Run unit tests only
	@vendor/bin/phpunit --no-coverage --testsuite Unit --testdox

test-integration: ## Run integration tests only
	@vendor/bin/phpunit --no-coverage --testsuite Integration --testdox

test-python: ## Run Python tests (v4 transform engine)
	@echo "Running Python tests..."
	@PYTHONPATH=src $(PYTEST) tests/python/ -v

gutenberg-smoke: ## Run Elementor to Gutenberg e2e smoke
	@PYTHONPATH=src $(PYTHON) tests/smoke_gutenberg_e2e.py

lint-php: ## Check PHP syntax
	@find includes translation-bridge -name "*.php" -exec php -l {} \; | grep -v "No syntax errors"

# -----------------------------------------------------------------------------
# Docker
# -----------------------------------------------------------------------------

docker-up: ## Start Docker containers
	@docker-compose up -d
	@echo ""
	@echo "Services starting..."
	@echo "  WordPress:   http://localhost:$${WORDPRESS_PORT:-8080}"
	@echo "  phpMyAdmin:  http://localhost:$${PHPMYADMIN_PORT:-8081}"
	@echo ""

docker-down: ## Stop Docker containers
	@docker-compose down

docker-logs: ## View Docker container logs
	@docker-compose logs -f

docker-restart: ## Restart Docker containers
	@docker-compose restart

docker-clean: ## Remove Docker volumes (WARNING: deletes database)
	@docker-compose down -v

# -----------------------------------------------------------------------------
# Admin Interface (React)
# -----------------------------------------------------------------------------

admin-dev: ## Start Vite dev server for admin UI
	@cd admin && npm run dev

admin-build: admin-install ## Build admin interface for production
	@cd admin && $(NPM) run build

admin-install: ## Install admin dependencies from package-lock
	@cd admin && $(NPM) ci

admin-lint: ## Lint admin TypeScript/React code
	@cd admin && $(NPM) run lint

admin-typecheck: ## Type-check admin TypeScript
	@cd admin && npx tsc -b --noEmit

admin-audit: ## Audit production admin dependencies
	@cd admin && $(NPM) audit --omit=dev

release-package-smoke: admin-build ## Build a local release package and verify its zip contents
	@./scripts/build-release-package.sh smoke

whitespace-check: ## Verify staged and unstaged diffs have no whitespace errors
	@git diff --check

# -----------------------------------------------------------------------------
# CLI Tool
# -----------------------------------------------------------------------------

cli-help: ## Show CLI tool help
	@./devtb --help

cli-frameworks: ## List supported frameworks
	@./devtb list-frameworks

cli-translate: ## Example translation (bootstrap to divi)
	@./devtb translate bootstrap divi examples/hero-bootstrap.html

# -----------------------------------------------------------------------------
# Utilities
# -----------------------------------------------------------------------------

clean: ## Remove generated files
	@rm -rf vendor/ node_modules/ admin/node_modules/ coverage/
	@echo "Cleaned: vendor/, node_modules/, admin/node_modules/, coverage/"

clean-all: clean ## Remove all generated files including .env
	@rm -f .env
	@echo "Also removed: .env"

version: ## Show version information
	@echo "DevelopmentTranslation Bridge"
	@grep -m1 "Version:" style.css | sed 's/.*Version: //'

# -----------------------------------------------------------------------------
# Help
# -----------------------------------------------------------------------------

help: ## Show this help message
	@echo ""
	@echo "DevelopmentTranslation Bridge - Available Commands"
	@echo "================================================"
	@echo ""
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'
	@echo ""
	@echo "Examples:"
	@echo "  make setup          # Initial setup for new contributors"
	@echo "  make test           # Run all tests"
	@echo "  make docker-up      # Start WordPress in Docker"
	@echo "  make admin-dev      # Start admin UI dev server"
	@echo ""
