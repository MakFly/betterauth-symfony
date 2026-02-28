# BetterAuth for Symfony

[![CI](https://github.com/MakFly/betterauth-symfony/actions/workflows/tests.yml/badge.svg)](https://github.com/MakFly/betterauth-symfony/actions)
[![Latest Stable Version](https://img.shields.io/packagist/v/betterauth/symfony-bundle?label=stable)](https://packagist.org/packages/betterauth/symfony-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/betterauth/symfony-bundle?label=downloads)](https://packagist.org/packages/betterauth/symfony-bundle)
[![License](https://img.shields.io/packagist/l/betterauth/symfony-bundle?label=license)](https://github.com/MakFly/betterauth-symfony/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/betterauth/symfony-bundle?label=php)](https://packagist.org/packages/betterauth/symfony-bundle)

Modern, secure authentication for **Symfony 6.4 / 7.x / 8.0** applications with automatic setup and embedded core.

> **Part of the BetterAuth ecosystem** — a unified authentication library for PHP.
> Also available: [BetterAuth for Laravel](https://github.com/MakFly/betterauth-laravel)

---

## BetterAuth vs LexikJWT

[LexikJWTAuthenticationBundle](https://github.com/lexik/LexikJWTAuthenticationBundle) is the most popular JWT bundle for Symfony. Here's an honest comparison:

### Quick Comparison

| Feature | BetterAuth | LexikJWT |
|---------|------------|----------|
| **Token Type** | Paseto V4 (encrypted) | JWT (signed only) |
| **Setup** | 1 command (`better-auth:install`) | Manual configuration |
| **Refresh Tokens** | Built-in with rotation | Requires extra bundle |
| **Registration** | Built-in | Manual implementation |
| **OAuth 2.0** | 5 providers ready | Manual integration |
| **2FA / TOTP** | Built-in | External packages |
| **Magic Link** | Built-in | Manual implementation |
| **Email Verification** | Built-in | Manual implementation |
| **Password Reset** | Built-in | Manual implementation |
| **Session Management** | Built-in | Not supported |
| **Multi-tenant** | Organizations & teams | Not supported |
| **Rate Limiting** | Built-in | Manual implementation |
| **Entity Generation** | Automatic | Manual |
| **Controller Generation** | Automatic | Manual |
| **Symfony Versions** | 6.4, 7.x, 8.0 | 5.4, 6.x, 7.x |
| **PHP Versions** | 8.2+ | 7.4+ |

### Token Security: Paseto vs JWT

| Aspect | Paseto V4 (BetterAuth) | JWT (LexikJWT) |
|--------|------------------------|----------------|
| **Encryption** | XChaCha20-Poly1305 | None (plaintext payload) |
| **Authentication** | Ed25519 signatures | HMAC or RSA |
| **Payload Visibility** | Encrypted, not readable | Base64, anyone can read |
| **Algorithm Confusion** | Not possible | Known vulnerability class |
| **Key Compromise Impact** | Encrypted payload protected | All tokens readable |

**JWT payloads are base64-encoded, not encrypted.** Anyone with the token can read user IDs, emails, roles, etc.:

```bash
# JWT payload is readable without the secret
echo "eyJzdWIiOiJ1c2VyLTEyMyIsInJvbGUiOiJhZG1pbiJ9" | base64 -d
# Output: {"sub":"user-123","role":"admin"}
```

**Paseto V4 encrypts the payload.** Even with the token, no one can read the contents without the secret key.

### Feature Comparison Details

#### What you get with BetterAuth

```
✅ Registration endpoint
✅ Login endpoint
✅ Token refresh with rotation
✅ Logout (single + all sessions)
✅ Password reset flow
✅ Email verification flow
✅ OAuth 2.0 (Google, GitHub, etc.)
✅ TOTP 2FA with backup codes
✅ Magic link authentication
✅ Session management
✅ Device tracking
✅ Rate limiting
✅ Multi-tenant organizations
```

#### What you need to build with LexikJWT

```
❌ Registration endpoint → Build yourself
❌ Token refresh → Install gesdinet/jwt-refresh-token-bundle
❌ Logout → Build yourself (JWT cannot be revoked)
❌ Password reset → Build yourself
❌ Email verification → Build yourself
❌ OAuth 2.0 → Install knpuniversity/oauth2-client-bundle + configure
❌ 2FA → Install scheb/2fa-bundle + configure
❌ Magic link → Build yourself
❌ Session management → Build yourself
❌ Device tracking → Build yourself
❌ Rate limiting → Install symfony/rate-limiter + configure
❌ Multi-tenant → Build yourself
```

### Setup Comparison

#### BetterAuth (2 minutes)

```bash
composer require betterauth/symfony-bundle
php bin/console better-auth:install
# Done! All entities, controllers, config generated.
```

#### LexikJWT (30+ minutes)

```bash
composer require lexik/jwt-authentication-bundle
# Generate SSH keys
mkdir -p config/jwt
openssl genpkey -out config/jwt/private.pem -algorithm RSA -pkeyopt rsa_keygen_bits:4096
openssl pkey -in config/jwt/private.pem -out config/jwt/public.pem -pubout

# Configure security.yaml
# Configure lexik_jwt_authentication.yaml
# Create User entity
# Create registration controller
# Create login controller
# Install refresh token bundle
# Configure refresh tokens
# Build password reset
# Build email verification
# ...and more
```

### When to Choose LexikJWT

LexikJWT is a great choice if you:
- Need **maximum simplicity** for basic API auth
- Want **JWT interoperability** with other services
- Already have user management built
- Only need **token generation**, not a full auth system
- Prefer **minimal dependencies**

### When to Choose BetterAuth

BetterAuth is a better choice if you:
- Want **encrypted tokens** (sensitive data in payload)
- Need **OAuth 2.0** social login
- Need **2FA / TOTP** authentication
- Want **magic link** passwordless login
- Need **session management** with device tracking
- Want **multi-tenant** organizations
- Prefer **automated setup** over manual configuration
- Need **password reset** and **email verification** flows
- Want **everything in one package**

### Migration from LexikJWT

If you're already using LexikJWT and want to migrate:

1. Install BetterAuth alongside LexikJWT
2. Run `php bin/console better-auth:install` (use existing User entity)
3. Update your frontend to use new endpoints
4. Remove LexikJWT once migration is complete

Both can coexist during migration.

---

## Features

### Core Authentication
- Email/password registration & login
- Paseto V4 encrypted tokens (not JWT)
- Refresh token rotation (one-time use)
- Session management with device tracking

### Passwordless
- Magic Link authentication
- Passkeys / WebAuthn (draft)

### Two-Factor Authentication
- TOTP (Google Authenticator, Authy, etc.)
- Backup codes with regeneration
- 2FA status endpoints

### OAuth 2.0 Providers
| Provider | Status |
|----------|--------|
| Google | **Stable** |
| GitHub | Draft |
| Facebook | Draft |
| Microsoft | Draft |
| Discord | Draft |

### Enterprise Features
- Email verification flow
- Password reset flow
- Guest sessions (anonymous users)
- Multi-tenant organizations & teams
- Security monitoring & threat detection
- Rate limiting built-in

### Developer Experience
- Automated installer with wizard
- UUID v7 or auto-increment IDs
- API Platform OpenAPI integration
- Symfony Security integration
- Comprehensive console commands

---

## Requirements

| Requirement | Version |
|-------------|---------|
| PHP | ^8.2 |
| Symfony | ^6.4 \| ^7.0 \| ^8.0 |
| Doctrine ORM | ^3.0 |
| API Platform | ^4.0 (optional) |

**Tested on:** PHP 8.2/8.3/8.4 × Symfony 6.4/7.0/7.1/7.2/7.3/8.0

---

## Installation

### Step 1: Install the bundle

```bash
composer require betterauth/symfony-bundle
```

### Step 2: Run the installer

```bash
php bin/console better-auth:install
```

The interactive wizard will:
1. Generate User, Session, and RefreshToken entities
2. Create AuthController with all endpoints
3. Set up configuration files
4. Generate Doctrine migrations
5. Create a secure secret key

### Non-Interactive Installation

```bash
php bin/console better-auth:install \
  --id-strategy=uuid \
  --mode=api \
  --no-interaction
```

| Option | Values | Description |
|--------|--------|-------------|
| `--id-strategy` | `uuid`, `int` | UUID v7 (default) or auto-increment |
| `--mode` | `api`, `session`, `hybrid` | Authentication mode |
| `--exclude-fields` | `avatar`, `name` | Exclude optional User fields |
| `--minimal` | - | Skip name/avatar fields |
| `--skip-migrations` | - | Don't generate migrations |
| `--skip-controller` | - | Don't generate controller |

---

## Quick Start

### Register a user

```bash
curl -X POST http://localhost:8000/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"SecurePassword123"}'
```

### Login

```bash
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"SecurePassword123"}'

# Response:
{
  "access_token": "v4.local.eyJ...",
  "refresh_token": "v4.local.eyJ...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

### Authenticated request

```bash
curl http://localhost:8000/auth/me \
  -H "Authorization: Bearer v4.local.eyJ..."
```

### Refresh token

```bash
curl -X POST http://localhost:8000/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refreshToken":"v4.local.eyJ..."}'
```

---

## API Endpoints

### Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/auth/register` | Create new user |
| `POST` | `/auth/login` | Authenticate user |
| `POST` | `/auth/login/2fa` | Complete 2FA login |
| `GET` | `/auth/me` | Get current user |
| `POST` | `/auth/refresh` | Refresh access token |
| `POST` | `/auth/logout` | Logout current session |
| `POST` | `/auth/revoke-all` | Revoke all tokens |

### Sessions

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/auth/sessions` | List active sessions |
| `DELETE` | `/auth/sessions/{id}` | Revoke specific session |

### Two-Factor Auth

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/auth/2fa/setup` | Initialize 2FA setup |
| `POST` | `/auth/2fa/validate` | Validate setup code |
| `POST` | `/auth/2fa/verify` | Verify 2FA code |
| `POST` | `/auth/2fa/disable` | Disable 2FA |
| `GET` | `/auth/2fa/status` | Get 2FA status |
| `POST` | `/auth/2fa/backup-codes/regenerate` | Regenerate backup codes |

### Magic Link

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/auth/magic-link/send` | Send magic link email |
| `POST` | `/auth/magic-link/verify` | Verify magic link token |
| `GET` | `/auth/magic-link/verify/{token}` | Verify via GET |

### Email Verification

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/auth/email/send-verification` | Send verification email |
| `POST` | `/auth/email/verify` | Verify email with token |
| `GET` | `/auth/email/verification-status` | Check verification status |

### Password Reset

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/auth/password/forgot` | Request password reset |
| `POST` | `/auth/password/reset` | Reset with token |
| `POST` | `/auth/password/verify-token` | Validate reset token |

### OAuth

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/auth/oauth/providers` | List enabled providers |
| `GET` | `/auth/oauth/{provider}` | Redirect to OAuth provider |
| `GET` | `/auth/oauth/{provider}/url` | Get OAuth URL (for SPAs) |
| `GET` | `/auth/oauth/{provider}/callback` | OAuth callback handler |

### Guest Sessions

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/auth/guest/create` | Create guest session |
| `GET` | `/auth/guest/{token}` | Get guest session |
| `POST` | `/auth/guest/convert` | Convert to registered user |
| `DELETE` | `/auth/guest/{token}` | Delete guest session |

---

## Configuration

### config/packages/better_auth.yaml

```yaml
better_auth:
    # Authentication mode: api, session, or hybrid
    mode: 'api'

    # Secret key for token encryption (auto-generated)
    secret: '%env(BETTER_AUTH_SECRET)%'

    # Token configuration
    token:
        lifetime: 3600              # Access token: 1 hour
        refresh_lifetime: 2592000   # Refresh token: 30 days

    # OAuth providers
    oauth:
        providers:
            google:
                enabled: true
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/google/callback'

    # Session settings (for session/hybrid mode)
    session:
        lifetime: 604800
        cookie_name: 'better_auth_session'

    # Email verification
    email_verification:
        enabled: true
        lifetime: 3600

    # Password reset
    password_reset:
        enabled: true
        lifetime: 3600

    # Multi-tenant (organizations)
    multi_tenant:
        enabled: false
        default_role: 'member'
```

### Environment Variables

```env
# Required
BETTER_AUTH_SECRET=your-64-character-secret-key-here
APP_URL=http://localhost:8000

# Email
MAILER_DSN=smtp://127.0.0.1:1025

# OAuth (optional)
GOOGLE_CLIENT_ID=your-google-client-id
GOOGLE_CLIENT_SECRET=your-google-client-secret
```

---

## Authentication Modes

### API Mode (Stateless)
- Paseto V4 tokens via `Authorization: Bearer` header
- Perfect for SPAs, mobile apps, APIs
- No server-side session storage

### Session Mode (Stateful)
- Traditional Symfony sessions with cookies
- Best for server-rendered applications
- Uses database for session storage

### Hybrid Mode
- Both tokens and sessions available
- Useful for apps with mixed requirements

---

## Console Commands

### List all commands

```bash
bin/console list better-auth
```

### Setup features after installation

```bash
# Enable specific features
php bin/console better-auth:setup-features \
  --enable=magic_link \
  --enable=two_factor \
  --with-controllers \
  --migrate

# Use a preset
php bin/console better-auth:setup-features \
  --preset=full \
  --with-controllers \
  --migrate

# Preview changes
php bin/console better-auth:setup-features \
  --enable=magic_link \
  --dry-run
```

| Preset | Features |
|--------|----------|
| `minimal` | Basic auth only |
| `standard` | Auth + email verification + password reset |
| `full` | All features enabled |

| Feature | Description |
|---------|-------------|
| `magic_link` | Passwordless login via email |
| `two_factor` | TOTP 2FA (Google Authenticator) |
| `oauth` | Social login providers |
| `email_verification` | Email verification flow |
| `password_reset` | Forgot password flow |
| `session_management` | View/revoke sessions |
| `device_tracking` | Track user devices |
| `security_monitoring` | Threat detection |
| `guest_sessions` | Anonymous sessions |
| `multi_tenant` | Organizations & teams |

### Other commands

```bash
# Generate a new secret key
php bin/console better-auth:generate-secret

# Add a specific controller
php bin/console better-auth:add-controller magic-link

# Manage User entity fields
php bin/console better-auth:user-fields add name,avatar
php bin/console better-auth:user-fields remove avatar

# Switch authentication mode
php bin/console better-auth:switch-mode api

# Reconfigure bundle
php bin/console better-auth:configure

# Cleanup old sessions/tokens
php bin/console better-auth:cleanup:sessions
php bin/console better-auth:cleanup:tokens

# Publish email templates
php bin/console better-auth:publish-templates
```

---

## Entity Customization

Extend the base User entity to add custom fields:

```php
<?php

namespace App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User extends BaseUser
{
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferences = null;

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;
        return $this;
    }

    public function getPreferences(): ?array
    {
        return $this->preferences;
    }

    public function setPreferences(?array $preferences): static
    {
        $this->preferences = $preferences;
        return $this;
    }
}
```

---

## Security

### Token Security
- **Paseto V4** — encrypted and authenticated tokens
- **No algorithm confusion** — unlike JWT
- **XChaCha20-Poly1305** encryption
- **Ed25519** signatures

### Password Security
- **Argon2id** — memory-hard password hashing
- Configurable memory, time, and threads

### Token Lifecycle
- **Refresh token rotation** — one-time use tokens
- **Automatic cleanup** — expired tokens removed
- **Revocation support** — revoke all tokens on demand

### Additional Security
- **UUID v7** — non-guessable, time-ordered IDs
- **Rate limiting** — built-in protection against brute force
- **CSRF protection** — for session-based auth

---

## Testing

```bash
# Run tests
composer test

# Static analysis
composer phpstan
```

### CI Matrix

| PHP | Symfony Versions |
|-----|------------------|
| 8.2 | 6.4, 7.0, 7.1, 7.2 |
| 8.3 | 6.4, 7.0, 7.1, 7.2, 7.3 |
| 8.4 | 6.4, 7.0, 7.1, 7.2, 7.3, 8.0 |

---

## Documentation

Full documentation available at [betterauth.dev](https://betterauth.dev)

- [Installation Guide](https://betterauth.dev/docs/getting-started/installation)
- [Configuration Reference](https://betterauth.dev/docs/getting-started/configuration)
- [API Mode](https://betterauth.dev/docs/authentication-modes/api-mode)
- [Session Mode](https://betterauth.dev/docs/authentication-modes/session-mode)
- [OAuth Providers](https://betterauth.dev/docs/features/oauth-providers)
- [Two-Factor Authentication](https://betterauth.dev/docs/features/two-factor-auth)
- [Magic Link](https://betterauth.dev/docs/features/magic-link)
- [Multi-Tenant](https://betterauth.dev/docs/features/multi-tenant)
- [Security Best Practices](https://betterauth.dev/docs/advanced/security)

---

## Contributing

We welcome contributions! Please follow these steps:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Make your changes with tests
4. Ensure tests pass (`composer test`)
5. Run static analysis (`composer phpstan`)
6. Commit with conventional commits (`feat: add amazing feature`)
7. Push to your branch (`git push origin feature/amazing-feature`)
8. Open a Pull Request

### Development Setup

```bash
git clone https://github.com/MakFly/betterauth-symfony.git
cd betterauth-symfony
composer install
composer test
```

---

## Security Issues

If you discover a security vulnerability, please **do not** open a public issue.

Instead, create a private security advisory on [GitHub](https://github.com/MakFly/betterauth-symfony/security/advisories) or email security@betterauth.dev.

---

## License

MIT License. See [LICENSE](LICENSE) for details.

---

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for version history.

---

**[Packagist](https://packagist.org/packages/betterauth/symfony-bundle)** · **[Documentation](https://betterauth.dev)** · **[GitHub](https://github.com/MakFly/betterauth-symfony)** · **[Issues](https://github.com/MakFly/betterauth-symfony/issues)**
