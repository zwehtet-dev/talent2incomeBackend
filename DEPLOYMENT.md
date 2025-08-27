# Talent2Income Platform - Deployment Guide

## Overview

This document provides comprehensive instructions for deploying the Talent2Income platform backend across different environments (development, staging, production).

## Prerequisites

### System Requirements

- PHP 8.2 or higher
- Composer 2.x
- MySQL 8.0 or higher (or MariaDB 10.4+)
- Redis 6.0+ (recommended for production)
- Node.js 18+ and npm (for asset compilation)
- Web server (Apache/Nginx)

### PHP Extensions

Ensure the following PHP extensions are installed:

```bash
# Required extensions
php-mysql
php-redis
php-mbstring
php-xml
php-curl
php-zip
php-gd
php-intl
php-bcmath
php-json
php-tokenizer
php-fileinfo
php-openssl
```

## Environment Setup

### Development Environment

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd talent2income_backend
   ```

2. **Install dependencies**
   ```bash
   composer install
   npm install
   ```

3. **Environment configuration**
   ```bash
   cp .env.example .env
   # Edit .env with your local database credentials
   ```

4. **Generate application key**
   ```bash
   php artisan key:generate
   ```

5. **Database setup**
   ```bash
   # Create database
   mysql -u root -p -e "CREATE DATABASE talent2income_dev CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   
   # Run migrations
   php artisan migrate
   
   # Seed database (optional)
   php artisan db:seed
   ```

6. **Storage setup**
   ```bash
   php artisan storage:link
   chmod -R 755 storage bootstrap/cache
   ```

7. **Start development server**
   ```bash
   php artisan serve
   # Or use the dev script
   composer run dev
   ```

### Staging Environment

1. **Server preparation**
   ```bash
   # Update system packages
   sudo apt update && sudo apt upgrade -y
   
   # Install required packages
   sudo apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-redis \
     php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-gd \
     php8.2-intl php8.2-bcmath nginx mysql-server redis-server
   ```

2. **Deploy application**
   ```bash
   # Clone repository
   git clone <repository-url> /var/www/talent2income
   cd /var/www/talent2income
   
   # Use staging environment
   ./deploy.sh -e staging
   ```

3. **Web server configuration**
   ```bash
   # Copy nginx configuration
   sudo cp deployment/nginx/staging.conf /etc/nginx/sites-available/talent2income-staging
   sudo ln -s /etc/nginx/sites-available/talent2income-staging /etc/nginx/sites-enabled/
   sudo nginx -t && sudo systemctl reload nginx
   ```

### Production Environment

1. **Server preparation** (similar to staging but with production optimizations)

2. **SSL Certificate setup**
   ```bash
   # Using Let's Encrypt
   sudo apt install certbot python3-certbot-nginx
   sudo certbot --nginx -d api.talent2income.com
   ```

3. **Deploy application**
   ```bash
   ./deploy.sh -e production
   ```

4. **Process monitoring setup**
   ```bash
   # Install supervisor for queue workers
   sudo apt install supervisor
   sudo cp deployment/supervisor/talent2income-worker.conf /etc/supervisor/conf.d/
   sudo supervisorctl reread && sudo supervisorctl update
   ```

## Environment Files

### Development (.env)
- Database: Local MySQL
- Cache: Database/File
- Queue: Database
- Mail: Log driver
- Debug: Enabled

### Staging (.env.staging)
- Database: Staging MySQL with replica
- Cache: Redis
- Queue: Redis
- Mail: SMTP
- Debug: Enabled with limited logging

### Production (.env.production)
- Database: Production MySQL with read replicas
- Cache: Redis cluster
- Queue: Redis with multiple workers
- Mail: Production SMTP
- Debug: Disabled
- Enhanced logging and monitoring

## Database Configuration

### Read/Write Splitting

The application is prepared for read/write splitting. Configure in your environment:

```env
# Write database (primary)
DB_CONNECTION=mysql
DB_HOST=primary-db-host
DB_DATABASE=talent2income_prod
DB_USERNAME=app_user
DB_PASSWORD=secure_password

# Read database (replica)
DB_READ_HOST=replica-db-host
DB_READ_DATABASE=talent2income_prod
DB_READ_USERNAME=app_user_read
DB_READ_PASSWORD=secure_password
```

### Database Optimization

```sql
-- Recommended MySQL configuration for production
[mysqld]
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
query_cache_type = 1
query_cache_size = 64M
max_connections = 200
```

## Caching Strategy

### Redis Configuration

```bash
# /etc/redis/redis.conf
maxmemory 1gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### Cache Usage

