#!/bin/bash

# Talent2Income Docker Test Runner
# This script runs comprehensive tests in Docker environment

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_status() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to run tests
run_tests() {
    local test_type=$1
    local profile=$2
    
    print_status "Running $test_type tests..."
    
    # Start test environment
    docker-compose -f docker-compose.yml -f docker-compose.test.yml --profile $profile up -d
    
    # Wait for services to be ready
    sleep 10
    
    case $test_type in
        "unit")
            docker-compose -f docker-compose.yml -f docker-compose.test.yml exec test-runner php artisan test --testsuite=Unit --parallel
            ;;
        "feature")
            docker-compose -f docker-compose.yml -f docker-compose.test.yml exec test-runner php artisan test --testsuite=Feature --parallel
            ;;
        "integration")
            docker-compose -f docker-compose.yml -f docker-compose.test.yml exec test-runner php artisan test --testsuite=Integration
            ;;
        "performance")
            docker-compose -f docker-compose.yml -f docker-compose.test.yml --profile performance exec performance-test php artisan test --testsuite=Performance
            ;;
        "security")
            docker-compose -f docker-compose.yml -f docker-compose.test.yml --profile security exec security-test php artisan test --testsuite=Security
            ;;
        "all")
            docker-compose -f docker-compose.yml -f docker-compose.test.yml exec test-runner php artisan test --parallel
            ;;
        "coverage")
            docker-compose -f docker-compose.yml -f docker-compose.test.yml exec test-runner php artisan test --coverage --min=80
            ;;
    esac
    
    # Stop test environment
    docker-compose -f docker-compose.yml -f docker-compose.test.yml --profile $profile down
}

# Main script
echo "🧪 Talent2Income Docker Test Runner"
echo "=================================="

# Check if Docker is running
if ! docker info > /dev/null 2>&1; then
    print_error "Docker is not running. Please start Docker first."
    exit 1
fi

# Create test network if it doesn't exist
docker network create talent2income_network 2>/dev/null || true

# Parse command line arguments
case "${1:-all}" in
    "unit")
        run_tests "unit" "testing"
        ;;
    "feature")
        run_tests "feature" "testing"
        ;;
    "integration")
        run_tests "integration" "testing"
        ;;
    "performance")
        run_tests "performance" "performance"
        ;;
    "security")
        run_tests "security" "security"
        ;;
    "coverage")
        run_tests "coverage" "testing"
        ;;
    "all")
        print_status "Running comprehensive test suite..."
        run_tests "all" "testing"
        ;;
    "setup")
        print_status "Setting up test environment..."
        docker-compose -f docker-compose.yml -f docker-compose.test.yml --profile testing up -d
        docker-compose -f docker-compose.yml -f docker-compose.test.yml exec test-runner composer install
        docker-compose -f docker-compose.yml -f docker-compose.test.yml exec test-runner php artisan migrate --force
        print_success "Test environment ready!"
        ;;
    "teardown")
        print_status "Tearing down test environment..."
        docker-compose -f docker-compose.yml -f docker-compose.test.yml down -v
        print_success "Test environment cleaned up!"
        ;;
    "help"|"-h"|"--help")
        echo "Usage: $0 [test_type]"
        echo ""
        echo "Test types:"
        echo "  unit         Run unit tests"
        echo "  feature      Run feature tests"
        echo "  integration  Run integration tests"
        echo "  performance  Run performance tests"
        echo "  security     Run security tests"
        echo "  coverage     Run tests with coverage report"
        echo "  all          Run all tests (default)"
        echo "  setup        Set up test environment"
        echo "  teardown     Clean up test environment"
        echo "  help         Show this help message"
        echo ""
        echo "Examples:"
        echo "  $0 unit              # Run only unit tests"
        echo "  $0 coverage          # Run tests with coverage"
        echo "  $0 setup             # Set up test environment"
        exit 0
        ;;
    *)
        print_error "Unknown test type: $1"
        print_status "Run '$0 help' for usage information"
        exit 1
        ;;
esac

print_success "Test execution completed!"