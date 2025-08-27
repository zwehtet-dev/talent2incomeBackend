# Talent2Income API Documentation

## Table of Contents
- [Overview](#overview)
- [Authentication](#authentication)
- [Rate Limiting](#rate-limiting)
- [API Versioning](#api-versioning)
- [Error Handling](#error-handling)
- [Endpoints](#endpoints)
  - [Health & Info](#health--info)
  - [Authentication](#authentication-endpoints)
  - [Users](#users)
  - [Categories](#categories)
  - [Jobs](#jobs)
  - [Skills](#skills)
  - [Messages](#messages)
  - [Payments](#payments)
  - [Reviews](#reviews)
  - [Search](#search)
  - [Saved Searches](#saved-searches)
  - [Ratings](#ratings)
  - [GDPR](#gdpr)
  - [Admin](#admin)

## Overview

The Talent2Income API is a RESTful API for a freelance marketplace platform. It provides endpoints for user management, job posting, skill sharing, messaging, payments, and more.

**Base URL:** `https://api.talent2income.com`
**Current Version:** v1
**Response Format:** JSON

## Authentication

The API uses Laravel Sanctum for authentication with Bearer tokens.

### Authentication Flow
1. Register or login to get an access token
2. Include the token in the `Authorization` header for protected endpoints
3. Use the format: `Authorization: Bearer {your_access_token}`

### Token Management
- Tokens don't expire by default but can be revoked
- Use `/api/auth/logout` to revoke current token
- Use `/api/auth/logout-all` to revoke all user tokens

## Rate Limiting

Different endpoint groups have different rate limits:

- **Authentication endpoints**: 5 requests per minute
- **Email verification**: 10 requests per minute  
- **Protected endpoints**: 60 requests per minute
- **Admin endpoints**: No specific limit (inherits protected limit)

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Remaining requests in current window
- `X-RateLimit-Reset`: Unix timestamp when limit resets

## API Versioning

The API supports versioning through middleware. Current version is `v1`.

**Version Header:** `Accept: application/vnd.api+json;version=v1`

## Error Handling

The API returns consistent error responses:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Specific error message"]
  },
  "error_code": "ERROR_CODE"
}
```

### HTTP Status Codes
- `200`: Success
- `201`: Created
- `400`: Bad Request
- `401`: Unauthorized
- `403`: Forbidden
- `404`: Not Found
- `422`: Validation Error
- `429`: Too Many Requests
- `500`: Internal Server Error

## Endpoints

### Health & Info

#### Get API Information
```http
GET /api
```

**Response:**
```json
{
  "name": "Talent2Income API",
  "version": "1.0.0",
  "current_version": "v1",
  "supported_versions": ["v1"],
  "documentation": "https://api.talent2income.com/api/documentation",
  "status": "operational"
}
```

#### Simple Health Check
```http
GET /api/health
```

**Response:**
```json
{
  "status": "ok",
  "timestamp": "2024-01-15T10:30:00Z"
}
```

#### Detailed Health Check
```http
GET /api/health/detailed
```

**Response:**
```json
{
  "status": "ok",
  "services": {
    "database": "ok",
    "cache": "ok",
    "storage": "ok"
  },
  "timestamp": "2024-01-15T10:30:00Z"
}
```

### Authentication Endpoints

#### Register User
```http
POST /api/auth/register
```

**Request Body:**
```json
{
  "first_name": "John",
  "last_name": "Doe", 
  "email": "john.doe@example.com",
  "password": "SecurePassword123!",
  "password_confirmation": "SecurePassword123!"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "User registered successfully",
  "user": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "email_verified_at": null,
    "created_at": "2024-01-15T10:30:00Z"
  },
  "token": "1|abc123def456..."
}
```

#### Login User
```http
POST /api/auth/login
```

**Request Body:**
```json
{
  "email": "john.doe@example.com",
  "password": "SecurePassword123!"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Login successful",
  "user": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "email_verified_at": "2024-01-15T10:30:00Z"
  },
  "token": "2|xyz789uvw456..."
}
```

#### Get Current User
```http
GET /api/auth/me
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "email_verified_at": "2024-01-15T10:30:00Z",
    "profile": {
      "bio": "Experienced web developer",
      "location": "New York, USA",
      "phone": "+1234567890",
      "avatar": "https://storage.talent2income.com/avatars/1.jpg"
    }
  }
}
```

#### Logout User
```http
POST /api/auth/logout
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Logged out successfully"
}
```

#### Forgot Password
```http
POST /api/auth/forgot-password
```

**Request Body:**
```json
{
  "email": "john.doe@example.com"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Password reset link sent to your email"
}
```

### Users

#### Get User Profile
```http
GET /api/users/profile
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "user": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "bio": "Experienced web developer",
    "location": "New York, USA",
    "phone": "+1234567890",
    "avatar": "https://storage.talent2income.com/avatars/1.jpg",
    "skills_count": 5,
    "jobs_completed": 12,
    "average_rating": 4.8,
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

#### Update User Profile
```http
PUT /api/users/profile
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "first_name": "John",
  "last_name": "Smith",
  "bio": "Senior full-stack developer with 5+ years experience",
  "location": "San Francisco, USA",
  "phone": "+1987654321"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Profile updated successfully",
  "user": {
    "id": 1,
    "first_name": "John",
    "last_name": "Smith",
    "bio": "Senior full-stack developer with 5+ years experience",
    "location": "San Francisco, USA",
    "phone": "+1987654321"
  }
}
```

#### Upload Avatar
```http
POST /api/users/avatar
Authorization: Bearer {token}
Content-Type: multipart/form-data
```

**Request Body:**
- `avatar`: Image file (JPEG, PNG, max 2MB)

**Response (200):**
```json
{
  "success": true,
  "message": "Avatar uploaded successfully",
  "avatar_url": "https://storage.talent2income.com/avatars/1.jpg"
}
```

#### Search Users
```http
GET /api/users/search?q=john&skills=php,laravel&location=new+york
Authorization: Bearer {token}
```

**Query Parameters:**
- `q`: Search query (name, email)
- `skills`: Comma-separated skill names
- `location`: Location filter
- `page`: Page number (default: 1)
- `per_page`: Results per page (default: 20)

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "location": "New York, USA",
      "avatar": "https://storage.talent2income.com/avatars/1.jpg",
      "average_rating": 4.8,
      "skills": ["PHP", "Laravel", "JavaScript"]
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 1,
    "last_page": 1
  }
}
```

### Categories

#### Get All Categories
```http
GET /api/categories
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Web Development",
      "description": "Frontend and backend web development",
      "slug": "web-development",
      "skills_count": 25,
      "jobs_count": 150
    },
    {
      "id": 2,
      "name": "Mobile Development",
      "description": "iOS and Android app development",
      "slug": "mobile-development",
      "skills_count": 18,
      "jobs_count": 89
    }
  ]
}
```

### Jobs

#### Get All Jobs
```http
GET /api/jobs?page=1&per_page=20&status=active
Authorization: Bearer {token}
```

**Query Parameters:**
- `page`: Page number (default: 1)
- `per_page`: Results per page (default: 20)
- `status`: Job status (active, completed, cancelled)
- `category_id`: Filter by category
- `job_type`: fixed, hourly

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Laravel Developer Needed",
      "description": "Looking for experienced Laravel developer...",
      "category": {
        "id": 1,
        "name": "Web Development"
      },
      "budget_min": 500,
      "budget_max": 2000,
      "job_type": "fixed",
      "experience_level": "intermediate",
      "deadline": "2024-12-31",
      "status": "active",
      "client": {
        "id": 2,
        "first_name": "Jane",
        "last_name": "Smith",
        "avatar": "https://storage.talent2income.com/avatars/2.jpg"
      },
      "required_skills": ["PHP", "Laravel", "MySQL"],
      "proposals_count": 5,
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 150,
    "last_page": 8
  }
}
```

#### Create Job
```http
POST /api/jobs
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "title": "React.js Developer for E-commerce Site",
  "description": "We need a skilled React.js developer to build the frontend of our e-commerce platform...",
  "category_id": 1,
  "budget_min": 1000,
  "budget_max": 3000,
  "deadline": "2024-03-15",
  "required_skills": ["React", "JavaScript", "HTML", "CSS"],
  "job_type": "fixed",
  "experience_level": "intermediate"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Job created successfully",
  "data": {
    "id": 15,
    "title": "React.js Developer for E-commerce Site",
    "description": "We need a skilled React.js developer...",
    "category_id": 1,
    "budget_min": 1000,
    "budget_max": 3000,
    "job_type": "fixed",
    "status": "active",
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

#### Get Job Details
```http
GET /api/jobs/1
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "id": 1,
    "title": "Laravel Developer Needed",
    "description": "Detailed job description...",
    "category": {
      "id": 1,
      "name": "Web Development"
    },
    "budget_min": 500,
    "budget_max": 2000,
    "job_type": "fixed",
    "experience_level": "intermediate",
    "deadline": "2024-12-31",
    "status": "active",
    "client": {
      "id": 2,
      "first_name": "Jane",
      "last_name": "Smith",
      "avatar": "https://storage.talent2income.com/avatars/2.jpg",
      "average_rating": 4.9
    },
    "required_skills": ["PHP", "Laravel", "MySQL"],
    "proposals": [
      {
        "id": 1,
        "freelancer": {
          "id": 3,
          "first_name": "Mike",
          "last_name": "Johnson"
        },
        "bid_amount": 1500,
        "proposal_text": "I have 3+ years experience with Laravel...",
        "created_at": "2024-01-15T11:00:00Z"
      }
    ],
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

#### Search Jobs
```http
GET /api/jobs/search?q=laravel&budget_min=500&budget_max=2000&category_id=1
Authorization: Bearer {token}
```

**Query Parameters:**
- `q`: Search query (title, description)
- `budget_min`: Minimum budget
- `budget_max`: Maximum budget
- `category_id`: Category filter
- `job_type`: fixed, hourly
- `experience_level`: beginner, intermediate, expert
- `page`: Page number
- `per_page`: Results per page

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Laravel Developer Needed",
      "description": "Looking for experienced Laravel developer...",
      "budget_min": 500,
      "budget_max": 2000,
      "job_type": "fixed",
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 5,
    "last_page": 1
  }
}
```

#### Get My Jobs
```http
GET /api/jobs/my-jobs
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "title": "Laravel Developer Needed",
      "status": "active",
      "proposals_count": 5,
      "budget_min": 500,
      "budget_max": 2000,
      "created_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

### Skills

#### Get All Skills
```http
GET /api/skills?page=1&per_page=20
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Laravel Development",
      "category": {
        "id": 1,
        "name": "Web Development"
      },
      "user": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe"
      },
      "description": "Expert Laravel framework development",
      "hourly_rate": 50,
      "experience_level": "expert",
      "is_available": true,
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 100,
    "last_page": 5
  }
}
```

#### Create Skill
```http
POST /api/skills
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "name": "React.js Development",
  "category_id": 1,
  "description": "Frontend development with React.js",
  "hourly_rate": 45,
  "experience_level": "intermediate",
  "is_available": true
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Skill created successfully",
  "data": {
    "id": 25,
    "name": "React.js Development",
    "category_id": 1,
    "description": "Frontend development with React.js",
    "hourly_rate": 45,
    "experience_level": "intermediate",
    "is_available": true,
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

#### Get My Skills
```http
GET /api/skills/my-skills
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Laravel Development",
      "hourly_rate": 50,
      "experience_level": "expert",
      "is_available": true
    },
    {
      "id": 2,
      "name": "JavaScript Development",
      "hourly_rate": 40,
      "experience_level": "intermediate",
      "is_available": false
    }
  ]
}
```

#### Toggle Skill Availability
```http
PATCH /api/skills/1/toggle-availability
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Skill availability updated",
  "data": {
    "id": 1,
    "is_available": false
  }
}
```

### Messages

#### Get Conversations
```http
GET /api/messages/conversations
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "user": {
        "id": 2,
        "first_name": "Jane",
        "last_name": "Smith",
        "avatar": "https://storage.talent2income.com/avatars/2.jpg"
      },
      "last_message": {
        "content": "Thanks for your proposal!",
        "created_at": "2024-01-15T12:30:00Z",
        "sender_id": 2
      },
      "unread_count": 2
    }
  ]
}
```

#### Get Conversation with User
```http
GET /api/messages/conversation/2?page=1&per_page=50
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "content": "Hi! I'm interested in your Laravel project.",
      "sender": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe"
      },
      "recipient": {
        "id": 2,
        "first_name": "Jane",
        "last_name": "Smith"
      },
      "type": "text",
      "is_read": true,
      "created_at": "2024-01-15T11:00:00Z"
    },
    {
      "id": 2,
      "content": "Thanks for your proposal!",
      "sender": {
        "id": 2,
        "first_name": "Jane",
        "last_name": "Smith"
      },
      "recipient": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe"
      },
      "type": "text",
      "is_read": false,
      "created_at": "2024-01-15T12:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 2,
    "last_page": 1
  }
}
```

#### Send Message
```http
POST /api/messages
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "recipient_id": 2,
  "content": "Hello! I'm interested in your Laravel project.",
  "type": "text"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Message sent successfully",
  "data": {
    "id": 15,
    "content": "Hello! I'm interested in your Laravel project.",
    "sender_id": 1,
    "recipient_id": 2,
    "type": "text",
    "is_read": false,
    "created_at": "2024-01-15T13:00:00Z"
  }
}
```

#### Get Unread Message Count
```http
GET /api/messages/unread-count
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "unread_count": 5
}
```

### Payments

#### Create Payment
```http
POST /api/payments/create
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "job_id": 1,
  "freelancer_id": 2,
  "amount": 500.00,
  "description": "Payment for Laravel development work",
  "milestone_title": "Initial Development Phase"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Payment created successfully",
  "data": {
    "id": 1,
    "job_id": 1,
    "client_id": 1,
    "freelancer_id": 2,
    "amount": 500.00,
    "status": "pending",
    "description": "Payment for Laravel development work",
    "milestone_title": "Initial Development Phase",
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

#### Get Payment History
```http
GET /api/payments/history?page=1&per_page=20
Authorization: Bearer {token}
```

**Query Parameters:**
- `page`: Page number
- `per_page`: Results per page
- `status`: pending, released, refunded
- `type`: sent, received

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "job": {
        "id": 1,
        "title": "Laravel Developer Needed"
      },
      "amount": 500.00,
      "status": "released",
      "description": "Payment for Laravel development work",
      "created_at": "2024-01-15T10:30:00Z",
      "released_at": "2024-01-20T14:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 10,
    "last_page": 1
  }
}
```

#### Release Payment
```http
POST /api/payments/1/release
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "release_note": "Work completed successfully"
}
```

**Response (200):**
```json
{
  "success": true,
  "message": "Payment released successfully",
  "data": {
    "id": 1,
    "status": "released",
    "released_at": "2024-01-15T15:00:00Z"
  }
}
```

### Reviews

#### Get All Reviews (Public)
```http
GET /api/reviews?page=1&per_page=20
```

**Query Parameters:**
- `user_id`: Filter by user ID
- `rating`: Filter by rating (1-5)
- `page`: Page number
- `per_page`: Results per page

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "job": {
        "id": 1,
        "title": "Laravel Developer Needed"
      },
      "reviewer": {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe"
      },
      "reviewed_user": {
        "id": 2,
        "first_name": "Jane",
        "last_name": "Smith"
      },
      "rating": 5,
      "comment": "Excellent work quality and great communication!",
      "review_type": "client_to_freelancer",
      "created_at": "2024-01-15T10:30:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 20,
    "total": 50,
    "last_page": 3
  }
}
```

