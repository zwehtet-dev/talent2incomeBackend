# Database Performance Optimization & Monitoring

This document describes the comprehensive database performance optimization and monitoring system implemented for the Talent2Income platform.

## Overview

The database performance system provides:

- **Real-time Query Monitoring** - Tracks slow queries and performance metrics
- **Connection Pool Management** - Optimizes database connections with load balancing
- **Automated Backup System** - Full, incremental, and point-in-time recovery backups
- **Performance Analytics** - Detailed metrics and trend analysis
- **Automated Optimization** - Table analysis and optimization scheduling
- **Alert System** - Proactive notifications for performance issues

## Components

### 1. Database Performance Service

**Location**: `app/Services/DatabasePerformanceService.php`

**Features**:
- Query execution monitoring and logging
- Slow query detection and analysis
- Performance metrics collection
- Query optimization suggestions
- EXPLAIN query analysis
- Table optimization and analysis

**Key Methods**:
```php
// Enable query monitoring
$service->enableQueryLogging();

// Get performance metrics
$metrics = $service->getPerformanceMetrics();

// Analyze slow query
$explanation = $service->explainQuery($sql, $bindings);

// Optimize table
$service->optimizeTable('table_name');
```

### 2. Connection Pool Service

**Location**: `app/Services/DatabaseConnectionPoolService.php`

**Features**:
- Connection pooling with different pool types (read, write, analytics)
- Load balancing across connections
- Connection health monitoring
- Automatic cleanup of stale connections
- Pool utilization tracking

**Pool Types**:
- **Read Pool**: For SELECT queries
- **Write Pool**: For INSERT/UPDATE/DELETE queries  
- **Analytics Pool**: For reporting and analytics queries

**Usage**:
```php
// Get connection from specific pool
$connection = $poolService->getConnection('read');

// Get load-balanced connection
$connection = $poolService->getLoadBalancedConnection('select');

// Release connection back to pool
$poolService->releaseConnection('read', $connection);
```

### 3. Database Backup Service

**Location**: `app/Services/DatabaseBackupService.php`

**Features**:
- Full database backups
- Incremental backups
- Point-in-time recovery
- Backup compression and encryption
- Automated retention policy
- Backup verification

**Backup Types**:
```php
// Full backup
$result = $backupService->createFullBackup();

// Incremental backup
$result = $backupService->createIncrementalBackup($lastBackupTime);

// Point-in-time backup
$result = $backupService->createPointInTimeBackup($timestamp);

// Restore from backup
$result = $backupService->restoreFromBackup($filename);
```

## Console Commands

### Database Optimization Command

```bash
# Display performance metrics
php artisan db:optimize --metrics

# Analyze all tables
php artisan db:optimize --analyze

# Optimize all tables
php artisan db:optimize --optimize

# Show EXPLAIN analysis for slow queries
php artisan db:optimize --explain

# Clean up stale connections
php artisan db:optimize --cleanup

# Run full optimization (all options)
php artisan db:optimize
```

### Database Monitoring Command

```bash
# Single performance check
php artisan db:monitor

# Enable alerts for performance issues
php artisan db:monitor --alert

# Continuous monitoring (runs until stopped)
php artisan db:monitor --continuous

# Set monitoring interval (default: 60 seconds)
php artisan db:monitor --continuous --interval=30
```

### Database Backup Command

```bash
# Create full backup
php artisan db:backup --type=full

# Create incremental backup
php artisan db:backup --type=incremental --since="2024-01-01 00:00:00"

# Create point-in-time backup
php artisan db:backup --type=point-in-time --point-in-time="2024-01-01 12:00:00"

# List available backups
php artisan db:backup --list

# Show backup statistics
php artisan db:backup --stats

# Restore from backup
php artisan db:backup --restore=backup_filename.sql.gz

# Clean up old backups
php artisan db:backup --cleanup
```

## Configuration

### Database Performance Configuration

