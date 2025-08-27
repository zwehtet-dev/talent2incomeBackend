# Developer Onboarding Guide

Welcome to the Talent2Income API! This guide will help you get started quickly and efficiently.

## 🚀 Quick Start (5 minutes)

### 1. Get Your Development Environment Ready

```bash
# Clone the repository (if working locally)
git clone https://github.com/talent2income/api.git
cd talent2income-api

# Install dependencies
composer install

# Set up environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate --seed

# Start the development server
php artisan serve
```

### 2. Test the API

```bash
# Check if API is running
curl http://localhost:8000/api/

# Expected response:
{
  "name": "Talent2Income API",
  "version": "1.0.0",
  "current_version": "v1",
  "supported_versions": ["v1"],
  "documentation": "http://localhost:8000/api/documentation",
  "status": "operational"
}
```

### 3. Create Your First User

```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "first_name": "Developer",
    "last_name": "Test",
    "email": "dev@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

### 4. Get Your Access Token

```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "dev@example.com",
    "password": "password123"
  }'
```

Save the `token` from the response - you'll need it for authenticated requests!

## 📚 Essential Resources

### Interactive Documentation
- **Swagger UI**: [http://localhost:8000/api/documentation](http://localhost:8000/api/documentation)
- Browse all endpoints, test requests, and see response schemas

### Postman Collection
1. Import `/postman/Talent2Income_API.postman_collection.json`
2. Import `/postman/Talent2Income_Development.postman_environment.json`
3. Set your `auth_token` in the environment after login

### Code Examples
Check `/docs/API_DOCUMENTATION.md` for examples in:
- JavaScript/Node.js
- PHP
- Python
- cURL

## 🔑 Authentication Deep Dive

### Token-Based Authentication
The API uses Laravel Sanctum for stateless authentication:

```javascript
// After login, include token in all requests
const headers = {
  'Authorization': 'Bearer YOUR_TOKEN_HERE',
  'Accept': 'application/json',
  'Content-Type': 'application/json'
};
```

### Token Management
```bash
# Get current user info
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/auth/me

# View active sessions
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/auth/sessions

# Logout (revoke current token)
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/auth/logout

# Logout all devices
curl -X POST -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/auth/logout-all
```

## 🏗️ Core Concepts

### 1. Jobs and Skills
- **Jobs**: Tasks posted by clients seeking services
- **Skills**: Services offered by providers
- Both support categories, pricing, and availability

### 2. Messaging System
- Real-time messaging between users
- Job-context conversations
- User blocking capabilities

### 3. Payment Processing
- Escrow-based payments
- Multiple payment methods
- Dispute resolution

### 4. Review System
- Bidirectional reviews after job completion
- Rating aggregation and statistics
- Moderation capabilities

## 📊 Working with Data

### Pagination
All list endpoints return paginated results:

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "total": 100,
    "per_page": 15,
    "last_page": 7
  }
}
```

### Filtering and Search
Most endpoints support filtering:

```bash
# Search jobs
curl "http://localhost:8000/api/jobs?search=wordpress&category_id=1&budget_min=500"

# Filter skills by availability
curl "http://localhost:8000/api/skills?is_available=true&pricing_type=hourly"
```

### Sorting
```bash
# Sort by creation date (newest first)
curl "http://localhost:8000/api/jobs?sort=created_at&order=desc"

# Sort by rating (highest first)
curl "http://localhost:8000/api/skills?sort=average_rating&order=desc"
```

## 🚦 Rate Limiting

### Understanding Limits
- **Authentication**: 5 requests/minute
- **General API**: 60 requests/minute
- **Search**: 30 requests/minute

### Monitoring Usage
```bash
# Check rate limit headers in response
curl -I -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/jobs

# Look for these headers:
# X-RateLimit-Limit: 60
# X-RateLimit-Remaining: 45
# X-RateLimit-Reset: 1640995200
```

### Best Practices
```javascript
// Implement retry with exponential backoff
async function apiRequest(url, options, retries = 3) {
  try {
    const response = await fetch(url, options);
    
    if (response.status === 429) {
      const retryAfter = response.headers.get('Retry-After');
      if (retries > 0) {
        await new Promise(resolve => setTimeout(resolve, retryAfter * 1000));
        return apiRequest(url, options, retries - 1);
      }
    }
    
    return response;
  } catch (error) {
    if (retries > 0) {
      await new Promise(resolve => setTimeout(resolve, 1000 * (4 - retries)));
      return apiRequest(url, options, retries - 1);
    }
    throw error;
  }
}
```

## 🔄 API Versioning

### Current Version: v1
Specify version using any method:

```bash
# Method 1: Accept header (recommended)
curl -H "Accept: application/vnd.talent2income.v1+json" http://localhost:8000/api/jobs

# Method 2: Custom header
curl -H "X-API-Version: v1" http://localhost:8000/api/jobs

# Method 3: Query parameter
curl "http://localhost:8000/api/jobs?version=v1"
```

### Version Information
```bash
curl http://localhost:8000/api/versions
```

## 🛠️ Development Workflow

### 1. Typical Integration Flow

