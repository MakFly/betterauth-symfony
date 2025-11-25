# Security

Best practices and security hardening for BetterAuth.

## Security Features

BetterAuth includes:
- **Paseto V4 tokens** - More secure than JWT
- **Argon2id password hashing** - Memory-hard, GPU-resistant
- **Refresh token rotation** - One-time use tokens
- **Session tracking** - Device, IP, location logging
- **Rate limiting** - Brute force protection
- **CSRF protection** - For session mode

---

## Token Security

### Paseto vs JWT

| Feature | Paseto V4 | JWT |
|---------|-----------|-----|
| Algorithm agility | ❌ Fixed (secure) | ⚠️ Vulnerable to confusion |
| "none" algorithm | ❌ Not possible | ⚠️ Possible vulnerability |
| Encryption | ✅ Built-in | ❌ Separate (JWE) |
| Key confusion | ❌ Not possible | ⚠️ RS/HS confusion attacks |

### Token Best Practices

```yaml
better_auth:
    token:
        # Short access tokens (15min - 1h)
        lifetime: 3600

        # Longer refresh tokens (7-30 days)
        refresh_lifetime: 2592000
```

### Refresh Token Rotation

BetterAuth uses one-time-use refresh tokens:

1. Client uses refresh token
2. Server invalidates old token
3. Server issues new token pair
4. If old token is reused → All tokens revoked (potential theft)

---

## Password Security

### Argon2id Configuration

BetterAuth uses Argon2id by default:

```yaml
# config/packages/security.yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface:
            algorithm: argon2id
            memory_cost: 65536    # 64MB
            time_cost: 4
            threads: 1
```

### Password Requirements

Recommended minimum:
- 8+ characters
- Mix of letters, numbers, symbols
- Not in common password lists

```php
use Symfony\Component\Validator\Constraints as Assert;

#[Assert\Length(min: 8, minMessage: 'Password must be at least 8 characters')]
#[Assert\NotCompromisedPassword(message: 'This password has been leaked')]
private string $password;
```

---

## Rate Limiting

### Configure Rate Limiter

```yaml
# config/packages/rate_limiter.yaml
framework:
    rate_limiter:
        login:
            policy: 'sliding_window'
            limit: 5
            interval: '1 minute'

        register:
            policy: 'sliding_window'
            limit: 3
            interval: '1 minute'

        api:
            policy: 'sliding_window'
            limit: 60
            interval: '1 minute'
```

### Apply Rate Limiting

```php
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[Route('/auth/login', methods: ['POST'])]
public function login(
    Request $request,
    RateLimiterFactory $loginLimiter
): JsonResponse {
    $limiter = $loginLimiter->create($request->getClientIp());

    if (!$limiter->consume()->isAccepted()) {
        return $this->json([
            'error' => 'Too many login attempts. Please try again later.'
        ], 429);
    }

    // Continue with login...
}
```

---

## Session Security

### Cookie Configuration

```yaml
# config/packages/framework.yaml
framework:
    session:
        cookie_secure: true         # HTTPS only
        cookie_httponly: true       # No JavaScript access
        cookie_samesite: lax        # CSRF protection
        cookie_lifetime: 0          # Session cookie
```

### Session Fixation Protection

BetterAuth regenerates session IDs on login:

```php
// Session is regenerated automatically on successful authentication
$this->authManager->signIn($email, $password, $ip, $userAgent);
```

---

## HTTPS Configuration

### Force HTTPS

```yaml
# config/packages/framework.yaml
framework:
    trusted_proxies: '%env(TRUSTED_PROXIES)%'
    trusted_headers: ['x-forwarded-for', 'x-forwarded-proto']
```

```php
// public/index.php
if ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
```

### HSTS Header

```yaml
# config/packages/nelmio_security.yaml
nelmio_security:
    forced_ssl:
        enabled: true
        hsts_max_age: 31536000        # 1 year
        hsts_subdomains: true
        hsts_preload: true
```

---

## Input Validation

### Validate All Input

```php
use Symfony\Component\Validator\Validator\ValidatorInterface;

public function register(Request $request, ValidatorInterface $validator): JsonResponse
{
    $data = json_decode($request->getContent(), true);

    // Validate JSON
    if (json_last_error() !== JSON_ERROR_NONE) {
        return $this->json(['error' => 'Invalid JSON'], 400);
    }

    // Validate fields
    $constraints = new Assert\Collection([
        'email' => [
            new Assert\NotBlank(),
            new Assert\Email(['mode' => 'strict']),
            new Assert\Length(['max' => 180]),
        ],
        'password' => [
            new Assert\NotBlank(),
            new Assert\Length(['min' => 8, 'max' => 4096]),
            new Assert\NotCompromisedPassword(),
        ],
    ]);

    $errors = $validator->validate($data, $constraints);

    if (count($errors) > 0) {
        return $this->json(['error' => (string) $errors], 400);
    }

    // Continue...
}
```

