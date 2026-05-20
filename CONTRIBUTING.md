# Contributing to DevelopmentTranslation Bridge

Thank you for your interest in contributing to DevelopmentTranslation Bridge! This guide will help you get set up and contributing in under 5 minutes.

## Quick Start

```bash
# Clone the repository
git clone https://github.com/coryhubbell/development-translation-bridge.git
cd development-translation-bridge

# Run the setup script (installs dependencies, configures environment)
./setup.sh
# Or use make:
make setup

# Verify everything works
make test

# Try a translation
./devtb translate bootstrap divi examples/hero-bootstrap.html
```

That's it! You're ready to contribute.

## Prerequisites

### Required
- **PHP 8.1+** (matches the platform pin in `composer.json` and the runtime gate in `devtb-php`)
  ```bash
  php --version
  ```
- **Composer 2.0+**
  ```bash
  composer --version
  ```

### Optional (for full development)
- **Docker & Docker Compose** - For running WordPress locally
- **Node.js 18+** - For Visual Interface development
- **Make** - For standardized commands (included on macOS/Linux)

## Development Setup Options

### Option 1: CLI-Only (Fastest)

If you just want to work on the translation engine or CLI:

```bash
./setup.sh
make test
./devtb list-frameworks
```

No WordPress or Docker needed!

### Option 2: Full Stack with Docker

For working on WordPress integration, REST API, or Visual Interface:

```bash
./setup.sh
make docker-up

# Wait for WordPress to start, then visit:
# WordPress: http://localhost:8080
# phpMyAdmin: http://localhost:8081
```

Complete the WordPress installation wizard, then activate the theme.

### Option 3: Manual Setup

```bash
# Install PHP dependencies
composer install

# Copy environment file
cp .env.example .env

# Make CLI executable
chmod +x devtb

# Run tests
composer test
```

## Running Tests

### All Tests
```bash
make test
# or
composer test
```

### With Coverage Report
```bash
make test-coverage
# Coverage report generated in coverage/ directory
```

### Specific Test Suites
```bash
# Unit tests only
vendor/bin/phpunit --testsuite Unit

# Integration tests only
vendor/bin/phpunit --testsuite Integration

# Single test file
vendor/bin/phpunit tests/Unit/FrameworkConversionsTest.php

# Single test method
vendor/bin/phpunit --filter testFrameworkConversion

# Verbose output with test names
vendor/bin/phpunit --testdox

# Stop on first failure
vendor/bin/phpunit --stop-on-failure
```

## Project Structure

```
development-translation-bridge/
├── translation-bridge/          # Core translation engine
│   ├── core/                    # Translation orchestration
│   ├── parsers/                 # 10 framework parsers
│   ├── converters/              # 10 framework converters
│   ├── models/                  # Data structures
│   └── utils/                   # Helper utilities
│
├── includes/                    # WordPress integration
│   ├── class-devtb-cli.php       # CLI commands
│   ├── class-devtb-api-v2.php    # REST API v2
│   ├── class-translator.php     # Main translator
│   └── ...
│
├── admin/                       # React admin interface
│   ├── src/                     # React components
│   ├── vite.config.ts           # Build config
│   └── package.json             # Node dependencies
│
├── tests/                       # PHPUnit tests
│   ├── Unit/                    # Unit tests
│   ├── Integration/             # Integration tests
│   ├── fixtures/                # Test data for all 10 frameworks
│   └── bootstrap.php            # Test environment setup
│
├── docs/                        # Documentation
├── examples/                    # Example files
├── devtb                         # CLI entry point
├── functions.php                # Theme entry point
└── composer.json                # PHP dependencies
```

## Make Commands

| Command | Description |
|---------|-------------|
| `make setup` | Run initial setup script |
| `make test` | Run PHPUnit tests |
| `make test-coverage` | Run tests with coverage report |
| `make docker-up` | Start Docker containers |
| `make docker-down` | Stop Docker containers |
| `make admin-dev` | Start Vite dev server for admin UI |
| `make admin-build` | Build admin interface for production |
| `make clean` | Remove generated files |
| `make help` | Show all available commands |

