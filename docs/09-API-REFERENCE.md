# API Reference

Complete reference for all BetterAuth authentication endpoints.

## Base URL

```
http://localhost:8000
```

## Authentication

Protected endpoints require the `Authorization` header:

```
Authorization: Bearer <access_token>
```

---

## Endpoints Overview

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/auth/register` | No | Register new user |
| POST | `/auth/login` | No | Login user |
| POST | `/auth/login/2fa` | No | Login with 2FA |
| GET | `/auth/me` | Yes | Get current user |
| POST | `/auth/refresh` | No | Refresh access token |
| POST | `/auth/logout` | Yes | Logout user |
| POST | `/auth/revoke-all` | Yes | Revoke all sessions |
| GET | `/auth/sessions` | Yes | List active sessions |
| DELETE | `/auth/sessions/{id}` | Yes | Revoke specific session |
| GET | `/auth/oauth/{provider}` | No | Get OAuth URL |
| GET | `/auth/oauth/{provider}/callback` | No | OAuth callback |

---

## User Registration

### POST /auth/register

Register a new user account.

**Request:**
```bash
curl -X POST http://localhost:8000/auth/register \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePassword123",
    "name": "John Doe"
  }'
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | Yes | User email |
| password | string | Yes | Password (min 8 chars) |
| name | string | No | Display name |

**Response (201 Created):**
```json
{
  "access_token": "v4.local.eyJ...",
  "refresh_token": "rt_abc123...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": {
    "id": "019ab13e-40f1-7b21-a672-f403d5277ec7",
    "email": "user@example.com",
    "name": "John Doe",
    "emailVerified": false,
    "createdAt": "2024-01-15T10:00:00+00:00"
  }
}
```

**Errors:**
| Code | Error | Description |
|------|-------|-------------|
| 400 | Email and password are required | Missing fields |
| 400 | User already exists | Email taken |

---

## User Login

### POST /auth/login

Authenticate user and get tokens.

**Request:**
```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePassword123"
  }'
```

**Response (200 OK):**
```json
{
  "access_token": "v4.local.eyJ...",
  "refresh_token": "rt_abc123...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": {
    "id": "019ab13e-40f1-7b21-a672-f403d5277ec7",
    "email": "user@example.com",
    "name": "John Doe",
    "emailVerified": true,
    "createdAt": "2024-01-15T10:00:00+00:00",
    "updatedAt": "2024-01-16T14:30:00+00:00"
  }
}
```

**2FA Required Response (200 OK):**
```json
{
  "requires2fa": true,
  "message": "Two-factor authentication required",
  "user": {
    "id": "019ab13e-40f1-7b21-a672-f403d5277ec7",
    "email": "user@example.com"
  }
}
```

**Errors:**
| Code | Error | Description |
|------|-------|-------------|
| 400 | Email and password are required | Missing fields |
| 401 | Invalid credentials | Wrong email/password |

---

## Login with 2FA

### POST /auth/login/2fa

Complete login with two-factor authentication code.

**Request:**
```bash
curl -X POST http://localhost:8000/auth/login/2fa \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "SecurePassword123",
    "code": "123456"
  }'
```

**Request Body:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| email | string | Yes | User email |
| password | string | Yes | User password |
| code | string | Yes | 6-digit TOTP code |

**Response (200 OK):**
```json
{
  "access_token": "v4.local.eyJ...",
  "refresh_token": "rt_abc123...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": { ... }
}
```

**Errors:**
| Code | Error | Description |
|------|-------|-------------|
| 400 | Email, password and 2FA code are required | Missing fields |
| 401 | Invalid 2FA code | Wrong TOTP code |

---

## Get Current User

### GET /auth/me

Get authenticated user's information.

**Request:**
```bash
curl -X GET http://localhost:8000/auth/me \
  -H "Authorization: Bearer v4.local.eyJ..."
```

**Response (200 OK):**
```json
{
  "id": "019ab13e-40f1-7b21-a672-f403d5277ec7",
  "email": "user@example.com",
  "name": "John Doe",
  "emailVerified": true,
  "createdAt": "2024-01-15T10:00:00+00:00",
  "updatedAt": "2024-01-16T14:30:00+00:00"
}
```

**Errors:**
| Code | Error | Description |
|------|-------|-------------|
| 401 | No token provided | Missing Authorization header |
| 401 | Invalid token | Expired or invalid token |

---

## Refresh Token

### POST /auth/refresh

Get new access token using refresh token.

