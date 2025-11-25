# Hybrid Mode (API + Session)

Hybrid mode supports both stateless tokens AND cookie-based sessions for maximum flexibility.

## Overview

Hybrid mode allows:
- **API clients** (mobile, SPAs) → Use Bearer tokens
- **Web clients** (traditional) → Use cookies
- **Same backend** handles both automatically

## Configuration

```yaml
# config/packages/better_auth.yaml
better_auth:
    mode: 'hybrid'
    secret: '%env(BETTER_AUTH_SECRET)%'

    session:
        lifetime: 604800        # 7 days
        cookie_name: 'better_auth_session'

    token:
        lifetime: 3600          # 1 hour
        refresh_lifetime: 2592000  # 30 days
```

---

## How It Works

### Backend Auto-Detection

The backend accepts **both** authentication methods:

```php
// AuthController.php
private function getAuthToken(Request $request): ?string
{
    // 1. Try Bearer token (API mode)
    $token = $this->getBearerToken($request);
    if ($token) {
        return $token;
    }

    // 2. Try cookie (Session mode)
    $token = $request->cookies->get('access_token');
    if ($token) {
        return $token;
    }

    return null;
}
```

### Client Choice

Clients choose how to authenticate:

**API Style (Bearer Token):**
```typescript
// Store in memory/localStorage
localStorage.setItem('access_token', token);

// Send in header
headers: { 'Authorization': `Bearer ${token}` }
```

**Session Style (Cookie):**
```typescript
// Store in cookie
setCookie('access_token', token, 1);

// Automatically sent with requests
fetch('/api/me', { credentials: 'include' });
```

---

## Use Cases

### Case 1: Web App with Magic Link

**Flow:**
1. User clicks Magic Link in email
2. Backend generates tokens
3. Frontend stores in **cookies**
4. Cookies sent automatically with each request
5. User stays logged in after refresh

```typescript
// Magic Link callback
const { access_token, refresh_token } = await magicLinkApi.verify(token);
setCookie('access_token', access_token, 1);
setCookie('refresh_token', refresh_token, 7);
```

### Case 2: Mobile + Web App

**Mobile (API):**
```typescript
// Store securely
SecureStore.setItemAsync('access_token', token);

// Send in header
headers: { 'Authorization': `Bearer ${token}` }
```

**Web (Session):**
```typescript
// Store in cookie
document.cookie = `access_token=${token}`;

// Auto-sent with requests
fetch('/api/me');  // Works!
```

### Case 3: Public API + Admin Dashboard

**Public API:**
- Developers use Bearer tokens
- Long-lived tokens, revocable

**Admin Dashboard:**
- Admins use Magic Link or login
- Session cookies for smooth UX

---

## Frontend Integration

### Axios Configuration

```typescript
const api = axios.create({
  baseURL: 'http://localhost:8000',
  withCredentials: true,  // Important for cookies!
});

// Add token to header (optional, for API mode)
api.interceptors.request.use((config) => {
  const token = getCookie('access_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
```

### React Example

```tsx
// Login can work with either method
async function login(email: string, password: string) {
  const response = await api.post('/auth/login', { email, password });
  const { access_token, refresh_token, user } = response.data;

  // Choice: Store in cookies (session-like)
  setCookie('access_token', access_token, 1);
  setCookie('refresh_token', refresh_token, 7);

  // OR: Store in memory/localStorage (API-like)
  // localStorage.setItem('access_token', access_token);

  return user;
}
```

---

## CORS Configuration

For cross-origin requests with cookies:

```yaml
# config/packages/nelmio_cors.yaml
nelmio_cors:
    defaults:
        origin_regex: true
        allow_origin: ['%env(FRONTEND_URL)%']
        allow_credentials: true  # Required for cookies!
        allow_headers: ['*']
        allow_methods: ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS']
```

```env
FRONTEND_URL=https://myapp.com
```

---

## Cookie Security

### Development

```php
setCookie('access_token', $token, [
    'expires' => time() + 86400,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'lax',
]);
```

### Production

```php
setCookie('access_token', $token, [
    'expires' => time() + 86400,
    'path' => '/',
    'httponly' => true,
    'secure' => true,      // HTTPS only
    'samesite' => 'strict', // CSRF protection
]);
```

---

## Migration

### From API to Hybrid

```yaml
# Before
better_auth:
    mode: 'api'

# After
better_auth:
    mode: 'hybrid'
```

**No breaking changes!** API tokens continue to work, cookies now also supported.

### From Session to Hybrid

```yaml
# Before
better_auth:
    mode: 'session'

# After
better_auth:
    mode: 'hybrid'
```

**No breaking changes!** Sessions continue to work, API tokens now also supported.

---

## When to Use Hybrid Mode

| Scenario | Recommendation |
|----------|----------------|
| SPA only | `api` mode |
| Traditional web only | `session` mode |
| Mobile + Web | `hybrid` mode |
| Magic Link auth | `hybrid` mode |
| Public API + Dashboard | `hybrid` mode |
| Unknown future needs | `hybrid` mode |

**Recommendation:** Use `hybrid` by default unless you have specific requirements.

---

## Advantages

- **Maximum flexibility** - Supports all use cases
- **Compatibility** - Works with web, mobile, APIs
- **Optimal UX** - Magic Link + refresh without disconnection
- **Security** - Immediate session revocation when needed
- **Scalability** - JWT tokens don't require server storage

---

## Next Steps

- [API Mode](03-API-MODE.md)
- [Session Mode](04-SESSION-MODE.md)
- [Security](11-SECURITY.md)
