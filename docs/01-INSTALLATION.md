# Installation

Complete guide to install and configure BetterAuth Symfony bundle.

## Requirements

- PHP 8.2 or higher
- Symfony 6.4 or 7.x
- Doctrine ORM 3.0+
- Composer 2.x

## Quick Installation

```bash
# 1. Install the bundle
composer require betterauth/symfony-bundle

# 2. Run the installation wizard
php bin/console better-auth:install
```

The wizard will guide you through:
1. ID strategy selection (UUID v7 or INT)
2. Authentication mode (api, session, hybrid)
3. OAuth providers (optional)
4. Entity generation
5. Migration creation and execution

---

## Installation Options

### Interactive Mode (Recommended)

```bash
php bin/console better-auth:install
```

The wizard asks:
- **ID Strategy**: UUID v7 (recommended) or INT
- **Mode**: API, Session, or Hybrid
- **OAuth Providers**: Google, GitHub, Facebook, Microsoft, Discord

### Non-Interactive Mode

For CI/CD or automation:

```bash
php bin/console better-auth:install \
  --id-strategy=uuid \
  --mode=api \
  --no-interaction
```

### Available Options

| Option | Values | Description |
|--------|--------|-------------|
| `--id-strategy` | `uuid`, `int` | ID generation strategy |
| `--mode` | `api`, `session`, `hybrid` | Authentication mode |
| `--skip-migrations` | - | Don't generate/run migrations |
| `--skip-controller` | - | Don't generate AuthController |
| `--skip-config` | - | Don't generate config files |
| `--no-interaction` | - | Run without prompts |

---

## What Gets Generated

### Entities

```
src/Entity/
├── User.php           # User entity (UUID v7 or INT)
├── Session.php        # Session tracking
└── RefreshToken.php   # Refresh token storage
```

### Controller

```
src/Controller/
└── AuthController.php   # 8 authentication endpoints
```

### Configuration

```
config/packages/
└── better_auth.yaml     # BetterAuth configuration
```

### Environment Variables

```env
# .env
BETTER_AUTH_SECRET=auto_generated_64_char_secret
APP_URL=http://localhost:8000
```

---

## Post-Installation

### 1. Configure Database

```bash
# Create/update database schema
php bin/console doctrine:migrations:migrate
```

### 2. Configure Security

The bundle auto-configures `security.yaml`, but verify:

```yaml
# config/packages/security.yaml
security:
    providers:
        better_auth:
            id: BetterAuth\Symfony\Security\BetterAuthUserProvider

    firewalls:
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

### 3. Clear Cache

```bash
php bin/console cache:clear
```

### 4. Test Installation

```bash
# Start server
symfony server:start

# Test registration
curl -X POST http://localhost:8000/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"Password123","name":"Test User"}'
```

---

## Additional Setup Commands

### Setup Dependencies

Install required Composer packages:

```bash
php bin/console better-auth:setup:dependencies
```

**Options:**
- `--skip-install`: Preview without installing
- `--with-dev`: Include dev dependencies

### Setup Logging

Configure Monolog for BetterAuth:

```bash
php bin/console better-auth:setup:logging
```

**Options:**
- `--channel=NAME`: Log channel (default: betterauth)
- `--level=LEVEL`: Log level (default: info)
- `--path=PATH`: Log file path

### Update Configuration

Update existing configuration files:

```bash
php bin/console better-auth:config:update
```

**Config types:**
- `all`: Update all configs (default)
- `security`: Update security.yaml
- `better_auth`: Update better_auth.yaml
- `monolog`: Update monolog.yaml

**Options:**
- `--dry-run`: Preview changes
- `--force`: Overwrite existing

---

## Configuration Commands

### Switch Authentication Mode

```bash
# Show current mode
php bin/console better-auth:switch-mode

# Switch to API mode
php bin/console better-auth:switch-mode api

# Preview changes without applying
php bin/console better-auth:switch-mode session --dry-run
```

### Generate Configuration

Generate complete configuration with presets:

```bash
# Interactive
php bin/console better-auth:generate-config

# With preset
php bin/console better-auth:generate-config --preset=standard

# With comments
php bin/console better-auth:generate-config --preset=enterprise --with-comments
```

**Presets:**
- `minimal`: Basic API mode
- `standard`: API + OAuth (Google, GitHub) + 2FA
- `enterprise`: Hybrid mode + all features

### Interactive Configuration Wizard

Step-by-step configuration:

```bash
# Full wizard
php bin/console better-auth:configure

# Configure specific section
php bin/console better-auth:configure --section=oauth
php bin/console better-auth:configure --section=2fa
```

---

## Troubleshooting Installation

### Command not found

```bash
# Clear cache
php bin/console cache:clear

# Check bundle is registered
php bin/console debug:container BetterAuth
```

### Permission errors

```bash
chmod -R 755 var/log/
chmod -R 755 var/cache/
```

### Configuration not applied

```bash
# Clear configuration cache
php bin/console cache:clear --env=prod

# Validate YAML syntax
php bin/console lint:yaml config/
```

### Migration errors

```bash
# Check doctrine configuration
php bin/console doctrine:schema:validate

# Create migration manually
php bin/console doctrine:migrations:diff
```

---

## Next Steps

- [Configuration Reference](02-CONFIGURATION.md)
- [API Mode Setup](03-API-MODE.md)
- [OAuth Providers](06-OAUTH-PROVIDERS.md)