### Sanitize Output

```php
// Always escape user data in responses
return $this->json([
    'user' => [
        'name' => htmlspecialchars($user->getName(), ENT_QUOTES, 'UTF-8'),
    ],
]);
```

---

## CORS Configuration

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    defaults:
        allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
        allow_headers: ['Content-Type', 'Authorization']
        expose_headers: ['X-RateLimit-Limit', 'X-RateLimit-Remaining']
        max_age: 3600
    paths:
        '^/auth':
            allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
        '^/api':
            allow_origin: ['%env(CORS_ALLOW_ORIGIN)%']
```

```env
CORS_ALLOW_ORIGIN=https://myapp.com
```

---

## Environment Security

### Secret Management

```env
# NEVER commit these values
BETTER_AUTH_SECRET=change_me_in_production_64_chars_minimum
DATABASE_URL=mysql://user:password@localhost/db

# Use environment-specific files
# .env.local (ignored by git)
# .env.prod.local (production secrets)
```

### Generate Secure Secret

```bash
# 64 character hex string
php -r "echo bin2hex(random_bytes(32));"

# Or OpenSSL
openssl rand -hex 32
```

---

## Security Headers

### Configure Headers

```yaml
# config/packages/nelmio_security.yaml
nelmio_security:
    content_type:
        nosniff: true

    xss_protection:
        enabled: true
        mode_block: true

    clickjacking:
        paths:
            '^/.*': DENY

    csp:
        enabled: true
        hosts: []
        content_types: []
        report_only: false
        default_src: ["'self'"]
        script_src: ["'self'"]
        style_src: ["'self'", "'unsafe-inline'"]
        img_src: ["'self'", 'data:']
```

---

## Audit Logging

### Log Security Events

```php
<?php

namespace App\EventSubscriber;

use BetterAuth\Symfony\Event\BetterAuthEvents;
use Psr\Log\LoggerInterface;

class SecurityAuditSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            BetterAuthEvents::AUTHENTICATION_SUCCESS => 'onSuccess',
            BetterAuthEvents::AUTHENTICATION_FAILURE => 'onFailure',
            BetterAuthEvents::TOKEN_INVALID => 'onInvalidToken',
        ];
    }

    public function onSuccess($event): void
    {
        $this->logger->info('Authentication successful', [
            'user_id' => $event->getUser()->getId(),
            'ip' => $event->getRequest()->getClientIp(),
            'user_agent' => $event->getRequest()->headers->get('User-Agent'),
        ]);
    }

    public function onFailure($event): void
    {
        $this->logger->warning('Authentication failed', [
            'reason' => $event->getException()->getMessage(),
            'ip' => $event->getRequest()->getClientIp(),
        ]);
    }

    public function onInvalidToken($event): void
    {
        $this->logger->warning('Invalid token detected', [
            'ip' => $event->getRequest()->getClientIp(),
            'path' => $event->getRequest()->getPathInfo(),
        ]);
    }
}
```

---

## Security Checklist

### Production Deployment

- [ ] HTTPS enabled everywhere
- [ ] Secure secret key configured
- [ ] Rate limiting enabled
- [ ] CORS properly configured
- [ ] Security headers set
- [ ] Audit logging enabled
- [ ] Error messages don't leak info
- [ ] Debug mode disabled
- [ ] Dependencies up to date

### Regular Maintenance

- [ ] Review audit logs
- [ ] Update dependencies monthly
- [ ] Rotate secrets annually
- [ ] Test backup/recovery
- [ ] Security scan codebase

---

## Symfony Security Integration

```yaml
# config/packages/security.yaml
security:
    enable_authenticator_manager: true

    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    providers:
        better_auth:
            id: BetterAuth\Symfony\Security\BetterAuthUserProvider

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        auth:
            pattern: ^/auth
            stateless: true
            security: false

        api:
            pattern: ^/
            stateless: true
            provider: better_auth
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator

    access_control:
        - { path: ^/auth, roles: PUBLIC_ACCESS }
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/, roles: ROLE_USER }
```

---

## Next Steps

- [Testing](12-TESTING.md)
- [Troubleshooting](13-TROUBLESHOOTING.md)
- [API Reference](09-API-REFERENCE.md)
