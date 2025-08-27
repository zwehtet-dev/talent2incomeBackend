# Security Implementation Guide

## Overview

This document outlines the comprehensive security measures implemented in the Talent2Income platform backend. The security implementation follows industry best practices and includes multiple layers of protection.

## Security Features Implemented

### 1. CORS (Cross-Origin Resource Sharing) Protection

**Configuration**: `config/cors.php`

- Environment-specific origin allowlists
- Secure credential handling
- Proper header restrictions
- Subdomain pattern matching for production

**Environment Variables**:
```bash
CORS_ALLOWED_ORIGINS=http://localhost:3000,http://localhost:3001
```

### 2. Security Headers

**Middleware**: `App\Http\Middleware\SecurityHeadersMiddleware`

Implemented headers:
- `X-Frame-Options: DENY` - Prevents clickjacking
- `X-Content-Type-Options: nosniff` - Prevents MIME type sniffing
- `X-XSS-Protection: 1; mode=block` - XSS protection
- `Referrer-Policy: strict-origin-when-cross-origin` - Controls referrer information
- `Permissions-Policy` - Controls browser features
- `Strict-Transport-Security` - Forces HTTPS (when using HTTPS)
- Removes `Server` and `X-Powered-By` headers

### 3. Content Security Policy (CSP)

**Configuration**: `config/security.php`

- Configurable CSP directives
- Report-only mode for testing
- Environment-specific policies

**Environment Variables**:
```bash
CSP_ENABLED=false
CSP_REPORT_ONLY=true
```

### 4. Input Sanitization & XSS Prevention

**Middleware**: `App\Http\Middleware\InputSanitizationMiddleware`

Features:
- Automatic HTML entity encoding
- Dangerous tag stripping
- Null byte removal
- Content-Type validation for API requests

**Custom Validation Rules**:
- `no_xss` - Prevents XSS patterns
- `no_sql_injection` - Detects SQL injection attempts
- `strong_password` - Enforces password policies
- `safe_filename` - Validates file names

### 5. SQL Injection Prevention

**Service**: `App\Services\SecurityService`

Features:
- Parameter binding validation
- SQL injection pattern detection
- Automatic query sanitization
- Comprehensive logging of attempts

**Protected Patterns**:
- Union-based injections
- Boolean-based blind injections
- Time-based blind injections
- Stacked queries
- Function-based injections

### 6. CSRF Protection

**Middleware**: `App\Http\Middleware\CsrfProtectionMiddleware`

Features:
- Token validation for state-changing requests
- Multiple token sources (header, body)
- Automatic token generation
- API route exemption (using Sanctum tokens)

### 7. File Upload Security

**Service**: `App\Services\SecurityService::validateFileUpload()`

Features:
- File type validation
- MIME type verification
- File size limits
- Executable content detection
- Virus scanning preparation

**Configuration**:
```php
'file_upload_security' => [
    'allowed_extensions' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx'],
    'max_file_size' => 10 * 1024 * 1024, // 10MB
    'scan_for_viruses' => env('VIRUS_SCANNING_ENABLED', false),
],
```

### 8. IP Filtering

**Middleware**: `App\Http\Middleware\IpFilteringMiddleware`

Features:
- IP whitelist/blacklist support
- Admin-specific IP restrictions
- Automatic logging of blocked attempts

**Environment Variables**:
```bash
IP_FILTERING_ENABLED=false
IP_WHITELIST=
IP_BLACKLIST=
ADMIN_IP_WHITELIST=
```

### 9. Rate Limiting

Enhanced rate limiting with:
- Different limits for authenticated/unauthenticated users
- Login attempt protection
- API endpoint specific limits
- Password reset protection

**Environment Variables**:
```bash
THROTTLE_REQUESTS_PER_MINUTE=60
THROTTLE_LOGIN_ATTEMPTS=5
THROTTLE_LOGIN_DECAY_MINUTES=1
THROTTLE_LOCKOUT_DURATION=60
THROTTLE_API_AUTHENTICATED=100
THROTTLE_API_UNAUTHENTICATED=20
```

