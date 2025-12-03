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
- 🌍 **OAuth 2.0**: Google `[STABLE]`, GitHub, Facebook, Microsoft, Discord `[DRAFT]`
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
- ✅ Génère les contrôleurs d’auth prêts à l’emploi (register/login/refresh/2FA/etc.)
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
- `--exclude-fields=username,avatar` - Exclude optional User fields
- `--minimal` - Generate minimal User entity (no name, avatar)
- `--skip-migrations` - Skip migration generation
- `--skip-controller` - Skip controller generation
- `--no-interaction` - Run without prompts

### Minimal Installation (without profile fields)

If you don't need `username` and `avatar` fields:

```bash
php bin/console better-auth:install \
  --id-strategy=uuid \
  --mode=api \
  --minimal
```

Or exclude specific fields:

```bash
php bin/console better-auth:install \
  --id-strategy=uuid \
  --mode=api \
  --exclude-fields=avatar  # Keep username, exclude avatar
```

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
  -d '{"email":"user@example.com","password":"SecurePassword123","username":"John Doe"}'

# Response (UUID v7 example):
# {
#   "user": {
#     "id": "019ab13e-40f1-7b21-a672-f403d5277ec7",
#     "email": "user@example.com",
#     "username": "John Doe",
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
#   "expires_in": 3600,
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

## 🛣️ Endpoints prêts à l’emploi

Les contrôleurs livrés par le bundle exposent les routes suivantes :

| Zone | Endpoints principaux |
|------|----------------------|
| Auth de base | `POST /auth/register`, `POST /auth/login`, `POST /auth/login/2fa`, `GET /auth/me`, `POST /auth/refresh`, `POST /auth/logout`, `POST /auth/revoke-all` |
| Sessions | `GET /auth/sessions`, `DELETE /auth/sessions/{id}` |
| 2FA/TOTP | `POST /auth/2fa/setup`, `POST /auth/2fa/validate`, `POST /auth/2fa/verify`, `POST /auth/2fa/disable`, `GET /auth/2fa/status`, `POST /auth/2fa/reset`, `POST /auth/2fa/backup-codes/regenerate` |
| Magic link | `POST /auth/magic-link/send`, `POST /auth/magic-link/verify`, `GET /auth/magic-link/verify/{token}` |
| Email | `POST /auth/email/send-verification`, `POST /auth/email/verify`, `GET /auth/email/verification-status` |
| Mot de passe | `POST /auth/password/forgot`, `POST /auth/password/reset`, `POST /auth/password/verify-token` |
| OAuth | `GET /auth/oauth/providers`, `GET /auth/oauth/{provider}`, `GET /auth/oauth/{provider}/url`, `GET /auth/oauth/{provider}/callback` |
| Invité | `POST /auth/guest/create`, `GET /auth/guest/{token}`, `POST /auth/guest/convert`, `DELETE /auth/guest/{token}` |

## ⚙️ Configuration

Generated configuration file (`config/packages/better_auth.yaml`):

```yaml
better_auth:
    mode: 'api'  # or 'session' or 'hybrid'
    secret: '%env(BETTER_AUTH_SECRET)%'

    token:
        lifetime: 3600           # Access token: 1 hour (default)
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

# Mailer configuration
MAILER_DSN=smtp://127.0.0.1:1025
MAILER_FROM_EMAIL=noreply@betterauth.local
MAILER_FROM_NAME=BetterAuth

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
# List all BetterAuth commands
bin/console list better-auth
```

### 🔧 Setup Features (Add Magic Link, 2FA, OAuth, etc.)

**After initial installation**, use `better-auth:setup-features` to add new features with automatic entity generation and migrations.

**Key behavior:**
- Only generates **additional** controllers for new features (not core auth controllers)
- **Asks for confirmation** before generating each controller
- **Detects existing controllers** in both `src/Controller/` and legacy `src/Controller/Api/`
- Skips controllers that already exist (unless `--force` is used)

