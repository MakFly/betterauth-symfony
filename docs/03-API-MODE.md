# API Mode (Stateless Authentication)

Stateless authentication using Paseto V4 tokens for SPAs, mobile apps, and microservices.

## Overview

API mode provides:
- **Stateless authentication** - No server-side session storage
- **Paseto V4 tokens** - More secure than JWT
- **Access + Refresh tokens** - Short-lived access, long-lived refresh
- **Bearer token authentication** - Standard HTTP Authorization header

## Configuration

```yaml
# config/packages/better_auth.yaml
better_auth:
    mode: 'api'
    secret: '%env(BETTER_AUTH_SECRET)%'
    token:
        lifetime: 3600          # Access token: 1 hour
        refresh_lifetime: 2592000  # Refresh token: 30 days
```

## Authentication Flow

```
┌──────────┐                                    ┌──────────┐
│  Client  │                                    │  Server  │
└────┬─────┘                                    └────┬─────┘
     │                                               │
     │  POST /auth/login                            │
     │  {email, password}                           │
     ├──────────────────────────────────────────────►
     │                                               │
     │  {access_token, refresh_token, user}         │
     ◄──────────────────────────────────────────────┤
     │                                               │
     │  GET /api/resource                           │
     │  Authorization: Bearer <access_token>        │
     ├──────────────────────────────────────────────►
     │                                               │
     │  {data}                                       │
     ◄──────────────────────────────────────────────┤
     │                                               │
     │  (access_token expired)                       │
     │  POST /auth/refresh                          │
     │  {refreshToken}                              │
     ├──────────────────────────────────────────────►
     │                                               │
     │  {new_access_token, new_refresh_token}       │
     ◄──────────────────────────────────────────────┤
```

## Usage

### Login

```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"password123"}'
```

**Response:**
```json
{
  "access_token": "v4.local.eyJ...",
  "refresh_token": "rt_abc123...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": {
    "id": "019ab13e-40f1-7b21-a672-f403d5277ec7",
    "email": "user@example.com",
    "username": "John Doe",
    "emailVerified": true
  }
}
```

### Access Protected Resources

```bash
curl -X GET http://localhost:8000/api/me \
  -H "Authorization: Bearer v4.local.eyJ..."
```

### Refresh Token

```bash
curl -X POST http://localhost:8000/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refreshToken":"rt_abc123..."}'
```

**Response:**
```json
{
  "access_token": "v4.local.new...",
  "refresh_token": "rt_new123...",
  "expires_in": 3600,
  "token_type": "Bearer"
}
```

### Logout

```bash
curl -X POST http://localhost:8000/auth/logout \
  -H "Authorization: Bearer v4.local.eyJ..."
```

---

## Frontend Integration

### React/TypeScript Example

```typescript
// lib/auth.ts
const API_URL = 'http://localhost:8000';

interface LoginResponse {
  access_token: string;
  refresh_token: string;
  expires_in: number;
  user: User;
}

// Store tokens
function setTokens(accessToken: string, refreshToken: string) {
  localStorage.setItem('access_token', accessToken);
  localStorage.setItem('refresh_token', refreshToken);
}

// Get access token
function getAccessToken(): string | null {
  return localStorage.getItem('access_token');
}

// Login
async function login(email: string, password: string): Promise<LoginResponse> {
  const response = await fetch(`${API_URL}/auth/login`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, password }),
  });

  if (!response.ok) {
    throw new Error('Login failed');
  }

  const data = await response.json();
  setTokens(data.access_token, data.refresh_token);
  return data;
}

// Authenticated request
async function authFetch(url: string, options: RequestInit = {}): Promise<Response> {
  const token = getAccessToken();

  const response = await fetch(url, {
    ...options,
    headers: {
      ...options.headers,
      'Authorization': `Bearer ${token}`,
    },
  });

  // Handle token expiration
  if (response.status === 401) {
    const newToken = await refreshToken();
    if (newToken) {
      return fetch(url, {
        ...options,
        headers: {
          ...options.headers,
          'Authorization': `Bearer ${newToken}`,
        },
      });
    }
  }

  return response;
}

// Refresh token
async function refreshToken(): Promise<string | null> {
  const refresh = localStorage.getItem('refresh_token');
  if (!refresh) return null;

  const response = await fetch(`${API_URL}/auth/refresh`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ refreshToken: refresh }),
  });

  if (!response.ok) {
    // Refresh failed, clear tokens
    localStorage.removeItem('access_token');
    localStorage.removeItem('refresh_token');
    return null;
  }

  const data = await response.json();
  setTokens(data.access_token, data.refresh_token);
  return data.access_token;
}
```

