# =============================================================================
# DevelopmentTranslation Bridge - Makefile
# =============================================================================
# Standardized commands for development workflow
# Run 'make help' to see available targets
# =============================================================================

.PHONY: setup test test-coverage docker-up docker-down docker-logs admin-dev admin-build clean help

# Default target
.DEFAULT_GOAL := help

# -----------------------------------------------------------------------------
# Setup & Installation
# -----------------------------------------------------------------------------

setup: ## Run initial setup script
	@./setup.sh

install: ## Install all dependencies (PHP + Node)
	@composer install
	@if [ -d "admin" ]; then cd admin && npm install; fi

# -----------------------------------------------------------------------------
# Testing
# -----------------------------------------------------------------------------

test: test-unit test-integration test-python ## Run all tests (PHP + Python)

test-php: ## Run PHPUnit tests
	@composer test

test-coverage: ## Run tests with coverage report
	@mkdir -p coverage
	@composer test:coverage
	@echo "Coverage report: coverage/index.html"

test-watch: ## Run tests in watch mode
	@composer test:watch 2>/dev/null || vendor/bin/phpunit --testdox

test-unit: ## Run unit tests only
	@vendor/bin/phpunit --testsuite Unit --testdox

test-integration: ## Run integration tests only
	@vendor/bin/phpunit --testsuite Integration --testdox

test-python: ## Run Python tests (v4 transform engine)
	@echo "Running Python tests..."
	@PYTHONPATH=src python -m pytest tests/python/ -v

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

admin-build: ## Build admin interface for production
	@cd admin && npm install && npm run build

admin-lint: ## Lint admin TypeScript/React code
	@cd admin && npm run lint

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
