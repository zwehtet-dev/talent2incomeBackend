#!/bin/bash

# Talent2Income Docker Setup Script for Linux Server
# This script sets up the complete Docker environment for the Laravel API

set -euo pipefail

echo "🚀 Setting up Talent2Income Docker Environment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
print_status() { echo -e "${BLUE}[INFO]${NC} $1"; }
print_success() { echo -e "${GREEN}[SUCCESS]${NC} $1"; }
print_warning() { echo -e "${YELLOW}[WARNING]${NC} $1"; }
print_error() { echo -e "${RED}[ERROR]${NC} $1"; }

# Detect Docker Compose command (v2 preferred)
if docker compose version >/dev/null 2>&1; then
    COMPOSE="docker compose"
elif docker-compose version >/dev/null 2>&1; then
    COMPOSE="docker-compose"
else
    print_error "Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Ensure Docker is installed
if ! command -v docker &> /dev/null; then
    print_error "Docker is not installed. Please install Docker first."
    exit 1
fi

# Create necessary directories
print_status "Creating necessary directories..."
mkdir -p storage/app/public \
         storage/framework/{cache,sessions,views} \
         storage/logs \
         bootstrap/cache

# Set permissions (important for Linux server)
print_status "Setting permissions..."
chown -R $USER:$USER storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# Copy environment file if not present
if [ ! -f .env ]; then
    print_status "Creating .env file from .env.docker..."
    cp .env.docker .env
else
    print_warning ".env file already exists. Skipping..."
fi

# Build and start containers
print_status "Building Docker containers..."
$COMPOSE build --no-cache

print_status "Starting Docker containers..."
$COMPOSE up -d

# Wait until MySQL is ready
print_status "Waiting for MySQL to be ready..."
until $COMPOSE exec -T mysql mysqladmin ping -h "127.0.0.1" --silent; do
    sleep 3
    echo "⏳ Waiting for database..."
done

# Install Composer dependencies inside app container
print_status "Installing Composer dependencies..."
$COMPOSE exec -T app composer install --optimize-autoloader --no-interaction --prefer-dist

# Generate application key
print_status "Generating application key..."
$COMPOSE exec -T app php artisan key:generate --force

# Run migrations and seed
print_status "Running database migrations and seeding..."
$COMPOSE exec -T app php artisan migrate --force
$COMPOSE exec -T app php artisan db:seed --force

# Storage link
print_status "Creating storage link..."
$COMPOSE exec -T app php artisan storage:link || true

# Optimize caches
print_status "Optimizing application..."
$COMPOSE exec -T app php artisan config:cache
$COMPOSE exec -T app php artisan route:cache
$COMPOSE exec -T app php artisan view:cache

# Run tests (optional in production)
if [[ "${RUN_TESTS:-false}" == "true" ]]; then
    print_status "Running tests..."
    $COMPOSE exec -T app php artisan test --parallel
fi

print_success "Docker environment setup completed!"
print_status "Services are running on:"
echo "  📱 API: http://localhost:8000"
echo "  🗄️  MySQL: localhost:3306"
echo "  🔴 Redis: localhost:6379"
echo "  📧 MailHog: http://localhost:8025 (if enabled)"
echo "  🌐 Nginx: http://localhost (if enabled)"

print_status "Useful commands:"
echo "  🐳 View logs: $COMPOSE logs -f"
echo "  🔧 Access app container: $COMPOSE exec app bash"
echo "  🗄️  Access MySQL: $COMPOSE exec mysql mysql -u talent2income_user -p talent2income"
echo "  🧪 Run tests: $COMPOSE exec app php artisan test"
echo "  🛑 Stop services: $COMPOSE down"
echo "  🗑️  Clean up: $COMPOSE down -v --remove-orphans"

print_success "Setup complete! Your Talent2Income API is ready to use."
