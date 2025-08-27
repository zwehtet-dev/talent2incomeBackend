# Real-time Broadcasting Setup

This document describes the real-time messaging and broadcasting setup for the Talent2Income platform.

## Overview

The platform uses Laravel Broadcasting with Redis and Laravel Echo Server to provide real-time messaging, typing indicators, and online status tracking.

## Architecture

- **Laravel Broadcasting**: Server-side event broadcasting
- **Redis**: Message broker and presence storage
- **Laravel Echo Server**: WebSocket server for client connections
- **Predis**: Redis client for PHP

## Configuration

### Environment Variables

```env
# Broadcasting
BROADCAST_CONNECTION=redis
BROADCAST_DRIVER=redis

# Redis Configuration
REDIS_CLIENT=predis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0
REDIS_CACHE_DB=1
REDIS_SESSION_DB=2
REDIS_QUEUE_DB=3
REDIS_BROADCAST_DB=4
```

### Laravel Echo Server Configuration

The `laravel-echo-server.json` file configures the WebSocket server:

```json
{
  "authHost": "http://localhost:8000",
  "authEndpoint": "/broadcasting/auth",
  "clients": [
    {
      "appId": "talent2income",
      "key": "talent2income-key"
    }
  ],
  "database": "redis",
  "databaseConfig": {
    "redis": {
      "host": "127.0.0.1",
      "port": "6379",
      "db": 4
    }
  },
  "devMode": true,
  "port": "6001",
  "protocol": "http"
}
```

## Broadcasting Events

### MessageSent Event

Broadcasted when a new message is sent:

```php
broadcast(new MessageSent($message))->toOthers();
```

**Channels:**
- `private-user.{recipient_id}` - Personal notification channel
- `private-conversation.{conversation_id}` - Conversation channel

**Data:**
```json
{
  "id": 1,
  "content": "Hello!",
  "sender_id": 1,
  "recipient_id": 2,
  "job_id": null,
  "is_read": false,
  "created_at": "2024-01-15T10:00:00Z",
  "sender": {
    "id": 1,
    "first_name": "John",
    "last_name": "Doe",
    "avatar": "https://example.com/avatar.jpg"
  }
}
```

### MessageRead Event

Broadcasted when messages are marked as read:

```php
broadcast(new MessageRead($message))->toOthers();
```

**Channels:**
- `private-user.{sender_id}` - Notify sender
- `private-conversation.{conversation_id}` - Update conversation

**Data:**
```json
{
  "message_id": 1,
  "read_by": 2,
  "read_at": "2024-01-15T10:05:00Z"
}
```

### UserTyping Event

Broadcasted when a user starts/stops typing:

```php
broadcast(new UserTyping($user, $recipientId, $isTyping))->toOthers();
```

**Channels:**
- `private-conversation.{conversation_id}` - Conversation channel

**Data:**
```json
{
  "user_id": 1,
  "user_name": "John Doe",
  "is_typing": true,
  "timestamp": "2024-01-15T10:00:00Z"
}
```

### UserOnlineStatusChanged Event

Broadcasted when user comes online/offline:

```php
broadcast(new UserOnlineStatusChanged($user, $isOnline));
```

**Channels:**
- `presence-online-users` - Global presence channel

**Data:**
```json
{
  "user_id": 1,
  "user_name": "John Doe",
  "avatar": "https://example.com/avatar.jpg",
  "is_online": true,
  "last_seen": "2024-01-15T10:00:00Z"
}
```

## Channel Authorization

### Private Channels

#### User Channel (`private-user.{id}`)
Users can only access their own private channel:

```php
Broadcast::channel('user.{id}', function (User $user, int $id) {
    return (int) $user->id === $id;
});
```

#### Conversation Channel (`private-conversation.{conversationId}`)
Users can access conversations they participate in:

```php
Broadcast::channel('conversation.{conversationId}', function (User $user, string $conversationId) {
    $userIds = explode('-', $conversationId);
    $userId1 = (int) $userIds[0];
    $userId2 = (int) $userIds[1];
    
    return $user->id === $userId1 || $user->id === $userId2;
});
```

#### Job Channel (`private-job.{jobId}`)
Users can access job channels if they own or are assigned to the job:

```php
Broadcast::channel('job.{jobId}', function (User $user, int $jobId) {
    $job = \App\Models\Job::find($jobId);
    return $user->id === $job->user_id || $user->id === $job->assigned_to;
});
```

#### Admin Channel (`private-admin`)
Only admin users can access:

```php
Broadcast::channel('admin', function (User $user) {
    return $user->is_admin;
});
```

### Presence Channels

#### Online Users (`presence-online-users`)
All authenticated users can join:

```php
Broadcast::channel('online-users', function (User $user) {
    return [
        'id' => $user->id,
        'name' => $user->first_name . ' ' . $user->last_name,
        'avatar' => $user->avatar,
    ];
});
```

## Online Status Service

The `OnlineStatusService` manages user presence:

### Methods

- `markUserOnline(User $user)` - Mark user as online
- `markUserOffline(User $user)` - Mark user as offline
- `isUserOnline(int $userId)` - Check if user is online
- `getUserLastSeen(int $userId)` - Get last seen timestamp
- `getOnlineUsers()` - Get all online user IDs
- `updateUserActivity(User $user)` - Update user activity

### Automatic Tracking

The `TrackUserActivity` middleware automatically updates user activity on API requests:

```php
// Applied to all API routes
$middleware->group('api', [
    // ... other middleware
    'track.activity',
]);
```

## API Endpoints

### Real-time Messaging

- `POST /api/messages` - Send message (broadcasts MessageSent)
- `POST /api/messages/mark-read/{user}` - Mark as read (broadcasts MessageRead)
- `POST /api/messages/typing/{user}` - Send typing indicator (broadcasts UserTyping)

### Online Status

- `GET /api/users/online` - Get online users list
- `GET /api/users/{user}/online-status` - Get specific user's online status

## Frontend Integration

### Laravel Echo Setup

```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: 'talent2income-key',
    wsHost: window.location.hostname,
    wsPort: 6001,
    forceTLS: false,
    disableStats: true,
    auth: {
        headers: {
            Authorization: `Bearer ${token}`,
        },
    },
});
```

### Listening to Events

```javascript
// Listen for new messages
Echo.private(`user.${userId}`)
    .listen('.message.sent', (e) => {
        console.log('New message:', e);
    });

// Listen for typing indicators
Echo.private(`conversation.${conversationId}`)
    .listen('.user.typing', (e) => {
        console.log('User typing:', e);
    });

// Join presence channel for online users
Echo.join('online-users')
    .here((users) => {
        console.log('Currently online:', users);
    })
    .joining((user) => {
        console.log('User joined:', user);
    })
    .leaving((user) => {
        console.log('User left:', user);
    });
```

## Running the Services

### Start Redis Server

```bash
redis-server
```

### Start Laravel Echo Server

```bash
# Install globally
npm install -g laravel-echo-server

# Start server
laravel-echo-server start
```

### Start Laravel Application

```bash
php artisan serve
```

## Testing

The broadcasting functionality is tested in:

- `tests/Feature/BroadcastingTest.php` - Comprehensive broadcasting tests
- `tests/Feature/SimpleBroadcastingTest.php` - Basic functionality tests

Run tests:

```bash
php artisan test --filter=Broadcasting
```

## Security Considerations

1. **Channel Authorization**: All private channels require proper authorization
2. **Rate Limiting**: Typing indicators and messages are rate-limited
3. **User Blocking**: Blocked users cannot send messages
4. **Token Validation**: All WebSocket connections require valid Sanctum tokens
5. **CORS Configuration**: Proper CORS setup for WebSocket connections

## Monitoring

- Redis connection status
- WebSocket connection count
- Message delivery rates
- Online user counts
- Channel subscription metrics

## Troubleshooting

### Common Issues

1. **Redis Connection Failed**
   - Check Redis server is running
   - Verify Redis configuration in `.env`

2. **WebSocket Connection Failed**
   - Check Laravel Echo Server is running on port 6001
   - Verify CORS configuration
   - Check authentication headers

3. **Events Not Broadcasting**
   - Verify `BROADCAST_DRIVER=redis` in `.env`
   - Check Redis database configuration
   - Ensure events implement `ShouldBroadcast`

4. **Authorization Failed**
   - Check channel authorization callbacks
   - Verify user authentication
   - Check Sanctum token validity