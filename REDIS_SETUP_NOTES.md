# Redis Setup Notes for Blood Stream Project

## What is Redis?
Redis is an in-memory data structure store used for caching, queuing, and session storage. It's much faster than database operations for temporary data.

## Installation & Configuration

### 1. Install Redis Server
- **Windows**: Download from https://redis.io/download or use WSL
- **Docker**: `docker run -d -p 6379:6379 redis:alpine`

### 2. Install PHP Redis Extension
```bash
# Via Composer (predis - pure PHP)
composer require predis/predis

# Or install php-redis extension (faster, requires compilation)
pecl install redis
```

### 3. Laravel Configuration

#### Update .env file:
```env
# Cache Configuration
CACHE_DRIVER=redis
CACHE_PREFIX=bloodstream

# Queue Configuration  
QUEUE_CONNECTION=redis

# Session Configuration (optional)
SESSION_DRIVER=redis

# Redis Configuration
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_DB=0

# Additional Redis databases for different purposes
REDIS_CACHE_DB=1
REDIS_QUEUE_DB=2
REDIS_SESSION_DB=3
```

#### Update config/database.php:
```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'predis'),
    
    'options' => [
        'cluster' => env('REDIS_CLUSTER', 'redis'),
        'prefix' => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_').'_database_'),
    ],
    
    'default' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_DB', '0'),
    ],
    
    'cache' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_CACHE_DB', '1'),
    ],
    
    'queue' => [
        'url' => env('REDIS_URL'),
        'host' => env('REDIS_HOST', '127.0.0.1'),
        'password' => env('REDIS_PASSWORD'),
        'port' => env('REDIS_PORT', '6379'),
        'database' => env('REDIS_QUEUE_DB', '2'),
    ],
],
```

## Usage in Our Project

### 1. API Token Caching
```php
// Store token for 30 days
Cache::put('ai_api_token', $token, now()->addDays(30));

// Retrieve token
$token = Cache::get('ai_api_token');
```

### 2. Patient History Caching
```php
// Store patient data for 1 hour
Cache::put("patient_history_{$icno}", $healthDetails, now()->addHour());

// Retrieve patient data
$cachedHistory = Cache::get("patient_history_{$icno}");
```

### 3. Job Queuing
```php
// Dispatch job to Redis queue
ProcessTestResultsJob::dispatch();

// Process queue
php artisan queue:work redis
```

### 4. Rate Limiting
```php
// Laravel's built-in rate limiter uses cache
RateLimiter::attempt('api_calls', 5, function() {
    // Make API call
});
```

## Testing Redis Connection

### Command Line Test:
```bash
# Test Redis connection
redis-cli ping
# Should return: PONG

# Monitor Redis activity
redis-cli monitor
```

### Laravel Test:
```php
// In tinker: php artisan tinker
Cache::put('test', 'Hello Redis!', 60);
Cache::get('test'); // Should return: "Hello Redis!"
```

## Useful Redis Commands

```bash
# Connect to Redis CLI
redis-cli

# List all keys
KEYS *

# Get specific key
GET bloodstream_cache:patient_history_123456789

# Delete specific key  
DEL bloodstream_cache:ai_api_token

# Clear all cache
FLUSHDB

# Monitor real-time commands
MONITOR

# Check memory usage
INFO memory
```

## Performance Benefits

1. **Speed**: Redis is in-memory, 10-100x faster than database queries
2. **Reduced API Calls**: Cache API tokens and patient data
3. **Queue Management**: Handle 200 test results efficiently
4. **Rate Limiting**: Control API call frequency automatically

## Troubleshooting

### Common Issues:
1. **Connection Refused**: Check if Redis server is running
2. **Memory Issues**: Monitor Redis memory usage with `INFO memory`
3. **Permission Denied**: Check Redis configuration file permissions
4. **Slow Performance**: Consider using php-redis extension instead of predis

### Memory Management:
```bash
# Set max memory limit in redis.conf
maxmemory 256mb
maxmemory-policy allkeys-lru
```

This setup will handle our caching, queuing, and rate limiting needs efficiently.