## Code Standards

### PHP
- Follow [WordPress PHP Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/)
- Use PSR-4 autoloading with namespaces
- Add PHPDoc blocks to all methods
- Use type hints, including return types and `readonly` properties where appropriate (PHP 8.1+ floor)

### JavaScript/TypeScript (Admin)
- Use TypeScript for new code
- Follow existing patterns in `admin/src/`
- Run `npm run lint` before committing

### General
- Keep commits focused and atomic
- Write descriptive commit messages
- Add tests for new functionality
- Don't introduce new dependencies without discussion

## Writing Tests

### Test Guidelines

1. **Use descriptive test names:**
   ```php
   public function test_translate_endpoint_validates_source_framework()
   ```

2. **One assertion concept per test**

3. **Use data providers for parameterized tests:**
   ```php
   /**
    * @dataProvider frameworkPairsProvider
    */
   public function testFrameworkConversion(string $source, string $target)
   ```

4. **Clean up after tests:**
   ```php
   protected function tearDown(): void {
       // Clean up test data
       parent::tearDown();
   }
   ```

### WordPress Mocking

Tests use [Brain Monkey](https://brain-wp.github.io/BrainMonkey/) for WordPress function mocking. Common mocks are pre-configured in `tests/bootstrap.php`:

- `add_action()`, `add_filter()`, `do_action()`, `apply_filters()`
- `get_option()`, `update_option()`, `delete_option()`
- `__()`, `_e()`, `esc_html()`, `esc_attr()`, `esc_url()`
- `WP_Error`, `WP_REST_Request`, `WP_REST_Response`

## Pull Request Process

1. **Fork and create a feature branch:**
   ```bash
   git checkout -b feature/your-feature-name
   ```

2. **Make your changes:**
   - Write code
   - Add/update tests
   - Update documentation if needed

3. **Ensure all tests pass:**
   ```bash
   make test
   ```

4. **Commit with descriptive messages:**
   ```bash
   git commit -m "Add: Description of what you added"
   # Prefixes: Add, Update, Fix, Remove, Refactor, Docs, Test
   ```

5. **Push and create PR:**
   ```bash
   git push origin feature/your-feature-name
   ```

6. **Fill out the PR template** with:
   - Summary of changes
   - Related issues
   - Test plan
   - Screenshots (if UI changes)

## CI/CD

All pull requests are automatically tested:

- **PHP Matrix**: 8.1, 8.2, 8.3, 8.4, 8.5
- **Node.js Matrix**: 18, 20 (for admin builds)
- **Code Coverage**: Generated on PHP 8.4
- **Security**: `composer audit` runs on every job (advisories fail CI)

Ensure your PR passes all CI checks before requesting review.

## Supported Frameworks

The Translation Bridge supports 10 page builder frameworks:

| Framework | Format | Parser | Converter |
|-----------|--------|--------|-----------|
| Bootstrap 5.3.3 | HTML | BootstrapParser | BootstrapConverter |
| DIVI Builder | Shortcodes | DIVIParser | DIVIConverter |
| Elementor | JSON | ElementorParser | ElementorConverter |
| Avada Fusion | HTML | AvadaParser | AvadaConverter |
| Bricks Builder | JSON | BricksParser | BricksConverter |
| WPBakery | Shortcodes | WPBakeryParser | WPBakeryConverter |
| Beaver Builder | Serialized PHP | BeaverBuilderParser | BeaverBuilderConverter |
| Gutenberg | Block HTML | GutenbergParser | GutenbergConverter |
| Oxygen Builder | JSON | OxygenParser | OxygenConverter |
| Claude AI | HTML + data attrs | ClaudeParser | ClaudeConverter |

## Getting Help

- **Issues**: [GitHub Issues](https://github.com/coryhubbell/development-translation-bridge/issues)
- **Discussions**: [GitHub Discussions](https://github.com/coryhubbell/development-translation-bridge/discussions)
- **Documentation**: See `/docs/` directory

## License

By contributing, you agree that your contributions will be licensed under the GPL-2.0+ license.

---

Thank you for contributing to DevelopmentTranslation Bridge!
