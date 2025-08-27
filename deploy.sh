#!/bin/bash

# Talent2Income Platform Deployment Script
# This script handles deployment for different environments

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Default values
ENVIRONMENT="production"
SKIP_BACKUP=false
SKIP_MIGRATIONS=false
SKIP_CACHE=false

# Function to print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to show usage
show_usage() {
    echo "Usage: $0 [OPTIONS]"
    echo ""
    echo "Options:"
    echo "  -e, --environment ENV    Set environment (production, staging, development)"
    echo "  --skip-backup           Skip database backup"
    echo "  --skip-migrations       Skip running migrations"
    echo "  --skip-cache            Skip cache operations"
    echo "  -h, --help              Show this help message"
    echo ""
    echo "Examples:"
    echo "  $0 -e production"
    echo "  $0 -e staging --skip-backup"
    echo "  $0 --environment development --skip-migrations"
}

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        -e|--environment)
            ENVIRONMENT="$2"
            shift 2
            ;;
        --skip-backup)
            SKIP_BACKUP=true
            shift
            ;;
        --skip-migrations)
            SKIP_MIGRATIONS=true
            shift
            ;;
        --skip-cache)
            SKIP_CACHE=true
            shift
            ;;
        -h|--help)
            show_usage
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            show_usage
            exit 1
            ;;
    esac
done

# Validate environment
if [[ ! "$ENVIRONMENT" =~ ^(production|staging|development)$ ]]; then
    print_error "Invalid environment: $ENVIRONMENT"
    print_error "Valid environments: production, staging, development"
    exit 1
fi

print_status "Starting deployment for environment: $ENVIRONMENT"

# Check if we're in the correct directory
if [[ ! -f "artisan" ]]; then
    print_error "artisan file not found. Please run this script from the Laravel project root."
    exit 1
fi

# Load environment file
ENV_FILE=".env.$ENVIRONMENT"
if [[ -f "$ENV_FILE" ]]; then
    print_status "Loading environment file: $ENV_FILE"
    cp "$ENV_FILE" .env
else
    print_warning "Environment file $ENV_FILE not found, using existing .env"
fi

# Put application in maintenance mode (production only)
if [[ "$ENVIRONMENT" == "production" ]]; then
    print_status "Putting application in maintenance mode..."
    php artisan down --retry=60 --secret="deployment-secret-key"
fi

# Create backup (if not skipped)
if [[ "$SKIP_BACKUP" == false && "$ENVIRONMENT" == "production" ]]; then
    print_status "Creating database backup..."
    BACKUP_FILE="backup_$(date +%Y%m%d_%H%M%S).sql"
    php artisan db:backup --filename="$BACKUP_FILE" || print_warning "Backup failed, continuing..."
fi

# Update dependencies
print_status "Installing/updating Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Clear and cache configuration
if [[ "$SKIP_CACHE" == false ]]; then
    print_status "Clearing and caching configuration..."
    php artisan config:clear
    php artisan config:cache
    
    print_status "Clearing and caching routes..."
    php artisan route:clear
    php artisan route:cache
    
    print_status "Clearing and caching views..."
    php artisan view:clear
    php artisan view:cache
    
    print_status "Optimizing autoloader..."
    composer dump-autoload --optimize
fi

# Run database migrations
if [[ "$SKIP_MIGRATIONS" == false ]]; then
    print_status "Running database migrations..."
    php artisan migrate --force
fi

# Seed database (development only)
if [[ "$ENVIRONMENT" == "development" ]]; then
    print_status "Seeding database..."
    php artisan db:seed --force
fi

# Clear application cache
print_status "Clearing application cache..."
php artisan cache:clear

# Generate application key if not set
if ! grep -q "APP_KEY=base64:" .env; then
    print_status "Generating application key..."
    php artisan key:generate --force
fi

# Create storage symlink
print_status "Creating storage symlink..."
php artisan storage:link

# Set proper permissions
print_status "Setting proper file permissions..."
chmod -R 755 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || print_warning "Could not change ownership (may require sudo)"

# Queue restart (production only)
if [[ "$ENVIRONMENT" == "production" ]]; then
    print_status "Restarting queue workers..."
    php artisan queue:restart
fi

# Bring application back online (production only)
if [[ "$ENVIRONMENT" == "production" ]]; then
    print_status "Bringing application back online..."
    php artisan up
fi

# Run health check
print_status "Running health check..."
if php artisan health:check 2>/dev/null; then
    print_status "Health check passed!"
else
    print_warning "Health check command not available or failed"
fi

print_status "Deployment completed successfully for environment: $ENVIRONMENT"

# Show post-deployment information
echo ""
echo "Post-deployment checklist:"
echo "- Verify application is accessible"
echo "- Check logs for any errors: tail -f storage/logs/laravel.log"
echo "- Monitor queue workers: php artisan queue:monitor"
echo "- Test critical functionality"

if [[ "$ENVIRONMENT" == "production" ]]; then
    echo "- Monitor application performance"
    echo "- Check error rates and response times"
fi