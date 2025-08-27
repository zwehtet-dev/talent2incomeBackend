#!/bin/bash

# Comprehensive Test Suite Runner
# This script runs all tests with coverage reporting and performance monitoring

set -e

echo "🧪 Starting Comprehensive Test Suite..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Create coverage directories
mkdir -p tests/coverage/html
mkdir -p tests/coverage/xml
mkdir -p tests/results

echo -e "${BLUE}📋 Preparing test environment...${NC}"

# Clear caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Migrate test database
php artisan migrate:fresh --env=testing --force

echo -e "${BLUE}🔧 Running static analysis...${NC}"

# Run PHPStan
if command -v ./vendor/bin/phpstan &> /dev/null; then
    ./vendor/bin/phpstan analyse --memory-limit=2G --no-progress
    echo -e "${GREEN}✅ Static analysis passed${NC}"
else
    echo -e "${YELLOW}⚠️  PHPStan not found, skipping static analysis${NC}"
fi

echo -e "${BLUE}🎨 Checking code style...${NC}"

# Run PHP CS Fixer
if command -v ./vendor/bin/php-cs-fixer &> /dev/null; then
    ./vendor/bin/php-cs-fixer fix --dry-run --diff --config=.php-cs-fixer.php
    echo -e "${GREEN}✅ Code style check passed${NC}"
else
    echo -e "${YELLOW}⚠️  PHP CS Fixer not found, skipping code style check${NC}"
fi

echo -e "${BLUE}🧪 Running Unit Tests...${NC}"
php artisan test --testsuite=Unit --stop-on-failure

echo -e "${BLUE}🔗 Running Feature Tests...${NC}"
php artisan test --testsuite=Feature --stop-on-failure

echo -e "${BLUE}🏗️  Running Architecture Tests...${NC}"
php artisan test --testsuite=Architecture --stop-on-failure

echo -e "${BLUE}🔌 Running Integration Tests...${NC}"
php artisan test --testsuite=Integration --stop-on-failure

echo -e "${BLUE}⚡ Running Performance Tests...${NC}"
php artisan test --testsuite=Performance --stop-on-failure

echo -e "${BLUE}🔒 Running Security Tests...${NC}"
php artisan test --testsuite=Security --stop-on-failure

echo -e "${BLUE}📊 Generating Coverage Report...${NC}"

# Run all tests with coverage
php artisan test \
    --coverage-html=tests/coverage/html \
    --coverage-clover=tests/coverage/clover.xml \
    --coverage-text=tests/coverage/coverage.txt \
    --min=80 \
    --log-junit=tests/results/junit.xml

echo -e "${GREEN}✅ All tests passed!${NC}"

# Display coverage summary
if [ -f "tests/coverage/coverage.txt" ]; then
    echo -e "${BLUE}📈 Coverage Summary:${NC}"
    tail -n 10 tests/coverage/coverage.txt
fi

echo -e "${GREEN}🎉 Test suite completed successfully!${NC}"
echo -e "${BLUE}📁 Coverage report available at: tests/coverage/html/index.html${NC}"
echo -e "${BLUE}📄 JUnit report available at: tests/results/junit.xml${NC}"

# Optional: Open coverage report in browser (uncomment if desired)
# if command -v open &> /dev/null; then
#     open tests/coverage/html/index.html
# elif command -v xdg-open &> /dev/null; then
#     xdg-open tests/coverage/html/index.html
# fi