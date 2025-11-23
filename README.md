# BetterAuth Bundle for Symfony

[![CI](https://github.com/MakFly/betterauth-symfony/actions/workflows/tests.yml/badge.svg?label=CI)](https://github.com/MakFly/betterauth-symfony/actions)
[![Latest Stable Version](https://img.shields.io/packagist/v/betterauth/symfony-bundle?label=stable)](https://packagist.org/packages/betterauth/symfony-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/betterauth/symfony-bundle?label=downloads)](https://packagist.org/packages/betterauth/symfony-bundle)
[![License](https://img.shields.io/packagist/l/betterauth/symfony-bundle?label=license)](https://github.com/MakFly/betterauth-symfony/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/betterauth/symfony-bundle?label=php)](https://packagist.org/packages/betterauth/symfony-bundle)

Modern, secure authentication for Symfony 6/7 applications with automatic setup.

## ✨ Features

- 🔐 **Email/Password authentication**
- 🔗 **Magic Link** passwordless auth
- 🌍 **OAuth 2.0**: Google, GitHub, Facebook, Apple, Discord, Microsoft, Twitter/X
- 🔑 **Passkeys/WebAuthn**
- 📱 **TOTP** (Two-Factor Authentication)
- 🔄 **SSO/OIDC Provider**
- 👥 **Multi-Tenant**: Organizations, Teams, Members, Invitations
- ⚡ **Fully automated installation** with `better-auth:install`
- 🆔 **UUID v7 or INT ID strategies**
- 🎯 **Symfony Flex auto-configuration**
- 🔌 **DependencyInjection integration**
- 💻 **Console commands**
- 📚 **API Platform OpenAPI integration**
- 🔒 **Symfony Security integration**

## 📦 Installation

### Automatic Installation (Recommended)

```bash
# 1. Install the bundle
composer require betterauth/symfony-bundle

# 2. Run the installation wizard
php bin/console better-auth:install
```

The installation wizard will:
- ✅ Ask for your preferred ID strategy (UUID v7 or INT)
- ✅ Ask for authentication mode (api, session, or hybrid)
- ✅ Ask for OAuth providers (Google, GitHub, etc.)
- ✅ Generate User, Session, and RefreshToken entities
- ✅ Generate AuthController with 8 endpoints
- ✅ Create configuration file (`config/packages/better_auth.yaml`)
- ✅ Generate and run database migrations
- ✅ Update .env with secrets
- ✅ Everything ready to use!

### Non-Interactive Installation

For CI/CD or automated setups:

```bash
php bin/console better-auth:install \
  --id-strategy=uuid \
  --mode=api \
  --no-interaction
```

**Options:**
- `--id-strategy=uuid|int` - Choose UUID v7 or INT IDs
- `--mode=api|session|hybrid` - Authentication mode
- `--skip-migrations` - Skip migration generation
- `--skip-controller` - Skip controller generation
- `--no-interaction` - Run without prompts

## 🚀 Quick Start

After installation, start your development server:

```bash
# Start Symfony server
symfony server:start

# Or PHP built-in server
php -S localhost:8000 -t public
```

Test the endpoints:

```bash
# Register a user
curl -X POST http://localhost:8000/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"SecurePassword123","name":"John Doe"}'

# Response (UUID v7 example):
# {
#   "user": {
#     "id": "019ab13e-40f1-7b21-a672-f403d5277ec7",
#     "email": "user@example.com",
#     "name": "John Doe",
#     "emailVerified": false
#   }
# }

# Login
curl -X POST http://localhost:8000/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"SecurePassword123"}'

# Response:
# {
#   "access_token": "v4.local.eyJ...",  # Paseto V4 token
#   "refresh_token": "...",
#   "expires_in": 7200,
#   "token_type": "Bearer",
#   "user": { ... }
# }

# Get current user
curl -X GET http://localhost:8000/auth/me \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"

# Refresh token
curl -X POST http://localhost:8000/auth/refresh \
  -H "Content-Type: application/json" \
  -d '{"refreshToken":"YOUR_REFRESH_TOKEN"}'
```

## 🛣️ Generated Endpoints

The `better-auth:install` command generates an `AuthController` with **8 endpoints**:

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register new user |
| POST | `/auth/login` | Authenticate user (returns tokens) |
| GET | `/auth/me` | Get current authenticated user |
| POST | `/auth/refresh` | Refresh access token |
| POST | `/auth/logout` | Logout current user |
| POST | `/auth/revoke-all` | Revoke all refresh tokens |
| GET | `/auth/oauth/{provider}` | Get OAuth authorization URL |
| GET | `/auth/oauth/{provider}/callback` | OAuth callback handler |

## ⚙️ Configuration

Generated configuration file (`config/packages/better_auth.yaml`):

```yaml
better_auth:
    mode: 'api'  # or 'session' or 'hybrid'
    secret: '%env(BETTER_AUTH_SECRET)%'

    token:
        lifetime: 7200           # Access token: 2 hours
        refresh_lifetime: 2592000  # Refresh token: 30 days

    oauth:
        providers:
            google:
                enabled: true
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/google/callback'

            github:
                enabled: true
                client_id: '%env(GITHUB_CLIENT_ID)%'
                client_secret: '%env(GITHUB_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/github/callback'

    multi_tenant:
        enabled: false
        default_role: 'member'
```

Environment variables (`.env`):

```env
# Auto-generated by better-auth:install
BETTER_AUTH_SECRET=auto_generated_64_char_secret
APP_URL=http://localhost:8000

# OAuth providers (if enabled)
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
```

## 🆔 UUID v7 vs INT ID Strategy

### UUID v7 (Recommended)

**Time-ordered UUIDs for better performance:**

```php
// Example UUID v7
$user->id; // "019ab13e-40f1-7b21-a672-f403d5277ec7"
```

**Advantages:**
- ✅ Chronologically sortable
- ✅ Non-guessable (secure)
- ✅ No index fragmentation (fast DB queries)
- ✅ Compatible with distributed systems

### INT (Classic)

**Auto-increment integers:**

```php
$user->id; // 1, 2, 3, ...
```

**Advantages:**
- ✅ Smaller storage size (4-8 bytes vs 36 bytes)
- ✅ Human-readable
- ✅ Standard Symfony approach

**Choose during installation or use `--id-strategy=uuid|int`**

## 🔑 Authentication Modes

### API Mode (Stateless)

Use Paseto V4 tokens for stateless authentication:

```yaml
better_auth:
    mode: 'api'
```

**Flow:**
1. Login → Receive access token + refresh token
2. Include `Authorization: Bearer <accessToken>` in requests
3. Refresh when expired

### Session Mode (Stateful)

Use traditional Symfony sessions:

```yaml
better_auth:
    mode: 'session'
    session:
        lifetime: 604800  # 7 days
        cookie_name: 'better_auth_session'
```

### Hybrid Mode

Both tokens and sessions available:

```yaml
better_auth:
    mode: 'hybrid'
```

## 📚 API Platform Integration

**Automatic OpenAPI Documentation** for all authentication endpoints!

When using **API Platform**, the bundle automatically adds all authentication endpoints to your OpenAPI/Swagger documentation.

### View Documentation

```bash
# Swagger UI
open http://localhost:8000/api/docs

# Export OpenAPI spec
bin/console api:openapi:export
```

You'll see all BetterAuth endpoints documented under the "Authentication" tag with:
- Bearer token authentication scheme (Paseto V4)
- Complete request/response schemas
- OAuth provider documentation

## 💻 Console Commands

```bash
# Install/setup BetterAuth
bin/console better-auth:install

# List available commands
bin/console list better-auth
```

## 🎨 Customizing Entities

After running `better-auth:install`, you can customize the generated entities:

```php
<?php
// src/Entity/User.php

namespace App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User extends BaseUser
{
    // Add custom fields
    #[ORM\Column(type: 'string', nullable: true)]
    private ?string $phoneNumber = null;

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): void
    {
        $this->phoneNumber = $phoneNumber;
    }
}
```

Then generate a migration:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## 🌍 OAuth Providers

Enable OAuth providers during installation or manually:

```yaml
better_auth:
    oauth:
        providers:
            google:
                enabled: true
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/google/callback'

            github:
                enabled: true
                client_id: '%env(GITHUB_CLIENT_ID)%'
                client_secret: '%env(GITHUB_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/github/callback'

            # Also supported: facebook, apple, discord, microsoft, twitter
```

## 👥 Multi-Tenancy

Enable organizations, teams, and member management:

```yaml
better_auth:
    multi_tenant:
        enabled: true
        default_role: 'member'
```

## 🔒 Security

- 🔐 **Paseto V4** tokens (encrypted, authenticated)
- 🔑 **Argon2id** password hashing (memory-hard, GPU-resistant)
- 🔄 **Refresh token rotation** (one-time use)
- ⚡ **Rate limiting** support
- 🛡️ **CSRF protection** (session mode)
- 🆔 **UUID v7** IDs (non-guessable)

## 🧪 Testing

```bash
# Run tests
composer test

# Run tests for specific Symfony version
composer require "symfony/framework-bundle:7.0.*" --no-update
composer test
```

## 🔧 Requirements

- PHP 8.2 or higher
- Symfony 6.4 or 7.0+
- Doctrine ORM 3.0+
- API Platform 4.0+ (optional, for OpenAPI integration)

## 📊 CI/CD

BetterAuth Symfony bundle includes comprehensive CI/CD with GitHub Actions:

- ✅ PHPUnit tests (14 combinations: PHP 8.2/8.3/8.4 × Symfony 6.4/7.0/7.1/7.2/7.3)
- ✅ PHPStan static analysis
- ✅ Security checks (Composer audit + Symfony security checker)
- ✅ Code quality checks
- ✅ Integration tests

All tests run on every push and pull request. View the [latest CI results](https://github.com/MakFly/betterauth-symfony/actions).

## 🤝 Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add some amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## 🔒 Security

If you discover any security-related issues, please create an issue on [GitHub](https://github.com/MakFly/betterauth-symfony/issues) with the `security` label.

## 📄 License

The MIT License (MIT). Please see [LICENSE](LICENSE) file for details.

## 🔗 Links

- **Packagist**: https://packagist.org/packages/betterauth/symfony-bundle
- **GitHub**: https://github.com/MakFly/betterauth-symfony
- **Issues**: https://github.com/MakFly/betterauth-symfony/issues
- **Core Package**: https://github.com/MakFly/betterauth-core
- **Laravel Package**: https://github.com/MakFly/betterauth-laravel

## 🙏 Credits

- [BackToTheFutur Team](https://github.com/MakFly/betterauth-symfony/contributors)
- All the amazing people who contribute to open source

---

Made with ❤️ by the BackToTheFutur Team
