# Docker Setup Guide for Talent2Income API

This guide provides comprehensive instructions for setting up the Talent2Income Laravel API using Docker.

## 🚀 Quick Start

1. **Clone the repository and navigate to the backend directory**
2. **Run the setup script:**
   ```bash
   ./docker-setup.sh
   ```
3. **Access the API at:** http://localhost:8000

## 📋 Prerequisites

- Docker Engine 20.10+
- Docker Compose 2.0+
- At least 4GB RAM available for containers
- Ports 8000, 3306, 6379, 1025, 8025 available

## 🏗️ Architecture Overview

The Docker setup includes the following services:

### Core Services
- **app**: Laravel API application (PHP 8.2-FPM)
- **mysql**: MySQL 8.0 database
- **redis**: Redis 7 for caching, sessions, and queues

### Supporting Services
- **queue**: Laravel queue worker
- **scheduler**: Laravel task scheduler
- **mailhog**: Email testing service

### Optional Services (Profiles)
- **nginx**: Reverse proxy (production profile)
- **echo-server**: WebSocket server (broadcasting profile)

## 🔧 Manual Setup

If you prefer manual setup or need to customize the configuration:

### 1. Environment Configuration

```bash
# Copy the Docker environment file
cp .env.docker .env

# Edit the .env file as needed
nano .env
```

### 2. Build and Start Services

```bash
# Build containers
docker-compose build

# Start core services
docker-compose up -d

# Start with production profile (includes Nginx)
docker-compose --profile production up -d

# Start with broadcasting profile (includes Echo Server)
docker-compose --profile broadcasting up -d
```

### 3. Application Setup

```bash
# Install dependencies
docker-compose exec app composer install

# Generate application key
docker-compose exec app php artisan key:generate

# Run migrations
docker-compose exec app php artisan migrate

# Seed database
docker-compose exec app php artisan db:seed

# Create storage link
docker-compose exec app php artisan storage:link

# Cache configuration
docker-compose exec app php artisan config:cache
```

## 🌐 Service Access

| Service | URL/Connection | Credentials |
|---------|----------------|-------------|
| API | http://localhost:8000 | - |
| MySQL | localhost:3306 | talent2income_user / talent2income_password |
| Redis | localhost:6379 | No password |
| MailHog UI | http://localhost:8025 | - |
| Nginx (optional) | http://localhost:80 | - |

## 🗄️ Database Configuration

### Multiple Databases
The setup creates three databases:
- `talent2income` - Main application database
- `talent2income_test` - Testing database
- `talent2income_staging` - Staging database

### Database Users
- `talent2income_user` - Full access to all databases
- `talent2income_readonly` - Read-only access for analytics

### Connecting to Database

```bash
# Using Docker Compose
docker-compose exec mysql mysql -u talent2income_user -p talent2income

# Using external client
mysql -h localhost -P 3306 -u talent2income_user -p talent2income
```

## 🔄 Queue Management

The setup includes a dedicated queue worker container:

```bash
# View queue worker logs
docker-compose logs -f queue

# Restart queue worker
docker-compose restart queue

# Scale queue workers
docker-compose up -d --scale queue=3
```

## 📧 Email Testing

MailHog captures all outgoing emails:

- **SMTP**: localhost:1025
- **Web UI**: http://localhost:8025

## 🧪 Testing

### Running Tests

```bash
# Run all tests
docker-compose exec app php artisan test

# Run specific test suite
docker-compose exec app php artisan test --testsuite=Feature

# Run tests with coverage (requires Xdebug)
docker-compose exec app php artisan test --coverage

# Run parallel tests
docker-compose exec app php artisan test --parallel
```

### Test Database

Tests use a separate database configuration:
- SQLite in-memory for unit tests
- MySQL `talent2income_test` for integration tests

## 🔍 Monitoring and Debugging

### Container Logs

```bash
# View all logs
docker-compose logs -f

# View specific service logs
docker-compose logs -f app
docker-compose logs -f mysql
docker-compose logs -f redis
```

