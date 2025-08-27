#!/bin/bash

# Talent2Income Docker Test Runner
# Runs Laravel tests inside Docker environment

set -euo pipefail

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

print_status()  { echo -e "${BLUE}[INFO]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_error()   { echo -e "${RED}[ERROR]${NC} $1"; }

# Detect Docker Compose version
if docker compose version >/dev/null 2>&1; then
    COMPOSE="docker compose"
elif docker-compose version >/dev/null 2>&1; then
    COMPOSE="docker-compose"
else
    print_error "Docker Compose is not installed."
    exit 1
fi

# Function to run tests
run_tests() {
    local test_type=$1
    local profile=$2

    print_status "Running $test_type tests..."

    $COMPOSE -f docker-compose.yml -f docker-compose.test.yml --profile "$profile" up -d

    # Wait until app service is healthy
    print_status "Waiting for test-runner to be ready..."
    for i in {1..10}; do
        if $COMPOSE ps test-runner 2>/dev/null | grep -q "running"; then
            break
        fi
        sleep 3
    done

    case $test_type in
        unit)
            $COMPOSE -f docker-compose.yml -f docker-compose.test.yml exec -T test-runner php artisan test --testsuite=Unit --parallel
            ;;
        feature)
            $COMPOSE -f docker-compose.yml -f docker-compose.test.yml exec -T test-runner php artisan test --testsuite=Feature --parallel
            ;;
        integration)
            $COMPOSE -f docker-compose.yml -f docker-compose.test.yml exec -T test-runner php artisan test --testsuite=Integration
            ;;
        performance)
            $COMPOSE -f docker-compose.yml -f docker-compose.test.yml --profile performance exec -T performance-test php artisan test --testsuite=Performance
            ;;
        security)
            $COMPOSE -f docker-compose.yml -f docker-compose.test.yml --profile security exec -T security-test php artisan test --testsuite=Security
            ;;
        all)
            $COMPOSE -f docker-compose.yml -f docker-compose.test.yml exec -T test-runner php artisan test --parallel
            ;;
        coverage)
            $COMPOSE -f docker-compose.yml -f docker-compose.test.yml exec -T test-runner php artisan test --coverage --min=80
            ;;
    esac

    $COMPOSE -f docker-compose.yml -f docker-compose.test.yml --profile "$profile" down
}

# Main
echo "🧪 Talent2Income Docker Test Runner"
echo "=================================="

if ! docker info >/dev/null 2>&1; then
    print_error "Docker is not running."
    exit 1
fi

docker network create talent2income_network 2>/dev/null || true

case "${1:-all}" in
    unit)        run_tests unit testing ;;
    feature)     run_tests feature testing ;;
    integration) run_tests integration testing ;;
    performance) run_tests performance performance ;;
    security)    run_tests security security ;;
    coverage)    run_tests coverage testing ;;
    all)         run_tests all testing ;;
    setup)
        print_status "Setting up test environment..."
        $COMPOSE -f docker-compose.yml -f docker-compose.test.yml --profile testing up -d
        $COMPOSE -f docker-compose.yml -f docker-compose.test.yml exec -T test-runner composer install
        $COMPOSE -f docker-compose.yml -f docker-compose.test.yml exec -T test-runner php artisan migrate --force
        print_success "Test environment ready!"
        ;;
    teardown)
        print_status "Cleaning up test environment..."
        $COMPOSE -f docker-compose.yml -f docker-compose.test.yml down -v
        print_success "Test environment removed!"
        ;;
    help|-h|--help)
        cat <<EOF
Usage: $0 [test_type]

Test types:
  unit         Run unit tests
  feature      Run feature tests
  integration  Run integration tests
  performance  Run performance tests
  security     Run security tests
  coverage     Run tests with coverage report
  all          Run all tests (default)
  setup        Set up test environment
  teardown     Clean up test environment
  help         Show this help

Examples:
  $0 unit
  $0 coverage
  $0 setup
EOF
        exit 0 ;;
    *)
        print_error "Unknown test type: $1"
        print_status "Run '$0 help' for usage."
        exit 1 ;;
esac

print_success "Test execution completed!"
