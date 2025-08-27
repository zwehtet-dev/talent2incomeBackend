# Development Guide

This document outlines the development tooling and code quality standards for the Talent2Income backend project.

## Code Quality Tools

### PHPStan - Static Analysis

PHPStan is configured with strict level 8 analysis and Laravel-specific rules via Larastan.

**Configuration:** `phpstan.neon`

**Usage:**
```bash
# Run static analysis
composer phpstan

# Or directly
vendor/bin/phpstan analyse --memory-limit=2G
```

### PHP CS Fixer - Code Style

PHP CS Fixer enforces PSR-12 coding standards with Laravel-specific rules.

**Configuration:** `.php-cs-fixer.php`

**Usage:**
```bash
# Check code style (dry run)
composer cs-check

# Fix code style issues
composer cs-fix

# Or directly
vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.php
```

### Laravel IDE Helper

Provides better IDE support with auto-completion for Laravel facades, models, and more.

**Usage:**
```bash
# Generate helper files
php artisan ide-helper:generate
php artisan ide-helper:models --write
php artisan ide-helper:meta
```

**Generated files:**
- `_ide_helper.php` - Facade definitions
- `_ide_helper_models.php` - Model properties and methods
- `.phpstorm.meta.php` - PhpStorm metadata

## Git Hooks

Pre-commit hooks are automatically installed to enforce code quality:

**Pre-commit hooks:**
- Code style check (`composer cs-check`)
- Static analysis (`composer phpstan`)

**Pre-push hooks:**
- Run tests (`composer test`)

**Manual hook management:**
```bash
# Install hooks
vendor/bin/cghooks add

# Update hooks
vendor/bin/cghooks update

# Remove hooks
vendor/bin/cghooks remove
```

## Available Composer Scripts

```bash
# Code quality
composer quality          # Run cs-check and phpstan
composer quality-fix       # Run cs-fix and phpstan

# Code style
composer cs-check          # Check code style (dry run)
composer cs-fix            # Fix code style issues

# Static analysis
composer phpstan           # Run PHPStan analysis

# Testing
composer test              # Run PHPUnit tests

# Development server
composer dev               # Start development server with queue, logs, and Vite
```

## IDE Configuration

### PhpStorm

1. Install the Laravel plugin
2. Enable PHP CS Fixer integration:
   - Go to Settings → PHP → Quality Tools → PHP CS Fixer
   - Set path to `vendor/bin/php-cs-fixer`
   - Set configuration file to `.php-cs-fixer.php`

3. Enable PHPStan integration:
   - Go to Settings → PHP → Quality Tools → PHPStan
   - Set path to `vendor/bin/phpstan`
   - Set configuration file to `phpstan.neon`

### VS Code

Install these extensions:
- PHP Intelephense
- Laravel Extension Pack
- PHP CS Fixer
- PHPStan

## Code Style Rules

The project follows PSR-12 with additional Laravel-specific rules:

- Short array syntax (`[]` instead of `array()`)
- Single quotes for strings
- Ordered imports alphabetically
- Trailing commas in multiline arrays
- Proper spacing around operators
- Laravel-specific formatting for method chaining

## Static Analysis Rules

PHPStan is configured with:
- Level 8 (strictest)
- Laravel-specific rules via Larastan
- Custom ignore patterns for Laravel-specific code
- Memory limit of 2GB for large codebases

## Pre-commit Workflow

1. Developer makes changes
2. Attempts to commit
3. Pre-commit hook runs:
   - Checks code style with PHP CS Fixer
   - Runs static analysis with PHPStan
4. If any checks fail, commit is blocked
5. Developer fixes issues and commits again

## Continuous Integration

The same quality checks should be run in CI/CD:

```yaml
# Example GitHub Actions workflow
- name: Check code style
  run: composer cs-check

- name: Run static analysis
  run: composer phpstan

- name: Run tests
  run: composer test
```

## Troubleshooting

### PHPStan Memory Issues
If PHPStan runs out of memory, increase the limit:
```bash
vendor/bin/phpstan analyse --memory-limit=4G
```

### PHP CS Fixer Cache Issues
Clear the cache if you encounter issues:
```bash
rm .php-cs-fixer.cache
```

### Git Hooks Not Working
Reinstall the hooks:
```bash
vendor/bin/cghooks remove
vendor/bin/cghooks add
```