# WP Vite — Developer Makefile
#
# Usage:
#   make install      Install all dependencies (Composer + npm)
#   make test         Run PHPUnit
#   make lint         Run PHPCS
#   make fix          Run PHPCBF (auto-fix style issues)
#   make check        Run lint + test together (used in CI)
#   make clean        Remove generated files
#   make help         Show this message

.PHONY: help install install-php install-js test test-coverage lint fix check clean

# ─── Defaults ────────────────────────────────────────────────────────────────

PHP        ?= php
COMPOSER   ?= composer
NPM        ?= npm
PHPUNIT    ?= vendor/bin/phpunit
PHPCS      ?= vendor/bin/phpcs
PHPCBF     ?= vendor/bin/phpcbf

# ─── Help ─────────────────────────────────────────────────────────────────────

help: ## Show this help message
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
		| awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'
	@echo ""

# ─── Install ──────────────────────────────────────────────────────────────────

install: install-php install-js ## Install Composer + npm dependencies

install-php: ## Install Composer dependencies
	$(COMPOSER) install

install-js: ## Install npm dependencies
	$(NPM) ci

# ─── Testing ──────────────────────────────────────────────────────────────────

test: ## Run PHPUnit (no coverage)
	$(PHP) $(PHPUNIT) --no-coverage

test-coverage: ## Run PHPUnit with code coverage (requires Xdebug)
	XDEBUG_MODE=coverage $(PHP) $(PHPUNIT) --coverage-text --coverage-html=coverage/html

# ─── Linting & Fixing ─────────────────────────────────────────────────────────

lint: ## Run PHPCS (coding standards check)
	$(PHPCS) --standard=phpcs.xml.dist

fix: ## Run PHPCBF (auto-fix coding standards)
	$(PHPCBF) --standard=phpcs.xml.dist

# ─── Combined ─────────────────────────────────────────────────────────────────

check: lint test ## Run lint + tests (mirrors CI)

# ─── Clean ────────────────────────────────────────────────────────────────────

clean: ## Remove generated files (vendor, node_modules, coverage, caches)
	rm -rf vendor node_modules coverage .phpunit.result.cache .phpcs-cache

# ─── JS helpers ───────────────────────────────────────────────────────────────

js-check: ## Check JS plugin syntax (Node)
	$(NPM) run test:syntax
