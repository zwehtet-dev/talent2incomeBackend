# Talent2Income API Documentation

## Overview

The Talent2Income API is a comprehensive REST API that powers a micro jobs and skill exchange platform. It connects service providers with clients seeking specific tasks or expertise through secure transactions, real-time communication, and a quality assurance system.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Authentication](#authentication)
3. [API Versioning](#api-versioning)
4. [Rate Limiting](#rate-limiting)
5. [Error Handling](#error-handling)
6. [Endpoints Overview](#endpoints-overview)
7. [Code Examples](#code-examples)
8. [SDKs and Tools](#sdks-and-tools)
9. [Support](#support)

## Getting Started

### Base URL

```
Production: https://api.talent2income.com
Development: http://localhost:8000
```

### Interactive Documentation

- **Swagger UI**: [/api/documentation](http://localhost:8000/api/documentation)
- **Postman Collection**: Available in `/postman/` directory

### Quick Start

1. **Register a new user account**
```bash
curl -X POST http://localhost:8000/api/auth/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
  }'
```

2. **Login to get access token**
```bash
curl -X POST http://localhost:8000/api/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john@example.com",
    "password": "password123"
  }'
```

3. **Use the token for authenticated requests**
```bash
curl -X GET http://localhost:8000/api/users/profile \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

## Authentication

The API uses Laravel Sanctum for stateless token-based authentication.

### Authentication Flow

1. **Register** or **Login** to receive an access token
2. Include the token in the `Authorization` header for all authenticated requests
3. **Logout** to revoke the current token

### Headers

```http
Authorization: Bearer {your_access_token}
Accept: application/json
Content-Type: application/json
```

### Token Management

- Tokens don't expire by default but can be revoked
- Use `/api/auth/logout` to revoke current token
- Use `/api/auth/logout-all` to revoke all user tokens
- Monitor active sessions via `/api/auth/sessions`

## API Versioning

The API supports versioning to ensure backward compatibility.

### Current Version: v1

### Version Headers

You can specify the API version using any of these methods:

1. **Accept Header (Recommended)**
```http
Accept: application/vnd.talent2income.v1+json
```

2. **Custom Header**
```http
X-API-Version: v1
```

3. **Query Parameter**
```http
GET /api/jobs?version=v1
```

### Version Information

```bash
# Get supported versions
curl -X GET http://localhost:8000/api/versions
```

### Deprecation Policy

- New versions are released with backward compatibility
- Deprecated versions receive 12 months notice
- Migration guides are provided for major version changes

## Rate Limiting

The API implements rate limiting to ensure fair usage and system stability.

### Rate Limits

| Endpoint Category | Limit | Window |
|------------------|-------|---------|
| Authentication | 5 requests | 1 minute |
| General API | 60 requests | 1 minute |
| Search | 30 requests | 1 minute |

### Rate Limit Headers

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1640995200
```

### Rate Limit Exceeded

```json
{
  "message": "Too many requests. Please try again later.",
  "retry_after": 60
}
```

### Best Practices

- Implement exponential backoff for retries
- Cache responses when possible
- Use webhooks instead of polling
- Monitor rate limit headers

## Error Handling

The API uses standard HTTP status codes and returns consistent error responses.

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Internal Server Error |

### Error Response Format

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  },
  "status_code": 422,
  "error_id": "uuid-here"
}
```

### Validation Errors

```json
{
  "message": "Validation failed",
  "errors": {
    "field_name": [
      "Error message 1",
      "Error message 2"
    ]
  }
}
```

## Endpoints Overview

### Authentication
- `POST /api/auth/register` - Register new user
- `POST /api/auth/login` - Login user
- `POST /api/auth/logout` - Logout current session
- `GET /api/auth/me` - Get current user info

### Users
- `GET /api/users/profile` - Get user profile
- `PUT /api/users/profile` - Update user profile
- `POST /api/users/avatar` - Upload avatar
- `GET /api/users/{id}` - Get user details

### Jobs
- `GET /api/jobs` - List jobs with filtering
- `POST /api/jobs` - Create new job
- `GET /api/jobs/{id}` - Get job details
- `PUT /api/jobs/{id}` - Update job
- `DELETE /api/jobs/{id}` - Delete job

### Skills
- `GET /api/skills` - List skills with filtering
- `POST /api/skills` - Create new skill
- `GET /api/skills/{id}` - Get skill details
- `PUT /api/skills/{id}` - Update skill
- `DELETE /api/skills/{id}` - Delete skill

### Messages
- `GET /api/messages/conversations` - Get conversations
- `GET /api/messages/conversation/{user}` - Get conversation with user
- `POST /api/messages` - Send message
- `POST /api/messages/mark-read/{user}` - Mark messages as read

### Payments
- `POST /api/payments/create` - Create payment
- `GET /api/payments/history` - Get payment history
- `POST /api/payments/{id}/release` - Release payment
- `POST /api/payments/{id}/refund` - Refund payment

### Reviews
- `POST /api/reviews` - Create review
- `GET /api/reviews` - List reviews
- `GET /api/reviews/{id}` - Get review details
- `POST /api/reviews/{id}/respond` - Respond to review

### Search
- `GET /api/search/jobs` - Search jobs
- `GET /api/search/skills` - Search skills
- `GET /api/search/all` - Search all content
- `GET /api/search/suggestions` - Get search suggestions

## Code Examples

### JavaScript/Node.js

```javascript
const axios = require('axios');

const api = axios.create({
  baseURL: 'http://localhost:8000/api',
  headers: {
    'Accept': 'application/json',
    'Content-Type': 'application/json'
  }
});

// Login and set token
async function login(email, password) {
  const response = await api.post('/auth/login', { email, password });
  const token = response.data.token;
  
  // Set token for future requests
  api.defaults.headers.common['Authorization'] = `Bearer ${token}`;
  
  return response.data;
}

// Get jobs
async function getJobs(filters = {}) {
  const response = await api.get('/jobs', { params: filters });
  return response.data;
}

// Create job
async function createJob(jobData) {
  const response = await api.post('/jobs', jobData);
  return response.data;
}
```

### PHP

```php
<?php

class Talent2IncomeAPI {
    private $baseUrl;
    private $token;
    
    public function __construct($baseUrl = 'http://localhost:8000/api') {
        $this->baseUrl = $baseUrl;
    }
    
    public function login($email, $password) {
        $response = $this->makeRequest('POST', '/auth/login', [
            'email' => $email,
            'password' => $password
        ]);
        
        $this->token = $response['token'];
        return $response;
    }
    
    public function getJobs($filters = []) {
        return $this->makeRequest('GET', '/jobs', $filters);
    }
    
    public function createJob($jobData) {
        return $this->makeRequest('POST', '/jobs', $jobData);
    }
    
    private function makeRequest($method, $endpoint, $data = []) {
        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Accept: application/json',
            'Content-Type: application/json'
        ];
        
        if ($this->token) {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif ($method === 'GET' && !empty($data)) {
            $url .= '?' . http_build_query($data);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        $response = curl_exec($ch);
        curl_close($ch);
        
        return json_decode($response, true);
    }
}
```

### Python

```python
import requests

class Talent2IncomeAPI:
    def __init__(self, base_url='http://localhost:8000/api'):
        self.base_url = base_url
        self.session = requests.Session()
        self.session.headers.update({
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        })
    
    def login(self, email, password):
        response = self.session.post(f'{self.base_url}/auth/login', json={
            'email': email,
            'password': password
        })
        response.raise_for_status()
        
        data = response.json()
        token = data['token']
        
        # Set token for future requests
        self.session.headers.update({
            'Authorization': f'Bearer {token}'
        })
        
        return data
    
    def get_jobs(self, filters=None):
        response = self.session.get(f'{self.base_url}/jobs', params=filters)
        response.raise_for_status()
        return response.json()
    
    def create_job(self, job_data):
        response = self.session.post(f'{self.base_url}/jobs', json=job_data)
        response.raise_for_status()
        return response.json()
```

## SDKs and Tools

### Postman Collection

Import the Postman collection and environment files:

1. **Collection**: `/postman/Talent2Income_API.postman_collection.json`
2. **Environment**: `/postman/Talent2Income_Development.postman_environment.json`

### OpenAPI/Swagger

- **Interactive Documentation**: `/api/documentation`
- **OpenAPI Spec**: `/docs/api-docs.json`

### Testing

The API includes comprehensive test coverage:

```bash
# Run all tests
composer test

# Run specific test suites
composer test-feature
composer test-unit

# Run with coverage
composer test-coverage
```

## Pagination

List endpoints support pagination:

```json
{
  "data": [...],
  "meta": {
    "current_page": 1,
    "total": 100,
    "per_page": 15,
    "last_page": 7,
    "from": 1,
    "to": 15
  }
}
```

### Pagination Parameters

- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 50)

## Filtering and Searching

### Common Filter Parameters

- `search`: Full-text search
- `category_id`: Filter by category
- `status`: Filter by status
- `created_at`: Date range filtering
- `sort`: Sort field
- `order`: Sort direction (asc/desc)

### Example

```bash
curl "http://localhost:8000/api/jobs?search=wordpress&category_id=1&status=open&sort=created_at&order=desc"
```

## Webhooks

The API supports webhooks for real-time notifications:

### Available Events

- `job.created`
- `job.updated`
- `job.completed`
- `payment.created`
- `payment.released`
- `review.created`
- `message.sent`

### Webhook Configuration

Configure webhooks in your application settings or contact support.

## Best Practices

### Security

1. **Always use HTTPS** in production
2. **Store tokens securely** (not in localStorage)
3. **Implement token refresh** logic
4. **Validate all input** on client side
5. **Use environment variables** for sensitive data

### Performance

1. **Implement caching** for frequently accessed data
2. **Use pagination** for large datasets
3. **Minimize API calls** by batching requests
4. **Implement retry logic** with exponential backoff
5. **Monitor rate limits** and adjust accordingly

### Error Handling

1. **Always check status codes**
2. **Handle network errors gracefully**
3. **Implement proper logging**
4. **Show user-friendly error messages**
5. **Implement fallback mechanisms**

## Support

### Documentation

- **API Reference**: `/api/documentation`
- **Developer Guide**: This document
- **Changelog**: `/docs/CHANGELOG.md`

### Contact

- **Email**: api-support@talent2income.com
- **Documentation Issues**: Create an issue in the repository
- **Feature Requests**: Contact the development team

### Status Page

Monitor API status and uptime: [status.talent2income.com](https://status.talent2income.com)

## Changelog

### v1.0.0 (2024-01-15)

- Initial API release
- Complete authentication system
- Job and skill management
- Real-time messaging
- Payment processing
- Review system
- Advanced search capabilities
- Admin dashboard
- Comprehensive documentation

---

**Last Updated**: January 15, 2024  
**API Version**: v1.0.0