- **Config Cache**: `php artisan config:cache`
- **Route Cache**: `php artisan route:cache`
- **View Cache**: `php artisan view:cache`
- **Application Cache**: Redis for sessions, cache, and queues

## Queue Management

### Supervisor Configuration

```ini
[program:talent2income-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/talent2income/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/talent2income/storage/logs/worker.log
stopwaitsecs=3600
```

### Queue Monitoring

```bash
# Monitor queue status
php artisan queue:monitor

# Restart workers after deployment
php artisan queue:restart

# Process failed jobs
php artisan queue:retry all
```

## Logging and Monitoring

### Log Channels

- **API Logs**: `storage/logs/api.log`
- **Auth Logs**: `storage/logs/auth.log`
- **Payment Logs**: `storage/logs/payments.log`
- **Security Logs**: `storage/logs/security.log`
- **Performance Logs**: `storage/logs/performance.log`

### Log Rotation

```bash
# Add to /etc/logrotate.d/talent2income
/var/www/talent2income/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
    postrotate
        /usr/bin/supervisorctl restart talent2income-worker:*
    endscript
}
```

## Security Considerations

### File Permissions

```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/talent2income

# Set directory permissions
find /var/www/talent2income -type d -exec chmod 755 {} \;

# Set file permissions
find /var/www/talent2income -type f -exec chmod 644 {} \;

# Set executable permissions
chmod +x /var/www/talent2income/artisan
chmod +x /var/www/talent2income/deploy.sh
```

### Environment Security

- Store sensitive environment variables in secure vaults
- Use strong, unique passwords for all services
- Enable firewall and restrict access to necessary ports
- Regular security updates and patches
- SSL/TLS encryption for all communications

## Backup Strategy

### Database Backups

```bash
# Daily backup script
#!/bin/bash
BACKUP_DIR="/var/backups/talent2income"
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u backup_user -p talent2income_prod > "$BACKUP_DIR/db_backup_$DATE.sql"
gzip "$BACKUP_DIR/db_backup_$DATE.sql"

# Keep only last 30 days
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +30 -delete
```

### File Backups

```bash
# Application files backup
rsync -av --exclude='node_modules' --exclude='vendor' \
  /var/www/talent2income/ /var/backups/talent2income/app_backup_$(date +%Y%m%d)/
```

## Performance Optimization

### OPcache Configuration

```ini
; /etc/php/8.2/fpm/conf.d/10-opcache.ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
opcache.fast_shutdown=1
```

### PHP-FPM Tuning

```ini
; /etc/php/8.2/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000
```

## Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   sudo chown -R www-data:www-data storage bootstrap/cache
   chmod -R 755 storage bootstrap/cache
   ```

2. **Queue Not Processing**
   ```bash
   php artisan queue:restart
   sudo supervisorctl restart talent2income-worker:*
   ```

3. **Cache Issues**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

4. **Database Connection Issues**
   - Check database credentials in .env
   - Verify database server is running
   - Check firewall rules
   - Verify user permissions

### Health Checks

```bash
# Application health
curl -f http://localhost/up || exit 1

# Database connectivity
php artisan tinker --execute="DB::connection()->getPdo();"

# Redis connectivity
redis-cli ping

# Queue status
php artisan queue:monitor
```

## Deployment Checklist

### Pre-deployment

- [ ] Code review completed
- [ ] Tests passing
- [ ] Database migrations reviewed
- [ ] Environment variables updated
- [ ] Backup created
- [ ] Maintenance mode enabled (production)

### Deployment

- [ ] Code deployed
- [ ] Dependencies updated
- [ ] Migrations executed
- [ ] Cache cleared and rebuilt
- [ ] Queue workers restarted
- [ ] Storage permissions set

### Post-deployment

- [ ] Application accessible
- [ ] Health checks passing
- [ ] Logs monitored for errors
- [ ] Critical functionality tested
- [ ] Performance metrics reviewed
- [ ] Maintenance mode disabled

## Support and Maintenance

### Regular Maintenance Tasks

- **Daily**: Monitor logs, check queue status
- **Weekly**: Review performance metrics, update dependencies
- **Monthly**: Security updates, backup verification
- **Quarterly**: Performance optimization, capacity planning

### Emergency Procedures

1. **Application Down**
   - Check web server status
   - Review error logs
   - Verify database connectivity
   - Check disk space and memory

2. **Database Issues**
   - Switch to read-only mode if needed
   - Restore from backup if necessary
   - Contact database administrator

3. **Performance Issues**
   - Enable maintenance mode
   - Clear all caches
   - Restart queue workers
   - Scale resources if needed

For additional support, contact the development team or refer to the Laravel documentation.