#### Create Review
```http
POST /api/reviews
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "job_id": 1,
  "reviewed_user_id": 2,
  "rating": 5,
  "comment": "Excellent work quality and great communication!",
  "review_type": "client_to_freelancer"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Review created successfully",
  "data": {
    "id": 15,
    "job_id": 1,
    "reviewer_id": 1,
    "reviewed_user_id": 2,
    "rating": 5,
    "comment": "Excellent work quality and great communication!",
    "review_type": "client_to_freelancer",
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

### Search

#### Search All
```http
GET /api/search/all?q=laravel&type=jobs,skills,users
Authorization: Bearer {token}
```

**Query Parameters:**
- `q`: Search query
- `type`: Comma-separated types (jobs, skills, users)
- `page`: Page number
- `per_page`: Results per page

**Response (200):**
```json
{
  "success": true,
  "data": {
    "jobs": [
      {
        "id": 1,
        "title": "Laravel Developer Needed",
        "budget_min": 500,
        "budget_max": 2000,
        "type": "job"
      }
    ],
    "skills": [
      {
        "id": 1,
        "name": "Laravel Development",
        "hourly_rate": 50,
        "type": "skill"
      }
    ],
    "users": [
      {
        "id": 1,
        "first_name": "John",
        "last_name": "Doe",
        "type": "user"
      }
    ]
  },
  "meta": {
    "total_results": 3,
    "jobs_count": 1,
    "skills_count": 1,
    "users_count": 1
  }
}
```

#### Get Search Suggestions
```http
GET /api/search/suggestions?q=lar&type=jobs
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "suggestions": [
    "Laravel",
    "Laravel Developer",
    "Laravel Framework",
    "Full Stack Laravel"
  ]
}
```

### Saved Searches

#### Get Saved Searches
```http
GET /api/saved-searches
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "name": "Laravel Jobs",
      "search_type": "jobs",
      "query": "laravel",
      "filters": {
        "category_id": 1,
        "budget_min": 500,
        "budget_max": 2000,
        "job_type": "fixed"
      },
      "notifications_enabled": true,
      "notification_frequency": "daily",
      "last_executed_at": "2024-01-15T10:30:00Z",
      "created_at": "2024-01-10T08:00:00Z"
    }
  ]
}
```

#### Create Saved Search
```http
POST /api/saved-searches
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "name": "React Jobs",
  "search_type": "jobs",
  "query": "react",
  "filters": {
    "category_id": 1,
    "budget_min": 800,
    "job_type": "fixed"
  },
  "notifications_enabled": true,
  "notification_frequency": "weekly"
}
```

**Response (201):**
```json
{
  "success": true,
  "message": "Saved search created successfully",
  "data": {
    "id": 5,
    "name": "React Jobs",
    "search_type": "jobs",
    "query": "react",
    "notifications_enabled": true,
    "created_at": "2024-01-15T10:30:00Z"
  }
}
```

#### Execute Saved Search
```http
POST /api/saved-searches/1/execute
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "results": [
    {
      "id": 1,
      "title": "Laravel Developer Needed",
      "budget_min": 500,
      "budget_max": 2000
    }
  ],
  "meta": {
    "total": 5,
    "new_results": 2
  }
}
```

### Ratings

#### Get My Rating Stats
```http
GET /api/ratings/my-stats
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "average_rating": 4.8,
    "total_reviews": 25,
    "rating_distribution": {
      "5": 20,
      "4": 3,
      "3": 2,
      "2": 0,
      "1": 0
    },
    "as_client": {
      "average_rating": 4.9,
      "total_reviews": 12
    },
    "as_freelancer": {
      "average_rating": 4.7,
      "total_reviews": 13
    }
  }
}
```

#### Get User Rating Stats
```http
GET /api/ratings/user/2/stats
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 2,
      "first_name": "Jane",
      "last_name": "Smith"
    },
    "average_rating": 4.9,
    "total_reviews": 30,
    "rating_distribution": {
      "5": 27,
      "4": 2,
      "3": 1,
      "2": 0,
      "1": 0
    }
  }
}
```

#### Get Top Rated Users
```http
GET /api/ratings/top-rated?limit=10&category=development
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 2,
      "first_name": "Jane",
      "last_name": "Smith",
      "avatar": "https://storage.talent2income.com/avatars/2.jpg",
      "average_rating": 4.95,
      "total_reviews": 40,
      "skills": ["Laravel", "PHP", "JavaScript"]
    },
    {
      "id": 3,
      "first_name": "Mike",
      "last_name": "Johnson",
      "average_rating": 4.88,
      "total_reviews": 32,
      "skills": ["React", "Node.js", "MongoDB"]
    }
  ]
}
```

### GDPR

#### Create GDPR Request
```http
POST /api/gdpr/requests
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "request_type": "data_export",
  "reason": "I want to download all my personal data"
}
```

**Request Types:**
- `data_export`: Export all user data
- `data_deletion`: Delete all user data
- `data_portability`: Transfer data to another service

**Response (201):**
```json
{
  "success": true,
  "message": "GDPR request created successfully",
  "data": {
    "id": 1,
    "request_type": "data_export",
    "status": "pending",
    "reason": "I want to download all my personal data",
    "created_at": "2024-01-15T10:30:00Z",
    "expected_completion": "2024-01-30T10:30:00Z"
  }
}
```

#### Get My GDPR Requests
```http
GET /api/gdpr/requests
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "request_type": "data_export",
      "status": "completed",
      "reason": "I want to download all my personal data",
      "created_at": "2024-01-15T10:30:00Z",
      "completed_at": "2024-01-25T14:20:00Z",
      "download_url": "https://exports.talent2income.com/user_1_export.zip"
    }
  ]
}
```

#### Get Consent Status
```http
GET /api/gdpr/consent/status
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "marketing_emails": true,
    "analytics_tracking": true,
    "personalized_ads": false,
    "data_processing": true,
    "last_updated": "2024-01-15T10:30:00Z"
  }
}
```

### Admin

#### Admin Dashboard
```http
GET /api/admin/dashboard
Authorization: Bearer {admin_token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "stats": {
      "total_users": 15000,
      "active_jobs": 450,
      "completed_jobs": 2800,
      "total_revenue": 450000.00,
      "pending_payments": 25000.00
    },
    "recent_activity": [
      {
        "type": "user_registered",
        "count": 25,
        "period": "last_24h"
      },
      {
        "type": "jobs_posted",
        "count": 12,
        "period": "last_24h"
      }
    ],
    "system_health": {
      "status": "healthy",
      "issues": 0
    }
  }
}
```

#### Get All Users (Admin)
```http
GET /api/admin/users?page=1&per_page=50&status=active
Authorization: Bearer {admin_token}
```

**Query Parameters:**
- `page`: Page number
- `per_page`: Results per page
- `status`: active, suspended, banned
- `role`: client, freelancer, admin
- `search`: Search by name or email

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@example.com",
      "status": "active",
      "role": "freelancer",
      "created_at": "2024-01-15T10:30:00Z",
      "last_login_at": "2024-01-15T14:20:00Z",
      "jobs_count": 5,
      "skills_count": 3,
      "average_rating": 4.8
    }
  ],
  "meta": {
    "current_page": 1,
    "per_page": 50,
    "total": 15000,
    "last_page": 300
  }
}
```

