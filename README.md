# BetterAuth for Symfony

[![CI](https://github.com/MakFly/betterauth-symfony/actions/workflows/tests.yml/badge.svg)](https://github.com/MakFly/betterauth-symfony/actions)
[![Latest Stable Version](https://img.shields.io/packagist/v/betterauth/symfony-bundle?label=stable)](https://packagist.org/packages/betterauth/symfony-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/betterauth/symfony-bundle?label=downloads)](https://packagist.org/packages/betterauth/symfony-bundle)
[![License](https://img.shields.io/packagist/l/betterauth/symfony-bundle?label=license)](https://github.com/MakFly/betterauth-symfony/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/betterauth/symfony-bundle?label=php)](https://packagist.org/packages/betterauth/symfony-bundle)

Modern, secure authentication for **Symfony 6.4 / 7.x** applications with automatic setup and embedded core.

> **Part of the BetterAuth ecosystem** — a unified authentication library for PHP.
> Laravel support is coming soon. See [betterauth-laravel](https://github.com/MakFly/betterauth-laravel).

## Features

| Category | What you get |
|----------|-------------|
| **Core Auth** | Email/password, Magic Link, TOTP 2FA, Passkeys (draft) |
| **OAuth 2.0** | Google `STABLE`, GitHub, Facebook, Microsoft, Discord `DRAFT` |
| **Enterprise** | SSO/OIDC, Multi-Tenant (orgs, teams, invitations) |
| **Security** | Paseto V4 tokens, Argon2id, refresh token rotation, rate limiting |
| **DX** | Automated installer, UUID v7 or INT IDs, API Platform OpenAPI integration |
| **Symfony** | Security integration, DI, Console commands, Flex auto-config |

## Architecture

This package ships with an **embedded core** (`core-embedded/`) — no separate `betterauth-core` dependency needed.

```
betterauth-symfony/
├── core-embedded/          # Framework-agnostic auth logic
│   ├── core/               # TokenService, PasswordHasher, AuthConfig
│   ├── providers/           # OAuth, MagicLink, TOTP, Passkey, etc.
│   └── contracts/           # AuthUserInterface, PasswordHolderInterface
├── src/                    # Symfony bundle integration
│   ├── Command/            # better-auth:install, setup-features, etc.
│   ├── Controller/         # Ready-to-use auth controllers
│   ├── Installer/          # Entity/Controller/Config generators
│   ├── Security/           # Authenticator, UserProvider, UserAdapter
│   ├── Service/            # Mailer, RateLimiter wrappers
│   └── Storage/Doctrine/   # Repository implementations
└── tests/
```

## Requirements

- PHP 8.2+
- Symfony 6.4 or 7.0+
- Doctrine ORM 3.0+
- API Platform 4.0+ (optional)

## Installation

```bash
composer require betterauth/symfony-bundle

php bin/console better-auth:install
```

The wizard configures everything: entities, controllers, config, migrations, and secrets.

### Non-Interactive

```bash
php bin/console better-auth:install \
  --id-strategy=uuid \
  --mode=api \
  --no-interaction
```

| Option | Description |
|--------|-------------|
| `--id-strategy=uuid\|int` | UUID v7 or auto-increment |
| `--mode=api\|session\|hybrid` | Authentication mode |
| `--exclude-fields=avatar` | Exclude optional User fields |
| `--minimal` | No name/avatar fields |
| `--skip-migrations` | Skip migration generation |
| `--skip-controller` | Skip controller generation |

## Quick Start

```bash
# Register
curl -X POST http://localhost:8000/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"SecurePassword123"}'

# Login
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"SecurePassword123"}'

# Authenticated request
curl http://localhost:8000/auth/me \
  -H "Authorization: Bearer v4.local.eyJ..."

# Refresh token
curl -X POST http://localhost:8000/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refreshToken":"..."}'
```

## Endpoints

| Area | Endpoints |
|------|-----------|
| **Auth** | `POST /auth/register`, `POST /auth/login`, `POST /auth/login/2fa`, `GET /auth/me`, `POST /auth/refresh`, `POST /auth/logout`, `POST /auth/revoke-all` |
| **Sessions** | `GET /auth/sessions`, `DELETE /auth/sessions/{id}` |
| **2FA/TOTP** | `POST /auth/2fa/setup`, `POST /auth/2fa/validate`, `POST /auth/2fa/verify`, `POST /auth/2fa/disable`, `GET /auth/2fa/status`, `POST /auth/2fa/backup-codes/regenerate` |
| **Magic Link** | `POST /auth/magic-link/send`, `POST /auth/magic-link/verify`, `GET /auth/magic-link/verify/{token}` |
| **Email** | `POST /auth/email/send-verification`, `POST /auth/email/verify`, `GET /auth/email/verification-status` |
| **Password** | `POST /auth/password/forgot`, `POST /auth/password/reset`, `POST /auth/password/verify-token` |
| **OAuth** | `GET /auth/oauth/providers`, `GET /auth/oauth/{provider}`, `GET /auth/oauth/{provider}/url`, `GET /auth/oauth/{provider}/callback` |
| **Guest** | `POST /auth/guest/create`, `GET /auth/guest/{token}`, `POST /auth/guest/convert`, `DELETE /auth/guest/{token}` |

## Configuration

```yaml
# config/packages/better_auth.yaml
better_auth:
    mode: 'api'  # api | session | hybrid
    secret: '%env(BETTER_AUTH_SECRET)%'

    token:
        lifetime: 3600            # Access token: 1 hour
        refresh_lifetime: 2592000 # Refresh token: 30 days

    oauth:
        providers:
            google:
                enabled: true
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/google/callback'

    session:
        lifetime: 604800
        cookie_name: 'better_auth_session'

    email_verification:
        enabled: true
        lifetime: 3600

    password_reset:
        enabled: true
        lifetime: 3600

    multi_tenant:
        enabled: false
        default_role: 'member'
```

```env
BETTER_AUTH_SECRET=auto_generated_64_char_secret
APP_URL=http://localhost:8000
MAILER_DSN=smtp://127.0.0.1:1025
```

## Authentication Modes

**API (stateless)** — Paseto V4 tokens via `Authorization: Bearer` header.

**Session (stateful)** — Traditional Symfony sessions with cookies.

**Hybrid** — Both tokens and sessions available simultaneously.

## Console Commands

```bash
bin/console list better-auth
```

### Add Features After Installation

```bash
# Enable magic link + 2FA with auto-generation
php bin/console better-auth:setup-features --enable=magic_link --enable=two_factor --with-controllers --migrate

# Use a preset (minimal, standard, full)
php bin/console better-auth:setup-features --preset=full --with-controllers --migrate

# Preview changes without applying
php bin/console better-auth:setup-features --enable=magic_link --dry-run
```

| Feature | Description |
|---------|-------------|
| `magic_link` | Passwordless login via email |
| `two_factor` | TOTP 2FA (Google Authenticator) |
| `oauth` | Google, GitHub, Facebook, Microsoft, Discord |
| `email_verification` | Email verification flow |
| `password_reset` | Forgot password flow |
| `session_management` | View/revoke sessions |
| `device_tracking` | Track user devices |
| `security_monitoring` | Threat detection |
| `guest_sessions` | Anonymous sessions |
| `multi_tenant` | Organizations & teams |

### Other Commands

```bash
php bin/console better-auth:generate-secret
php bin/console better-auth:add-controller magic-link
php bin/console better-auth:user-fields add name,avatar
php bin/console better-auth:switch-mode api
php bin/console better-auth:configure
php bin/console better-auth:cleanup:sessions
php bin/console better-auth:cleanup:tokens
php bin/console better-auth:publish-templates
```

## Entity Customization

```php
// src/Entity/User.php
namespace App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User extends BaseUser
{
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $phoneNumber = null;

    // getters/setters...
}
```

## OAuth Providers

| Provider | Status |
|----------|--------|
| Google | **Stable** |
| GitHub | Draft |
| Facebook | Draft |
| Microsoft | Draft |
| Discord | Draft |

## Security

- **Paseto V4** — encrypted, authenticated tokens (no JWT algorithm confusion)
- **Argon2id** — memory-hard password hashing
- **Refresh token rotation** — one-time use
- **Rate limiting** — built-in support
- **UUID v7** — non-guessable, time-ordered IDs

## Testing

```bash
composer test       # PHPUnit
composer phpstan    # Static analysis (level 5)
```

CI matrix: PHP 8.2 / 8.3 / 8.4 × Symfony 6.4 / 7.0 / 7.1 / 7.2 / 7.3

## Documentation

Full documentation: [betterauth.dev](https://betterauth.dev)

- [Installation](https://betterauth.dev/docs/getting-started/installation)
- [Configuration](https://betterauth.dev/docs/getting-started/configuration)
- [Authentication Modes](https://betterauth.dev/docs/authentication-modes/api-mode)
- [OAuth Providers](https://betterauth.dev/docs/features/oauth-providers)
- [Two-Factor Auth](https://betterauth.dev/docs/features/two-factor-auth)
- [Security](https://betterauth.dev/docs/advanced/security)

## Ecosystem

| Package | Description | Status |
|---------|-------------|--------|
| [betterauth-symfony](https://github.com/MakFly/betterauth-symfony) | Symfony bundle (this repo) | Active |
| [betterauth-laravel](https://github.com/MakFly/betterauth-laravel) | Laravel package | Coming soon |
| [betterauth-docs](https://github.com/MakFly/betterauth-docs) | Documentation site | Active |

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'feat: add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## Security

If you discover any security-related issues, please create an issue on [GitHub](https://github.com/MakFly/betterauth-symfony/issues) with the `security` label.

## License

MIT License. See [LICENSE](LICENSE) for details.

---

**[Packagist](https://packagist.org/packages/betterauth/symfony-bundle)** · **[Documentation](https://betterauth.dev)** · **[Issues](https://github.com/MakFly/betterauth-symfony/issues)**