**Request:**
```bash
curl -X POST http://localhost:8000/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refreshToken": "rt_abc123..."}'
```

**Response (200 OK):**
```json
{
  "access_token": "v4.local.new...",
  "refresh_token": "rt_new123...",
  "expires_in": 3600,
  "token_type": "Bearer"
}
```

**Errors:**
| Code | Error | Description |
|------|-------|-------------|
| 400 | Refresh token is required | Missing refreshToken |
| 401 | Invalid refresh token | Token invalid/expired/revoked |

---

## Logout

### POST /auth/logout

Logout current session.

**Request:**
```bash
curl -X POST http://localhost:8000/auth/logout \
  -H "Authorization: Bearer v4.local.eyJ..."
```

**Response (200 OK):**
```json
{
  "message": "Logged out successfully"
}
```

---

## Revoke All Sessions

### POST /auth/revoke-all

Revoke all refresh tokens and sessions.

**Request:**
```bash
curl -X POST http://localhost:8000/auth/revoke-all \
  -H "Authorization: Bearer v4.local.eyJ..."
```

**Response (200 OK):**
```json
{
  "message": "All sessions revoked successfully",
  "count": 5
}
```

---

## List Sessions

### GET /auth/sessions

Get all active sessions for the user.

**Request:**
```bash
curl -X GET http://localhost:8000/auth/sessions \
  -H "Authorization: Bearer v4.local.eyJ..."
```

**Response (200 OK):**
```json
{
  "sessions": [
    {
      "id": "sess_abc123",
      "device": "Desktop",
      "browser": "Chrome 120",
      "os": "Windows 11",
      "ip": "192.168.1.1",
      "location": "Paris, France",
      "current": true,
      "createdAt": "2024-01-15 10:00:00",
      "lastActiveAt": "2024-01-15 14:30:00",
      "expiresAt": "2024-01-22 10:00:00"
    },
    {
      "id": "sess_def456",
      "device": "Mobile",
      "browser": "Safari",
      "os": "iOS 17",
      "ip": "10.0.0.1",
      "location": "London, UK",
      "current": false,
      "createdAt": "2024-01-14 08:00:00",
      "lastActiveAt": "2024-01-14 12:00:00",
      "expiresAt": "2024-01-21 08:00:00"
    }
  ]
}
```

---

## Revoke Session

### DELETE /auth/sessions/{sessionId}

Revoke a specific session.

**Request:**
```bash
curl -X DELETE http://localhost:8000/auth/sessions/sess_def456 \
  -H "Authorization: Bearer v4.local.eyJ..."
```

**Response (200 OK):**
```json
{
  "message": "Session revoked successfully"
}
```

---

## OAuth Authorization

### GET /auth/oauth/{provider}

Get OAuth authorization URL.

**Request:**
```bash
curl -X GET http://localhost:8000/auth/oauth/google
```

**Response (200 OK):**
```json
{
  "url": "https://accounts.google.com/o/oauth2/v2/auth?...",
  "state": "abc123xyz"
}
```

**Supported providers:**
- `google` - `[STABLE]` - Fully tested, production-ready
- `github` - `[DRAFT]` - Implemented, needs more testing
- `microsoft` - `[DRAFT]` - Implemented, needs more testing
- `facebook` - `[DRAFT]` - Implemented, needs more testing
- `discord` - `[DRAFT]` - Implemented, needs more testing

---

## OAuth Callback

### GET /auth/oauth/{provider}/callback

Handle OAuth callback.

**Request:**
```
GET /auth/oauth/google/callback?code=xxx&state=abc123xyz
```

**Response (200 OK):**
```json
{
  "access_token": "v4.local.eyJ...",
  "refresh_token": "rt_abc123...",
  "expires_in": 3600,
  "token_type": "Bearer",
  "user": {
    "id": "019ab13e-40f1-7b21-a672-f403d5277ec7",
    "email": "user@gmail.com",
    "name": "John Doe",
    "emailVerified": true
  }
}
```

---

## Error Response Format

All errors follow this format:

```json
{
  "error": "Error message here"
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 500 | Internal Server Error |

---

## Rate Limiting

Default rate limits:
- Login: 5 attempts per minute
- Register: 3 per minute
- Refresh: 10 per minute
- General API: 60 per minute

Response headers:
```
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1705320000
```

---

## Next Steps

- [Events](08-EVENTS.md)
- [Error Handling](10-ERROR-HANDLING.md)
- [Security](11-SECURITY.md)