```bash
# 🚀 Enable Magic Link with auto-generation (recommended)
php bin/console better-auth:setup-features --enable=magic_link --with-controllers --migrate

# Enable multiple features at once
php bin/console better-auth:setup-features --enable=magic_link --enable=two_factor --migrate

# Enable OAuth with 2FA
php bin/console better-auth:setup-features --enable=oauth --enable=two_factor --with-controllers --migrate

# Use a preset (minimal, standard, full)
php bin/console better-auth:setup-features --preset=full --with-controllers --migrate

# Interactive mode (guided wizard)
php bin/console better-auth:setup-features

# List all features and their status
php bin/console better-auth:setup-features --list

# Preview changes without applying (dry-run)
php bin/console better-auth:setup-features --enable=magic_link --dry-run
```

**Available Features:**

| Feature | Description | Entities Generated |
|---------|-------------|-------------------|
| `magic_link` | Passwordless login via email | `MagicLinkToken` |
| `two_factor` | TOTP 2FA (Google Authenticator) | `TotpData` |
| `oauth` | Google, GitHub, Facebook, etc. | - |
| `email_verification` | Verify user emails | `EmailVerificationToken` |
| `password_reset` | Forgot password flow | `PasswordResetToken` |
| `session_management` | View/revoke sessions | - |
| `device_tracking` | Track user devices | `Device` |
| `security_monitoring` | Threat detection | `SecurityEvent` |
| `guest_sessions` | Anonymous sessions | `GuestSession` |
| `passkeys` | WebAuthn biometrics | `Passkey` |
| `multi_tenant` | Organizations & teams | `Organization`, `OrganizationMember` |

**Options:**

| Option | Description |
|--------|-------------|
| `--enable=FEATURE` | Enable a feature (can repeat) |
| `--disable=FEATURE` | Disable a feature |
| `--preset=PRESET` | Use preset: `minimal`, `standard`, `full` |
| `--with-controllers` | Generate required controllers |
| `--with-migrations` | Run `doctrine:migrations:diff` |
| `--migrate` | Run diff AND migrate |
| `--force` | Overwrite existing files |
| `--dry-run` | Preview changes |

### 🎮 Add Controllers

Add individual controllers after installation:

```bash
# List available controllers
php bin/console better-auth:add-controller --list

# Add specific controller
php bin/console better-auth:add-controller magic-link
php bin/console better-auth:add-controller oauth

# Add all controllers
php bin/console better-auth:add-controller --all
```

**Structure des contrôleurs fournis :**
```
src/Controller/
├── Trait/
│   └── AuthResponseTrait.php      # Helpers de réponse JSON
├── CredentialsController.php     # register/login/login 2FA
├── TokenController.php           # me/refresh/logout/revoke-all
├── TwoFactorController.php       # setup/validate/verify/disable/backup codes
├── PasswordResetController.php   # forgot/reset/verify-token
├── EmailVerificationController.php # send/verify/status
├── MagicLinkController.php       # passwordless
├── SessionController.php         # list/revoke sessions
├── GuestSessionController.php    # sessions invitées
└── OAuthController.php           # providers/oauth callback
```

### 👤 Managing User Fields

After installation, you can add or remove optional fields (`username`, `avatar`):

```bash
# Add fields
php bin/console better-auth:user-fields add name
php bin/console better-auth:user-fields add name,avatar

# Remove fields (WARNING: data loss after migration!)
php bin/console better-auth:user-fields remove avatar
php bin/console better-auth:user-fields remove name,avatar --force

# After modifying fields, generate and run migration
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### 🛠️ Other Commands

```bash
# Generate a secure secret for BETTER_AUTH_SECRET
php bin/console better-auth:generate-secret

# Nettoyer les données expirées
php bin/console better-auth:cleanup:sessions --dry-run
php bin/console better-auth:cleanup:tokens

# Switch authentication mode
php bin/console better-auth:switch-mode api
php bin/console better-auth:switch-mode session
php bin/console better-auth:switch-mode hybrid