**File**: `config/database_performance.php`

```php
return [
    'monitoring' => [
        'enabled' => true,
        'slow_query_thresholds' => [
            'select' => 1000, // milliseconds
            'insert' => 500,
            'update' => 500,
            'delete' => 500,
        ],
        'alert_thresholds' => [
            'connection_usage_percent' => 80,
            'slow_query_percent' => 10,
            'avg_query_time' => 500,
            'pool_utilization' => 90,
        ],
    ],
    
    'connection_pool' => [
        'enabled' => true,
        'max_connections' => 20,
        'min_connections' => 5,
        'connection_timeout' => 30,
    ],
    
    'alerts' => [
        'enabled' => true,
        'email' => [
            'enabled' => true,
            'recipients' => ['admin@example.com'],
        ],
    ],
];
```

### Backup Configuration

**File**: `config/backup.php`

```php
return [
    'enabled' => true,
    
    'storage' => [
        'disk' => 'local',
        'path' => 'backups/database',
    ],
    
    'compression' => [
        'enabled' => true,
        'level' => 6,
    ],
    
    'retention' => [
        'daily' => 7,
        'weekly' => 4,
        'monthly' => 12,
        'yearly' => 5,
    ],
    
    'schedule' => [
        'full_backup' => 'daily',
        'incremental_backup' => 'hourly',
    ],
];
```

## Environment Variables

Add these to your `.env` file:

```env
# Database Performance Monitoring
DB_MONITORING_ENABLED=true
DB_SLOW_SELECT_THRESHOLD=1000
DB_SLOW_INSERT_THRESHOLD=500
DB_SLOW_UPDATE_THRESHOLD=500
DB_SLOW_DELETE_THRESHOLD=500

# Connection Pool Settings
DB_POOL_ENABLED=true
DB_POOL_MAX_CONNECTIONS=20
DB_POOL_MIN_CONNECTIONS=5
DB_POOL_CONNECTION_TIMEOUT=30

# Alert Settings
DB_ALERTS_ENABLED=true
DB_EMAIL_ALERTS_ENABLED=true
DB_ALERT_EMAILS="admin@example.com,dba@example.com"

# Backup Settings
BACKUP_ENABLED=true
BACKUP_DISK=local
BACKUP_COMPRESSION_ENABLED=true
BACKUP_RETENTION_DAILY=7
BACKUP_RETENTION_WEEKLY=4
BACKUP_RETENTION_MONTHLY=12
```

## Database Schema

The system creates several tables for monitoring and backup management:

### Performance Monitoring Tables