#### Content Moderation Queue
```http
GET /api/admin/content-moderation?type=review&status=pending
Authorization: Bearer {admin_token}
```

**Response (200):**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "review",
      "content": {
        "id": 25,
        "comment": "This freelancer did a terrible job...",
        "rating": 1,
        "reviewer": {
          "id": 10,
          "name": "Angry Client"
        }
      },
      "reported_by": {
        "id": 5,
        "name": "Jane Smith"
      },
      "reason": "inappropriate_content",
      "status": "pending",
      "created_at": "2024-01-15T10:30:00Z"
    }
  ]
}
```

#### System Health
```http
GET /api/admin/system-health
Authorization: Bearer {admin_token}
```

**Response (200):**
```json
{
  "success": true,
  "data": {
    "overall_status": "healthy",
    "services": {
      "database": {
        "status": "healthy",
        "response_time": "2ms",
        "connections": 45
      },
      "cache": {
        "status": "healthy",
        "hit_rate": "94.2%",
        "memory_usage": "65%"
      },
      "storage": {
        "status": "healthy",
        "disk_usage": "78%",
        "available_space": "2.1TB"
      },
      "queue": {
        "status": "healthy",
        "pending_jobs": 12,
        "failed_jobs": 0
      }
    },
    "performance": {
      "average_response_time": "150ms",
      "uptime": "99.98%",
      "requests_per_minute": 450
    },
    "last_checked": "2024-01-15T10:30:00Z"
  }
}
```

## Error Examples

### Validation Error (422)
```json
{
  "success": false,
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."],
    "password": ["The password must be at least 8 characters."]
  }
}
```

### Unauthorized (401)
```json
{
  "success": false,
  "message": "Unauthenticated.",
  "error_code": "UNAUTHENTICATED"
}
```

### Rate Limit Exceeded (429)
```json
{
  "success": false,
  "message": "Too many requests. Please try again later.",
  "error_code": "RATE_LIMIT_EXCEEDED",
  "retry_after": 60
}
```

### Not Found (404)
```json
{
  "success": false,
  "message": "Resource not found.",
  "error_code": "NOT_FOUND"
}
```

## Best Practices

### Authentication
- Always include the `Authorization: Bearer {token}` header for protected endpoints
- Handle token expiration gracefully in your application
- Store tokens securely (never in localStorage for web apps)

### Rate Limiting
- Implement exponential backoff when hitting rate limits
- Monitor the rate limit headers in responses
- Cache responses when appropriate to reduce API calls

### Error Handling
- Always check the `success` field in responses
- Handle different HTTP status codes appropriately
- Display user-friendly error messages based on `message` field

### Pagination
- Use the `meta` object to implement pagination controls
- Default page size is 20, maximum is 100
- Include `page` and `per_page` parameters in requests

### Search and Filtering
- Use URL parameters for search queries and filters
- Combine multiple filters for more specific results
- Utilize saved searches for frequently used queries

### File Uploads
- Use `multipart/form-data` for file uploads
- Respect file size limits (typically 2MB for images)
- Handle upload progress for better user experience
