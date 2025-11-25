# Session Mode (Stateful Authentication)

Cookie-based session authentication for traditional web applications.

## Overview

Session mode provides:
- **Stateful authentication** - Server-side session storage
- **Secure HTTP-only cookies** - XSS protection
- **CSRF protection** - Built-in security
- **Session tracking** - Device, IP, location logging

## Configuration

```yaml
# config/packages/better_auth.yaml
better_auth:
    mode: 'session'
    secret: '%env(BETTER_AUTH_SECRET)%'
    session:
        lifetime: 604800          # 7 days
        cookie_name: 'better_auth_session'
```

## Authentication Flow

```
┌──────────┐                                    ┌──────────┐
│  Browser │                                    │  Server  │
└────┬─────┘                                    └────┬─────┘
     │                                               │
     │  POST /auth/login                            │
     │  {email, password}                           │
     ├──────────────────────────────────────────────►
     │                                               │
     │  Set-Cookie: better_auth_session=xxx         │
     │  {user}                                       │
     ◄──────────────────────────────────────────────┤
     │                                               │
     │  GET /api/resource                           │
     │  Cookie: better_auth_session=xxx             │
     ├──────────────────────────────────────────────►
     │                                               │
     │  {data}                                       │
     ◄──────────────────────────────────────────────┤
```

## Usage

### Login

```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -c cookies.txt \
  -d '{"email":"user@example.com","password":"password123"}'
```

**Response:**
```json
{
  "access_token": "session_token_xxx",
  "refresh_token": "session_token_xxx",
  "expires_in": 604800,
  "token_type": "Bearer",
  "user": {
    "id": "019ab13e-40f1-7b21-a672-f403d5277ec7",
    "email": "user@example.com",
    "name": "John Doe"
  }
}
```

**Cookie set:**
```
Set-Cookie: better_auth_session=xxx; HttpOnly; Secure; SameSite=Lax; Path=/
```

### Access Protected Resources

```bash
curl -X GET http://localhost:8000/api/me \
  -b cookies.txt
```

### Logout

```bash
curl -X POST http://localhost:8000/auth/logout \
  -b cookies.txt
```

---

## Security Features

### HTTP-Only Cookies

Cookies are set with:
- `HttpOnly` - Not accessible via JavaScript
- `Secure` - Only sent over HTTPS
- `SameSite=Lax` - CSRF protection

### Session Storage

Sessions are stored in the database with:
- Session token (hashed)
- User ID
- IP address
- User agent
- Created/updated timestamps
- Expiration time

### Session Tracking

```php
// Session entity stores:
$session->getToken();        // Session identifier
$session->getUserId();       // Associated user
$session->getIpAddress();    // Client IP
$session->getMetadata();     // Device, browser, OS, location
$session->getCreatedAt();    // Creation time
$session->getExpiresAt();    // Expiration time
```

---

## Symfony Security Integration

```yaml
# config/packages/security.yaml
security:
    providers:
        better_auth:
            id: BetterAuth\Symfony\Security\BetterAuthUserProvider

    firewalls:
        main:
            pattern: ^/
            stateless: false  # Enable sessions
            provider: better_auth
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator
            logout:
                path: /auth/logout
                target: /

    access_control:
        - { path: ^/auth, roles: PUBLIC_ACCESS }
        - { path: ^/admin, roles: ROLE_ADMIN }
        - { path: ^/, roles: ROLE_USER }
```

---

## Twig Integration

### Check Authentication

```twig
{% if is_granted('IS_AUTHENTICATED_FULLY') %}
    <p>Welcome, {{ app.user.email }}!</p>
    <a href="{{ path('auth_logout') }}">Logout</a>
{% else %}
    <a href="{{ path('auth_login') }}">Login</a>
{% endif %}
```

### User Information

```twig
{% if app.user %}
    <div class="user-info">
        <p>Name: {{ app.user.name }}</p>
        <p>Email: {{ app.user.email }}</p>
        <p>Verified: {{ app.user.emailVerified ? 'Yes' : 'No' }}</p>
    </div>
{% endif %}
```

### Protected Content

```twig
{% if is_granted('ROLE_ADMIN') %}
    <a href="{{ path('admin_dashboard') }}">Admin Dashboard</a>
{% endif %}
```

---

## Session Management

### List Active Sessions

```bash
curl -X GET http://localhost:8000/auth/sessions \
  -b cookies.txt
```

**Response:**
```json
{
  "sessions": [
    {
      "id": "sess_xxx",
      "device": "Desktop",
      "browser": "Chrome 120",
      "os": "Windows 11",
      "ip": "192.168.1.1",
      "location": "Paris, France",
      "current": true,
      "createdAt": "2024-01-15T10:00:00Z",
      "lastActiveAt": "2024-01-15T14:30:00Z",
      "expiresAt": "2024-01-22T10:00:00Z"
    },
    {
      "id": "sess_yyy",
      "device": "Mobile",
      "browser": "Safari",
      "os": "iOS 17",
      "ip": "10.0.0.1",
      "location": "London, UK",
      "current": false,
      "createdAt": "2024-01-14T08:00:00Z",
      "lastActiveAt": "2024-01-14T12:00:00Z",
      "expiresAt": "2024-01-21T08:00:00Z"
    }
  ]
}
```

### Revoke Specific Session

```bash
curl -X DELETE http://localhost:8000/auth/sessions/sess_yyy \
  -b cookies.txt
```

### Revoke All Other Sessions

```bash
curl -X POST http://localhost:8000/auth/revoke-all \
  -b cookies.txt
```

---

## Cookie Configuration

### Development

```yaml
# config/packages/better_auth.yaml
better_auth:
    session:
        cookie_name: 'better_auth_session'
        # Cookies work on localhost without HTTPS
```

### Production

Ensure HTTPS is enabled for secure cookies:

```yaml
# config/packages/framework.yaml
framework:
    session:
        cookie_secure: true
        cookie_httponly: true
        cookie_samesite: lax
```

---

## Session Lifetime Options

| Duration | Seconds | Use Case |
|----------|---------|----------|
| 1 hour | 3600 | High security |
| 24 hours | 86400 | Daily users |
| 7 days | 604800 | Default, balanced |
| 30 days | 2592000 | Remember me |
| 90 days | 7776000 | Long-lived |

### Remember Me

```yaml
better_auth:
    session:
        lifetime: 2592000  # 30 days for "remember me"
```

---

## CSRF Protection

For forms that modify data, include CSRF token:

```twig
<form method="post" action="{{ path('user_settings') }}">
    <input type="hidden" name="_token" value="{{ csrf_token('settings') }}">
    <!-- form fields -->
    <button type="submit">Save</button>
</form>
```

```php
// Controller
#[Route('/settings', methods: ['POST'])]
public function settings(Request $request): Response
{
    if (!$this->isCsrfTokenValid('settings', $request->request->get('_token'))) {
        throw new InvalidCsrfTokenException();
    }
    // Process form
}
```

---

## Next Steps

- [Hybrid Mode](05-HYBRID-MODE.md)
- [API Reference](09-API-REFERENCE.md)
- [Security Best Practices](11-SECURITY.md)