```sql
-- Performance metrics
CREATE TABLE database_performance_metrics (
    id BIGINT PRIMARY KEY,
    timestamp TIMESTAMP,
    connection_usage_percent DECIMAL(5,2),
    avg_query_time DECIMAL(10,2),
    slow_query_percent DECIMAL(5,2),
    total_queries INTEGER,
    pool_stats JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Slow query logs
CREATE TABLE slow_query_logs (
    id BIGINT PRIMARY KEY,
    sql TEXT,
    bindings JSON,
    execution_time DECIMAL(10,2),
    query_type VARCHAR(20),
    suggestions TEXT,
    explain_data JSON,
    executed_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Connection pool statistics
CREATE TABLE connection_pool_stats (
    id BIGINT PRIMARY KEY,
    pool_type VARCHAR(20),
    active_connections INTEGER,
    idle_connections INTEGER,
    total_connections INTEGER,
    utilization_percent DECIMAL(5,2),
    recorded_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

### Backup Management Tables

```sql
-- Backup logs
CREATE TABLE backup_logs (
    id BIGINT PRIMARY KEY,
    filename VARCHAR(255),
    type ENUM('full', 'incremental', 'point_in_time'),
    path VARCHAR(255),
    size BIGINT,
    metadata TEXT,
    status ENUM('completed', 'failed', 'in_progress'),
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Scheduled Tasks

The system automatically schedules the following tasks:

```php
// Daily table analysis at 2:00 AM
$schedule->command('db:optimize --analyze')
    ->daily()->at('02:00');

// Weekly table optimization on Sundays at 3:00 AM
$schedule->command('db:optimize --optimize')
    ->weekly()->sundays()->at('03:00');

// Performance monitoring every 5 minutes
$schedule->command('db:monitor --alert')
    ->everyFiveMinutes();

// Daily full backup at 1:00 AM
$schedule->command('db:backup --type=full')
    ->daily()->at('01:00');

// Hourly incremental backup
$schedule->command('db:backup --type=incremental')
    ->hourly();
```

## Monitoring and Alerts

### Performance Thresholds

The system monitors these key metrics:

- **Connection Usage**: Alert when > 80% of max connections used
- **Slow Query Percentage**: Alert when > 10% of queries are slow
- **Average Query Time**: Alert when > 500ms average execution time
- **Pool Utilization**: Alert when > 90% pool utilization

### Alert Channels

- **Email**: Sends alerts to configured admin emails
- **Log**: Writes alerts to application logs
- **Slack**: Optional Slack webhook integration

### Alert Types

- **Critical**: Immediate attention required (> 95% thresholds)
- **Warning**: Performance degradation detected
- **Info**: General performance information

## Best Practices

### Query Optimization

1. **Use Indexes**: Ensure proper indexing on frequently queried columns
2. **Avoid SELECT ***: Specify only needed columns
3. **Use LIMIT**: Limit result sets when possible
4. **Optimize JOINs**: Use appropriate JOIN types and conditions
5. **Use Query Builder**: Leverage Laravel's query builder for optimization

### Connection Management

1. **Pool Configuration**: Set appropriate pool sizes based on load
2. **Connection Cleanup**: Regularly clean up stale connections
3. **Load Balancing**: Use appropriate pool types for different operations
4. **Monitoring**: Monitor pool utilization and adjust as needed

### Backup Strategy

1. **Regular Backups**: Schedule daily full and hourly incremental backups
2. **Test Restores**: Regularly test backup restoration procedures
3. **Off-site Storage**: Store backups in multiple locations
4. **Retention Policy**: Implement appropriate backup retention
5. **Monitoring**: Monitor backup success and storage usage

## Troubleshooting

### Common Issues

1. **High Connection Usage**
   - Increase max_connections in database configuration
   - Optimize connection pool settings
   - Check for connection leaks in application code

2. **Slow Queries**
   - Review EXPLAIN output for optimization opportunities
   - Add missing indexes
   - Optimize query structure
   - Consider query caching

3. **Backup Failures**
   - Check disk space availability
   - Verify mysqldump binary availability
   - Review backup logs for specific errors
   - Check database permissions

### Performance Tuning

1. **Database Configuration**
   - Tune MySQL/PostgreSQL configuration parameters
   - Optimize buffer sizes and cache settings
   - Configure appropriate timeout values

2. **Application Level**
   - Use eager loading to prevent N+1 queries
   - Implement query result caching
   - Optimize database schema design
   - Use database-specific features (partitioning, etc.)

## Testing

The system includes comprehensive tests:

- **Unit Tests**: Test individual service methods
- **Feature Tests**: Test console commands and integration
- **Performance Tests**: Validate optimization effectiveness

Run tests with:
```bash
php artisan test tests/Unit/DatabasePerformanceServiceTest.php
php artisan test tests/Unit/DatabaseConnectionPoolServiceTest.php
php artisan test tests/Unit/DatabaseBackupServiceTest.php
php artisan test tests/Feature/DatabasePerformanceCommandsTest.php
```

## Logging

The system uses dedicated log channels:

- **slow_queries**: Logs slow query details
- **performance**: Logs performance metrics and alerts
- **database**: Logs database operations and connection pool events
- **backup**: Logs backup operations and status

Log files are located in `storage/logs/` with daily rotation.