# Publier les templates email
php bin/console better-auth:publish-templates --force

# Gérer les champs optionnels User
php bin/console better-auth:user-fields add name,avatar

# Interactive configuration wizard
php bin/console better-auth:configure

# Generate config with presets
php bin/console better-auth:generate-config --preset=standard

# Clean up expired sessions/tokens
php bin/console better-auth:cleanup:sessions
php bin/console better-auth:cleanup:tokens
```

## 🔮 Real-World Examples

### Add Magic Link Authentication (after initial install)

```bash
# 1. Enable magic link with everything auto-configured
php bin/console better-auth:setup-features --enable=magic_link --with-controllers --migrate

# This will:
# ✅ Generate src/Entity/MagicLinkToken.php
# ✅ Generate src/Controller/MagicLinkController.php (if you confirm)
# ✅ Update config/packages/better_auth.yaml
# ✅ Run doctrine:migrations:diff
# ✅ Run doctrine:migrations:migrate
```

**Test Magic Link endpoints:**

```bash
# Request magic link
curl -X POST http://localhost:8000/auth/magic-link/request \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com"}'

# Verify magic link token
curl -X POST http://localhost:8000/auth/magic-link/verify \
  -H "Content-Type: application/json" \
  -d '{"token":"received_token_from_email"}'
```

### Add Two-Factor Authentication

```bash
php bin/console better-auth:setup-features --enable=two_factor --migrate
```

**Test 2FA endpoints:**

```bash
# Setup 2FA (get QR code)
curl -X POST http://localhost:8000/auth/2fa/setup \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN"

# Verify 2FA setup
curl -X POST http://localhost:8000/auth/2fa/verify \
  -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"code":"123456"}'

# Login with 2FA
curl -X POST http://localhost:8000/auth/login/2fa \
  -H "Content-Type: application/json" \
  -d '{"email":"user@example.com","password":"pass","totpCode":"123456"}'
```

### Add Multi-Tenant (Organizations)

```bash
php bin/console better-auth:setup-features --enable=multi_tenant --with-controllers --migrate

# This generates:
# - src/Entity/Organization.php
# - src/Entity/OrganizationMember.php
# - src/Controller/OrganizationsController.php (if you confirm)
```

### Full Enterprise Setup

```bash
# Enable all enterprise features at once
php bin/console better-auth:setup-features \
  --preset=full \
  --with-controllers \
  --migrate

# Or cherry-pick specific features
php bin/console better-auth:setup-features \
  --enable=magic_link \
  --enable=two_factor \
  --enable=oauth \
  --enable=device_tracking \
  --enable=security_monitoring \
  --with-controllers \
  --migrate
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

Enable OAuth providers during installation or manually.

### Provider Status

| Provider | Status | Notes |
|----------|--------|-------|
| Google | `[STABLE]` | Fully tested, production-ready |
| GitHub | `[DRAFT]` | Implemented, needs more testing |
| Facebook | `[DRAFT]` | Implemented, needs more testing |
| Microsoft | `[DRAFT]` | Implemented, needs more testing |
| Discord | `[DRAFT]` | Implemented, needs more testing |

```yaml
better_auth:
    oauth:
        providers:
            google:  # [STABLE]
                enabled: true
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/google/callback'

            github:  # [DRAFT]
                enabled: true
                client_id: '%env(GITHUB_CLIENT_ID)%'
                client_secret: '%env(GITHUB_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/github/callback'

            # Also available (DRAFT): facebook, microsoft, discord
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

## 📚 Documentation

- **[Hybrid Mode (API + Session)](docs/HYBRID_MODE.md)** - Use tokens AND cookies together (recommended!)
- **[Two-Factor Authentication (TOTP)](docs/TWO_FACTOR.md)** - Setup and use 2FA with Google Authenticator
- **[Email Templates](docs/EMAIL_TEMPLATES.md)** - Customize email templates
- **[OAuth Providers](docs/OAUTH.md)** - Configure Google, GitHub, Facebook, etc.

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
