# BetterAuth Cache Configuration

## Overview

BetterAuth uses Symfony's cache system for rate limiting. The cache backend determines how rate limit data is stored and persisted.

## Available Cache Backends

### Development (config/packages/dev/cache.yaml)

**Default:** APCu (in-memory cache)

```bash
# Install APCu extension if not available
sudo apt-get install php-apcu  # Debian/Ubuntu
```

**Fallback:** Filesystem cache (`var/cache/dev/`)

### Production (config/packages/prod/cache.yaml)

**Recommended:** Redis (persistent, shared across servers)

## Setup

### Option 1: Redis (Recommended for Production)

**1. Install Redis:**

```bash
# Ubuntu/Debian
sudo apt-get install redis-server

# macOS
brew install redis
brew services start redis

# Docker
docker run -d -p 6379:6379 redis:alpine
```

**2. Install Symfony Redis component:**

```bash
composer require symfony/redis
```

**3. Configure in `.env`:**

```env
# .env
REDIS_URL=redis://localhost:6379
```

**4. Production config is already set:**

```yaml
# config/packages/prod/cache.yaml
framework:
    cache:
        app: cache.adapter.redis
        default_redis_provider: '%env(REDIS_URL)%'
```

### Option 2: APCu (Single Server)

```yaml
# config/packages/prod/cache.yaml
framework:
    cache:
        app: cache.adapter.apcu
        system: cache.adapter.apcu
```

### Option 3: Filesystem (Default, Not Recommended for Production)

No configuration needed - uses `var/cache/` directory.

**Limitations:**
- Not shared across multiple servers
- Slower than in-memory solutions
- File I/O overhead

## Verification

Check which cache backend is active:

```bash
# Symfony 5+
php bin/console debug:container --tag=cache.pool
```

## Testing Rate Limiting

```bash
# Test with curl
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"wrong"}'

# Multiple attempts will trigger rate limit after configured threshold
```

## Performance Comparison

| Backend | Speed | Persistence | Multi-server | Recommendation |
|---------|-------|-------------|--------------|----------------|
| **APCu** | ⚡ Fast | ❌ No (reboot) | ❌ No | Dev only |
| **Redis** | ⚡ Fast | ✅ Yes | ✅ Yes | **Production** |
| **Filesystem** | 🐌 Slow | ✅ Yes | ❌ No | Small projects |
| **Memcached** | ⚡ Fast | ⚠️ Limited | ✅ Yes | Alternative to Redis |

## Troubleshooting

### Redis connection refused

```bash
# Check Redis is running
redis-cli ping
# Should return: PONG

# Check Redis is listening
netstat -an | grep 6379
```

### Rate limiting not working

```bash
# Verify cache pool is configured
php bin/console debug:container --tag=cache.pool

# Check cache adapter
php bin/console debug:container 'cache.app'
```
