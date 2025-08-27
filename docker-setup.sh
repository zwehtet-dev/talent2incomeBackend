#!/bin/bash

# Talent2Income Docker Setup Script
# This script sets up the complete Docker environment for the Laravel API

set -e

echo "🚀 Setting up Talent2Income Docker Environment..."

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Function to print colored output
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

# Check if Docker is installed
if ! command -v docker &> /dev/null; then
    print_error "Docker is not installed. Please install Docker first."
    exit 1
fi

# Check if Docker Compose is installed
if ! command -v docker compose &> /dev/null; then
    print_error "Docker Compose is not installed. Please install Docker Compose first."
    exit 1
fi

# Create necessary directories
print_status "Creating necessary directories..."
mkdir -p storage/app/public
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Set permissions
print_status "Setting permissions..."
chmod -R 775 storage
chmod -R 775 bootstrap/cache

# Copy environment file
if [ ! -f .env ]; then
    print_status "Creating .env file from .env.docker..."
    cp .env.docker .env
else
    print_warning ".env file already exists. Skipping..."
fi

# Build and start containers
print_status "Building Docker containers..."
docker compose build --no-cache

print_status "Starting Docker containers..."
docker compose up -d

# Wait for services to be ready
print_status "Waiting for services to be ready..."
sleep 30

# Install Composer dependencies
print_status "Installing Composer dependencies..."
docker compose exec app composer install --optimize-autoloader

# Generate application key if not set
print_status "Generating application key..."
docker compose exec app php artisan key:generate

# Run database migrations
print_status "Running database migrations..."
docker compose exec app php artisan migrate --force

# Seed the database
print_status "Seeding the database..."
docker compose exec app php artisan db:seed --force

# Create storage link
print_status "Creating storage link..."
docker compose exec app php artisan storage:link

# Clear and cache configuration
print_status "Optimizing application..."
docker compose exec app php artisan config:cache
docker compose exec app php artisan route:cache
docker compose exec app php artisan view:cache

# Run tests to verify setup
print_status "Running tests to verify setup..."
docker compose exec app php artisan test --parallel

print_success "Docker environment setup completed!"
print_status "Services are running on:"
echo "  📱 API: http://localhost:8000"
echo "  🗄️  MySQL: localhost:3306"
echo "  🔴 Redis: localhost:6379"
echo "  📧 MailHog: http://localhost:8025"
echo "  🌐 Nginx (if enabled): http://localhost:80"

print_status "Useful commands:"
echo "  🐳 View logs: docker compose logs -f"
echo "  🔧 Access app container: docker compose exec app bash"
echo "  🗄️  Access MySQL: docker compose exec mysql mysql -u talent2income_user -p talent2income"
echo "  🧪 Run tests: docker compose exec app php artisan test"
echo "  🛑 Stop services: docker compose down"
echo "  🗑️  Clean up: docker compose down -v --remove-orphans"

print_success "Setup complete! Your Talent2Income API is ready to use."