### Axios Interceptor

```typescript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000',
});

// Request interceptor - add token
api.interceptors.request.use((config) => {
  const token = localStorage.getItem('access_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});

// Response interceptor - handle 401
let isRefreshing = false;
let refreshSubscribers: ((token: string) => void)[] = [];

api.interceptors.response.use(
  (response) => response,
  async (error) => {
    const originalRequest = error.config;

    if (error.response?.status === 401 && !originalRequest._retry) {
      if (isRefreshing) {
        return new Promise((resolve) => {
          refreshSubscribers.push((token) => {
            originalRequest.headers.Authorization = `Bearer ${token}`;
            resolve(api(originalRequest));
          });
        });
      }

      originalRequest._retry = true;
      isRefreshing = true;

      try {
        const refreshToken = localStorage.getItem('refresh_token');
        const response = await axios.post('/auth/refresh', { refreshToken });
        const { access_token, refresh_token } = response.data;

        localStorage.setItem('access_token', access_token);
        localStorage.setItem('refresh_token', refresh_token);

        refreshSubscribers.forEach((cb) => cb(access_token));
        refreshSubscribers = [];

        originalRequest.headers.Authorization = `Bearer ${access_token}`;
        return api(originalRequest);
      } catch (refreshError) {
        localStorage.removeItem('access_token');
        localStorage.removeItem('refresh_token');
        window.location.href = '/login';
        return Promise.reject(refreshError);
      } finally {
        isRefreshing = false;
      }
    }

    return Promise.reject(error);
  }
);

export default api;
```

---

## Token Structure

### Access Token (Paseto V4)

```
v4.local.eyJ...
```

**Payload:**
```json
{
  "sub": "019ab13e-40f1-7b21-a672-f403d5277ec7",
  "email": "user@example.com",
  "username": "John Doe",
  "iat": "2024-01-15T10:00:00Z",
  "exp": "2024-01-15T11:00:00Z"
}
```

### Refresh Token

Opaque string stored in database:
```
rt_019ab13e40f17b21a672f403d5277ec7
```

---

## Security Considerations

### Token Storage

| Storage | Pros | Cons |
|---------|------|------|
| localStorage | Simple, persists | XSS vulnerable |
| sessionStorage | Clears on close | XSS vulnerable |
| HttpOnly Cookie | XSS protected | CSRF vulnerable |
| Memory only | Most secure | Lost on refresh |

**Recommendation:** Use HttpOnly cookies for refresh token, memory for access token.

### Token Lifetimes

| Token | Recommended | Maximum |
|-------|-------------|---------|
| Access | 15min - 1h | 2h |
| Refresh | 7 - 30 days | 90 days |

### Refresh Token Rotation

BetterAuth uses one-time-use refresh tokens:
1. Client sends refresh token
2. Server validates and invalidates old token
3. Server issues new access + refresh tokens
4. If old refresh token is reused, all tokens are revoked

---

## Session Tracking in API Mode

Even in stateless API mode, BetterAuth tracks sessions for:
- Device identification
- IP address logging
- Session revocation
- Security audit

```bash
# List active sessions
curl -X GET http://localhost:8000/auth/sessions \
  -H "Authorization: Bearer v4.local.eyJ..."

# Revoke specific session
curl -X DELETE http://localhost:8000/auth/sessions/{sessionId} \
  -H "Authorization: Bearer v4.local.eyJ..."

# Revoke all sessions
curl -X POST http://localhost:8000/auth/revoke-all \
  -H "Authorization: Bearer v4.local.eyJ..."
```

---

## Next Steps

- [Session Mode](04-SESSION-MODE.md)
- [Hybrid Mode](05-HYBRID-MODE.md)
- [API Reference](09-API-REFERENCE.md)