```javascript
// 1. Initialize API client
const api = new Talent2IncomeAPI('http://localhost:8000/api');

// 2. Authenticate
const loginResponse = await api.login('user@example.com', 'password');
console.log('Logged in:', loginResponse.user);

// 3. Fetch data
const jobs = await api.getJobs({ search: 'web development' });
console.log('Found jobs:', jobs.data.length);

// 4. Create resources
const newJob = await api.createJob({
  title: 'Build a React App',
  description: 'Need a modern React application...',
  category_id: 1,
  budget_min: 1000,
  budget_max: 2000,
  budget_type: 'fixed'
});

// 5. Handle real-time updates (if using WebSockets)
api.onMessage((message) => {
  console.log('New message:', message);
});
```

### 2. Error Handling Patterns

```javascript
try {
  const response = await api.createJob(jobData);
  return response;
} catch (error) {
  if (error.status === 422) {
    // Validation errors
    console.log('Validation errors:', error.data.errors);
    // Show field-specific errors to user
  } else if (error.status === 401) {
    // Authentication required
    redirectToLogin();
  } else if (error.status === 429) {
    // Rate limit exceeded
    showRateLimitMessage(error.data.retry_after);
  } else {
    // Generic error
    showErrorMessage('Something went wrong. Please try again.');
  }
}
```

### 3. Testing Your Integration

```bash
# Run the test suite
composer test

# Test specific endpoints
composer test --filter AuthControllerTest

# Test with coverage
composer test-coverage
```

## 🔍 Debugging Tips

### 1. Enable Debug Mode
```bash
# In .env file
APP_DEBUG=true
LOG_LEVEL=debug
```

### 2. Check Logs
```bash
# API logs
tail -f storage/logs/laravel.log

# Authentication logs
tail -f storage/logs/auth-*.log

# Payment logs
tail -f storage/logs/payments-*.log
```

### 3. Common Issues

**Issue**: "Unauthenticated" error
```bash
# Solution: Check token format
curl -H "Authorization: Bearer YOUR_TOKEN" http://localhost:8000/api/auth/me
```

**Issue**: Validation errors
```bash
# Solution: Check required fields and formats
curl -X POST http://localhost:8000/api/jobs \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"title": "Test Job"}' # Missing required fields
```

**Issue**: Rate limit exceeded
```bash
# Solution: Implement proper retry logic and respect rate limits
```

## 📈 Performance Optimization

### 1. Caching Strategies
```javascript
// Cache frequently accessed data
const cache = new Map();

async function getCachedJobs(filters) {
  const key = JSON.stringify(filters);
  
  if (cache.has(key)) {
    return cache.get(key);
  }
  
  const jobs = await api.getJobs(filters);
  cache.set(key, jobs);
  
  // Expire cache after 5 minutes
  setTimeout(() => cache.delete(key), 5 * 60 * 1000);
  
  return jobs;
}
```

### 2. Batch Operations
```javascript
// Instead of multiple single requests
const jobs = await Promise.all([
  api.getJob(1),
  api.getJob(2),
  api.getJob(3)
]);

// Use filtering to get multiple items
const jobs = await api.getJobs({ ids: [1, 2, 3] });
```

### 3. Pagination Optimization
```javascript
// Load data progressively
async function loadAllJobs() {
  let page = 1;
  let allJobs = [];
  
  while (true) {
    const response = await api.getJobs({ page, per_page: 50 });
    allJobs.push(...response.data);
    
    if (page >= response.meta.last_page) break;
    page++;
  }
  
  return allJobs;
}
```

## 🔐 Security Best Practices

### 1. Token Security
```javascript
// ✅ Good: Store in secure HTTP-only cookie
// ✅ Good: Store in secure session storage
// ❌ Bad: Store in localStorage (XSS vulnerable)

// Implement token refresh
if (isTokenExpired(token)) {
  token = await refreshToken();
}
```

### 2. Input Validation
```javascript
// Always validate on client side (but server validates too)
function validateJobData(data) {
  const errors = {};
  
  if (!data.title || data.title.length < 3) {
    errors.title = 'Title must be at least 3 characters';
  }
  
  if (!data.budget_min || data.budget_min < 0) {
    errors.budget_min = 'Budget must be positive';
  }
  
  return Object.keys(errors).length ? errors : null;
}
```

### 3. HTTPS Only
```javascript
// Always use HTTPS in production
const apiUrl = process.env.NODE_ENV === 'production' 
  ? 'https://api.talent2income.com'
  : 'http://localhost:8000';
```

## 🎯 Next Steps

### 1. Explore Advanced Features
- Real-time messaging with WebSockets
- File uploads for avatars and attachments
- Advanced search with Elasticsearch
- Payment processing with Stripe/PayPal

### 2. Integration Examples
- Build a job board frontend
- Create a mobile app with React Native
- Integrate with existing systems

### 3. Contribute
- Report bugs and issues
- Suggest new features
- Contribute to documentation

## 📞 Getting Help

### Quick Help
- **Swagger UI**: Test endpoints interactively
- **Postman Collection**: Pre-built requests
- **Code Examples**: Copy-paste ready code

### Community
- **GitHub Issues**: Report bugs and request features
- **Developer Forum**: Ask questions and share solutions
- **API Status**: Monitor uptime and incidents

### Direct Support
- **Email**: api-support@talent2income.com
- **Response Time**: 24 hours for technical issues
- **Priority Support**: Available for enterprise customers

---

**Happy coding! 🚀**

*Last updated: January 15, 2024*