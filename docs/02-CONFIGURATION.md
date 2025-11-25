# Configuration

Complete reference for all BetterAuth configuration options.

## Configuration File

Location: `config/packages/better_auth.yaml`

## Full Configuration Reference

```yaml
better_auth:
    # Authentication mode
    # - api: Stateless Paseto V4 tokens (SPAs, mobile apps)
    # - session: Cookie-based sessions (traditional web apps)
    # - hybrid: Both tokens and sessions (maximum flexibility)
    mode: 'api'

    # Secret key for token encryption
    # MUST be changed in production - minimum 32 characters
    # Generate with: php -r "echo bin2hex(random_bytes(32));"
    secret: '%env(BETTER_AUTH_SECRET)%'

    # Session configuration (for session/hybrid modes)
    session:
        # Session lifetime in seconds (default: 7 days)
        lifetime: 604800
        # Cookie name for session storage
        cookie_name: 'better_auth_session'

    # Token configuration (for api/hybrid modes)
    token:
        # Access token lifetime in seconds (default: 1 hour)
        lifetime: 3600
        # Refresh token lifetime in seconds (default: 30 days)
        refresh_lifetime: 2592000

    # OAuth provider configuration
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

            microsoft:
                enabled: false
                client_id: '%env(MICROSOFT_CLIENT_ID)%'
                client_secret: '%env(MICROSOFT_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/microsoft/callback'

            facebook:
                enabled: false
                client_id: '%env(FACEBOOK_CLIENT_ID)%'
                client_secret: '%env(FACEBOOK_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/facebook/callback'

            discord:
                enabled: false
                client_id: '%env(DISCORD_CLIENT_ID)%'
                client_secret: '%env(DISCORD_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/discord/callback'

    # Multi-tenant (organizations/teams) configuration
    multi_tenant:
        enabled: false
        default_role: 'member'

    # Two-factor authentication (TOTP)
    two_factor:
        enabled: true
        # Name shown in authenticator apps
        issuer: 'MyApp'
        # Number of backup codes to generate
        backup_codes_count: 10
```

---

## Configuration Sections

### Mode

```yaml
better_auth:
    mode: 'api'  # api | session | hybrid
```

| Mode | Description | Use Case |
|------|-------------|----------|
| `api` | Stateless Paseto V4 tokens | SPAs, mobile apps, microservices |
| `session` | Cookie-based sessions | Traditional web apps, Twig templates |
| `hybrid` | Both tokens and sessions | Apps with mixed clients |

### Secret

```yaml
better_auth:
    secret: '%env(BETTER_AUTH_SECRET)%'
```

**Requirements:**
- Minimum 32 characters
- Use environment variable in production
- Never commit to version control

**Generate a secure secret:**
```bash
# Using PHP
php -r "echo bin2hex(random_bytes(32));"

# Using OpenSSL
openssl rand -base64 32
```

### Session

```yaml
better_auth:
    session:
        lifetime: 604800        # 7 days in seconds
        cookie_name: 'better_auth_session'
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `lifetime` | int | 604800 | Session duration in seconds |
| `cookie_name` | string | better_auth_session | HTTP cookie name |

**Common lifetime values:**
- 1 day: `86400`
- 7 days: `604800`
- 30 days: `2592000`

### Token

```yaml
better_auth:
    token:
        lifetime: 3600          # 1 hour
        refresh_lifetime: 2592000  # 30 days
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `lifetime` | int | 3600 | Access token duration in seconds |
| `refresh_lifetime` | int | 2592000 | Refresh token duration in seconds |

**Security recommendations:**
- Short access tokens (15min - 2h)
- Longer refresh tokens (7 - 30 days)
- Refresh token rotation enabled

### OAuth Providers

```yaml
better_auth:
    oauth:
        providers:
            google:
                enabled: true
                client_id: '%env(GOOGLE_CLIENT_ID)%'
                client_secret: '%env(GOOGLE_CLIENT_SECRET)%'
                redirect_uri: '%env(APP_URL)%/auth/oauth/google/callback'
```

**Supported providers:**
- `google` - Google OAuth
- `github` - GitHub OAuth
- `microsoft` - Microsoft/Azure AD
- `facebook` - Facebook OAuth
- `discord` - Discord OAuth
- `twitter` - Twitter/X OAuth
- `apple` - Apple Sign In

### Multi-Tenant

```yaml
better_auth:
    multi_tenant:
        enabled: true
        default_role: 'member'
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | false | Enable organizations/teams |
| `default_role` | string | member | Default role for new members |

### Two-Factor Authentication

```yaml
better_auth:
    two_factor:
        enabled: true
        issuer: 'MyApp'
        backup_codes_count: 10
```

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `enabled` | bool | true | Enable TOTP 2FA |
| `issuer` | string | BetterAuth | Name in authenticator apps |
| `backup_codes_count` | int | 10 | Number of backup codes |

---

## Environment Variables

### Required

```env
BETTER_AUTH_SECRET=your-64-char-secret-here
```

### Recommended

```env
APP_URL=https://myapp.com
MAILER_DSN=smtp://localhost:1025
MAILER_FROM_EMAIL=noreply@myapp.com
MAILER_FROM_NAME=MyApp
```

### OAuth Providers

```env
# Google
GOOGLE_CLIENT_ID=xxx.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=xxx

# GitHub
GITHUB_CLIENT_ID=xxx
GITHUB_CLIENT_SECRET=xxx

# Microsoft
MICROSOFT_CLIENT_ID=xxx
MICROSOFT_CLIENT_SECRET=xxx

# Facebook
FACEBOOK_CLIENT_ID=xxx
FACEBOOK_CLIENT_SECRET=xxx

# Discord
DISCORD_CLIENT_ID=xxx
DISCORD_CLIENT_SECRET=xxx
```

---

## Security Configuration

### Symfony Security Integration

```yaml
# config/packages/security.yaml
security:
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
        - { path: ^/, roles: ROLE_USER }
```

---

## Configuration Presets

Use `better-auth:generate-config` with presets:

### Minimal

```bash
php bin/console better-auth:generate-config --preset=minimal
```

- API mode only
- No OAuth
- No 2FA
- No multi-tenant

### Standard

```bash
php bin/console better-auth:generate-config --preset=standard
```

- API mode
- Google + GitHub OAuth
- 2FA enabled
- No multi-tenant

### Enterprise

```bash
php bin/console better-auth:generate-config --preset=enterprise
```

- Hybrid mode
- All OAuth providers
- 2FA enabled
- Multi-tenant enabled

---

## Logging Configuration

```yaml
# config/packages/monolog.yaml
monolog:
    channels:
        - better_auth

    handlers:
        better_auth:
            type: stream
            path: "%kernel.logs_dir%/better_auth.log"
            level: info
            channels: ["better_auth"]
```

**Usage in code:**
```php
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class MyService
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.better_auth')]
        private LoggerInterface $logger
    ) {}
}
```

---

## Next Steps

- [API Mode](03-API-MODE.md)
- [Session Mode](04-SESSION-MODE.md)
- [OAuth Providers](06-OAUTH-PROVIDERS.md)
