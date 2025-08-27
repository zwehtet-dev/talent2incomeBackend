# Testing Infrastructure

This document describes the comprehensive testing infrastructure set up for the Talent2Income platform backend.

## Overview

The testing infrastructure includes:
- **PHPUnit** with Laravel testing utilities
- **Pest** testing framework for expressive tests
- **SQLite in-memory database** for fast test execution
- **Parallel testing** support for faster test runs
- **Code coverage reporting** (requires Xdebug or PCOV)
- **Architecture testing** for code quality enforcement

## Test Structure

```
tests/
├── Architecture/          # Architecture and code quality tests
├── Feature/               # Feature tests (HTTP requests, database interactions)
├── Unit/                  # Unit tests (isolated component testing)
├── Helpers/               # Test helper classes and utilities
├── coverage/              # Code coverage reports (generated)
├── results/               # Test result reports (generated)
├── Pest.php              # Pest configuration
├── TestCase.php          # Base test case with common functionality
└── README.md             # This file
```

## Running Tests

### Basic Test Commands

```bash
# Run all tests
composer test

# Run specific test suites
composer test-unit
composer test-feature
composer test-architecture

# Run tests in parallel (faster)
composer test-parallel

# Run all tests with comprehensive checks
composer test-all
```

### Code Coverage

To enable code coverage reporting, install either Xdebug or PCOV:

```bash
# Install Xdebug (recommended for development)
# Follow installation instructions for your system

# Or install PCOV (faster, production-focused)
# Follow installation instructions for your system

# Then run coverage tests
composer test-coverage
composer test-coverage-html  # Generates HTML report
```

### Individual Test Files

```bash
# Run specific test file
./vendor/bin/pest tests/Unit/ExamplePestTest.php

# Run specific test with filter
./vendor/bin/pest --filter="can perform basic assertions"

# Run tests with verbose output
./vendor/bin/pest -v
```

## Test Configuration

### Database Configuration

Tests use SQLite in-memory database for speed:
- Database is created fresh for each test
- Foreign key constraints are enabled
- Migrations run automatically via RefreshDatabase trait

### Environment Configuration

Test environment is configured in:
- `.env.testing` - Environment variables for testing
- `phpunit.xml` - PHPUnit configuration
- `pest.xml` - Pest-specific configuration

### Key Testing Features

1. **Fast Database**: SQLite in-memory for speed
2. **Parallel Execution**: Tests run in parallel by default
3. **Strict Types**: All code enforces strict type declarations
4. **Architecture Rules**: Automated code quality checks
5. **Faker Integration**: Realistic test data generation
6. **Laravel Integration**: Full Laravel testing utilities

## Writing Tests

### Pest Test Example

```php
<?php

declare(strict_types=1);

use App\Models\User;

describe('User Management', function () {
    it('can create a user', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com'
        ]);
        
        expect($user->email)->toBe('test@example.com');
        expect($user->exists)->toBeTrue();
    });
    
    it('validates required fields', function () {
        $response = $this->postJson('/api/users', []);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email', 'password']);
    });
});
```

### Feature Test Example

```php
<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('API Authentication', function () {
    it('can register a new user', function () {
        $userData = [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'password' => 'Password123!',
            'password_confirmation' => 'Password123!'
        ];
        
        $response = $this->postJson('/api/auth/register', $userData);
        
        $response->assertStatus(201);
        $response->assertJsonStructure([
            'message',
            'user' => ['id', 'first_name', 'last_name', 'email']
        ]);
        
        $this->assertDatabaseHas('users', [
            'email' => 'john@example.com'
        ]);
    });
});
```

### Architecture Test Example

```php
<?php

declare(strict_types=1);

describe('Code Quality', function () {
    it('ensures controllers extend base controller', function () {
        expect('App\Http\Controllers')
            ->toExtend('App\Http\Controllers\Controller');
    });
    
    it('ensures no debugging functions are used', function () {
        expect(['dd', 'dump', 'var_dump'])
            ->not->toBeUsed();
    });
});
```

## Test Helpers

The `Tests\Helpers\TestHelpers` class provides common testing utilities:

```php
// Create test users
$user = TestHelpers::createUser();
$admin = TestHelpers::createAdminUser();

// Authenticate for API testing
$user = TestHelpers::authenticateUser();

// Generate test data
$jobData = TestHelpers::jobCreationData();
$skillData = TestHelpers::skillCreationData();
```

## Coverage Requirements

- **Minimum Coverage**: 80%
- **Excluded Directories**: 
  - `app/Console` (Artisan commands)
  - `app/Exceptions` (Exception handlers)
- **Coverage Reports**: 
  - HTML: `tests/coverage/html/index.html`
  - XML: `tests/coverage/xml/`
  - Clover: `tests/coverage/clover.xml`

## Continuous Integration

The testing infrastructure is integrated with:
- **Pre-commit hooks**: Run code quality checks
- **Pre-push hooks**: Run full test suite
- **Parallel execution**: Faster CI/CD pipeline

## Performance Optimizations

1. **In-memory database**: Faster than file-based SQLite
2. **Parallel testing**: Utilizes multiple CPU cores
3. **Optimized Laravel config**: Reduced overhead for testing
4. **Cached dependencies**: Faster test startup

## Troubleshooting

### Common Issues

1. **"No code coverage driver available"**
   - Install Xdebug or PCOV extension
   - Tests will still run without coverage

2. **"Database connection failed"**
   - Check SQLite is available: `php -m | grep sqlite`
   - Verify `.env.testing` configuration

3. **"Tests running slowly"**
   - Use parallel execution: `composer test-parallel`
   - Check database is using `:memory:`

4. **"Architecture tests failing"**
   - Ensure all PHP files have `declare(strict_types=1);`
   - Follow Laravel naming conventions

### Debug Commands

```bash
# Check PHP extensions
php -m

# Verify test configuration
./vendor/bin/pest --help

# Run single test with debug
./vendor/bin/pest tests/Unit/ExampleTest.php -v

# Check database connection
php artisan tinker
>>> DB::connection()->getPdo()
```

## Best Practices

1. **Use descriptive test names**: `it('validates email format when registering')`
2. **Test one thing per test**: Keep tests focused and simple
3. **Use factories for data**: Consistent and maintainable test data
4. **Clean up after tests**: Use RefreshDatabase trait
5. **Test edge cases**: Invalid inputs, boundary conditions
6. **Mock external services**: Don't rely on external APIs in tests
7. **Use architecture tests**: Enforce code quality automatically