### Container Access

```bash
# Access app container
docker-compose exec app bash

# Access MySQL container
docker-compose exec mysql bash

# Access Redis CLI
docker-compose exec redis redis-cli
```

### Health Checks

All services include health checks:

```bash
# Check service health
docker-compose ps

# View health check logs
docker inspect talent2income_api --format='{{json .State.Health}}'
```

## 🚀 Production Deployment

### Using Production Profile

```bash
# Start with Nginx reverse proxy
docker-compose --profile production up -d

# Build production images
docker-compose build --target production
```

### Environment Variables

Key production environment variables:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
DB_HOST=your-production-db-host
REDIS_HOST=your-production-redis-host
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
```

### SSL Configuration

1. Place SSL certificates in `docker/nginx/ssl/`
2. Uncomment HTTPS server block in `docker/nginx/sites-available/talent2income.conf`
3. Update `APP_URL` to use HTTPS

## 🔧 Customization

### Adding Services

To add new services, edit `docker-compose.yml`:

```yaml
services:
  your-service:
    image: your-image
    depends_on:
      - app
    networks:
      - talent2income_network
```

### Environment Overrides

Create `docker-compose.override.yml` for local customizations:

```yaml
version: '3.8'
services:
  app:
    ports:
      - "8080:8000"  # Use different port
    environment:
      - APP_DEBUG=true
```

### Volume Mounts

For development, the entire application is mounted as a volume:

```yaml
volumes:
  - .:/var/www/html
```

For production, only specific directories should be mounted.

## 🛠️ Troubleshooting

### Common Issues

1. **Port conflicts**
   ```bash
   # Check port usage
   netstat -tulpn | grep :8000
   
   # Change ports in docker-compose.yml
   ports:
     - "8080:8000"
   ```

2. **Permission issues**
   ```bash
   # Fix storage permissions
   docker-compose exec app chown -R www-data:www-data storage
   docker-compose exec app chmod -R 775 storage
   ```

3. **Database connection issues**
   ```bash
   # Check MySQL is running
   docker-compose ps mysql
   
   # Check MySQL logs
   docker-compose logs mysql
   
   # Test connection
   docker-compose exec app php artisan tinker
   >>> DB::connection()->getPdo()
   ```

4. **Redis connection issues**
   ```bash
   # Check Redis is running
   docker-compose ps redis
   
   # Test Redis connection
   docker-compose exec redis redis-cli ping
   ```

### Performance Optimization

1. **Increase memory limits**
   ```yaml
   services:
     app:
       deploy:
         resources:
           limits:
             memory: 512M
   ```

2. **Use production PHP configuration**
   ```dockerfile
   # In Dockerfile
   COPY docker/php/php.ini /usr/local/etc/php/
   ```

3. **Enable OPcache**
   ```ini
   opcache.enable=1
   opcache.memory_consumption=128
   opcache.max_accelerated_files=4000
   ```

## 🔄 Maintenance

### Regular Tasks

```bash
# Update containers
docker-compose pull
docker-compose up -d

# Clean up unused images
docker image prune -f

# Backup database
docker-compose exec mysql mysqldump -u talent2income_user -p talent2income > backup.sql

# View disk usage
docker system df
```

### Scaling

```bash
# Scale queue workers
docker-compose up -d --scale queue=3

# Scale with different profiles
docker-compose --profile production up -d --scale app=2
```

## 📚 Additional Resources

- [Laravel Documentation](https://laravel.com/docs)
- [Docker Compose Documentation](https://docs.docker.com/compose/)
- [MySQL Docker Image](https://hub.docker.com/_/mysql)
- [Redis Docker Image](https://hub.docker.com/_/redis)
- [MailHog Documentation](https://github.com/mailhog/MailHog)

## 🆘 Support

If you encounter issues:

1. Check the logs: `docker-compose logs -f`
2. Verify service health: `docker-compose ps`
3. Review this documentation
4. Check the Laravel logs: `storage/logs/laravel.log`