### 10. Security Logging & Auditing

**Channels**: `security`, `audit`

Logged events:
- Authentication attempts (success/failure)
- Password changes
- Permission changes
- Sensitive operations
- Security violations

**Configuration**:
```bash
AUDIT_LOGGING_ENABLED=true
```

### 11. Password Security

**Policy Configuration**:
```php
'password_policy' => [
    'min_length' => 8,
    'max_length' => 128,
    'require_uppercase' => true,
    'require_lowercase' => true,
    'require_numbers' => true,
    'require_symbols' => false,
    'prevent_common_passwords' => true,
    'prevent_personal_info' => true,
    'history_count' => 5,
    'expiry_days' => 90,
],
```

### 12. Session Security

Features:
- Session regeneration on login
- Session invalidation on logout
- Concurrent session limits
- Secure cookie settings

**Environment Variables**:
```bash
SESSION_ENCRYPT=true
SESSION_HTTP_ONLY=true
SESSION_SECURE_COOKIE=false  # Set to true in production with HTTPS
MAX_CONCURRENT_SESSIONS=3
```

## Security Commands

### Security Audit

Run comprehensive security audit:
```bash
php artisan security:audit
```

Options:
- `--fix` - Attempt to fix issues automatically (future feature)

## Environment Configuration

### Development Environment

```bash
# Security settings for development
APP_DEBUG=true
APP_ENV=local
CSP_ENABLED=false
IP_FILTERING_ENABLED=false
REQUIRE_HTTPS=false
SESSION_SECURE_COOKIE=false
```

### Production Environment

```bash
# Security settings for production
APP_DEBUG=false
APP_ENV=production
CSP_ENABLED=true
CSP_REPORT_ONLY=false
IP_FILTERING_ENABLED=true
REQUIRE_HTTPS=true
SESSION_SECURE_COOKIE=true
VIRUS_SCANNING_ENABLED=true
2FA_REQUIRED_ADMIN=true
```

## Security Testing

### Running Security Tests

```bash
# Run all security tests
php artisan test tests/Feature/SecurityTest.php

# Run specific security test
php artisan test --filter="test_sql_injection_patterns"
```

### Security Test Coverage

- Input sanitization
- SQL injection prevention
- XSS protection
- File upload validation
- IP filtering
- Rate limiting
- Security headers
- CORS configuration

## Security Monitoring

### Log Files

- `storage/logs/security.log` - Security events and violations
- `storage/logs/audit.log` - User actions and system changes
- `storage/logs/auth.log` - Authentication events

### Monitoring Recommendations

1. Set up log monitoring and alerting
2. Regular security audit runs
3. Monitor failed authentication attempts
4. Track unusual IP access patterns
5. Monitor file upload attempts

## Security Maintenance

### Regular Tasks

1. **Weekly**: Review security logs
2. **Monthly**: Run security audit
3. **Quarterly**: Update security configurations
4. **Annually**: Security penetration testing

### Security Updates

1. Keep Laravel framework updated
2. Update security-related packages
3. Review and update security policies
4. Monitor security advisories

## Incident Response

### Security Event Detection

The system automatically logs and can alert on:
- Multiple failed login attempts
- SQL injection attempts
- XSS attempts
- Unusual file upload patterns
- IP-based attacks

### Response Procedures

1. **Immediate**: Block malicious IPs
2. **Short-term**: Investigate logs and patterns
3. **Long-term**: Update security measures

## Compliance

### Standards Supported

- OWASP Top 10 protection
- GDPR compliance preparation
- SOC 2 Type II preparation
- PCI DSS considerations (for payment processing)

### Data Protection

- Sensitive data hashing
- Secure token generation
- Audit trail maintenance
- Data retention policies

## Security Contact

For security issues or questions:
- Create a security incident ticket
- Follow responsible disclosure practices
- Document all security-related changes

## Additional Resources

- [OWASP Security Guidelines](https://owasp.org/)
- [Laravel Security Documentation](https://laravel.com/docs/security)
- [PHP Security Best Practices](https://www.php.net/manual/en/security.php)