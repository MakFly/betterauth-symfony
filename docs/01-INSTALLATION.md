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
| `--exclude-fields` | `name`, `avatar` | Comma-separated list of optional User fields to exclude |
| `--minimal` | - | Exclude all optional fields (name, avatar) |
| `--skip-migrations` | - | Don't generate/run migrations |
| `--skip-controller` | - | Don't generate AuthController |
| `--skip-config` | - | Don't generate config files |
| `--no-interaction` | - | Run without prompts |

### Minimal Installation (without profile fields)

If you only need email/password authentication without profile fields:

```bash
php bin/console better-auth:install \
  --id-strategy=uuid \
  --mode=api \
  --minimal \
  --no-interaction
```

Or exclude only specific fields:

```bash
# Keep name, exclude avatar
php bin/console better-auth:install \
  --id-strategy=uuid \
  --mode=api \
  --exclude-fields=avatar

# Exclude both name and avatar (same as --minimal)
php bin/console better-auth:install \
  --id-strategy=uuid \
  --mode=api \
  --exclude-fields=name,avatar
```

---

## What Gets Generated

### Entities

```
src/Entity/
├── User.php                    # User entity (UUID v7 or INT)
├── Session.php                 # Session tracking
├── RefreshToken.php            # Refresh token storage
├── MagicLinkToken.php          # Passwordless magic links
├── EmailVerificationToken.php  # Email verification tokens
├── PasswordResetToken.php      # Password reset tokens
├── TotpData.php                # 2FA/TOTP data
└── GuestSession.php            # Guest sessions (optional)
```

### Controllers

**Core Controllers (installés automatiquement):**

```
src/Controller/Api/
├── Trait/
│   └── ApiResponseTrait.php      # Trait for consistent API responses
├── AuthController.php            # Authentication & 2FA (11 endpoints)
├── PasswordController.php        # Password reset (3 endpoints)
└── SessionsController.php        # Session management (2 endpoints)
```

**Optional Controllers (via `better-auth:add-controller`):**

```
src/Controller/Api/
├── OAuthController.php           # OAuth providers (3 endpoints)
├── EmailVerificationController.php # Email verification (4 endpoints)
├── MagicLinkController.php       # Passwordless auth (3 endpoints)
├── GuestSessionController.php    # Guest sessions (4 endpoints)
├── AccountLinkController.php     # Account linking (4 endpoints)
└── DeviceController.php          # Device management (6 endpoints)
```

### All Available Endpoints (40 total)

| Controller | Route | Description |
|------------|-------|-------------|
| **AuthController** (11) | | |
| | `POST /auth/register` | Inscription |
| | `POST /auth/login` | Connexion |
| | `POST /auth/login/2fa` | Connexion avec 2FA |
| | `GET /auth/me` | Utilisateur courant |
| | `POST /auth/refresh` | Rafraîchir le token |
| | `POST /auth/logout` | Déconnexion |
| | `POST /auth/revoke-all` | Déconnecter tous les appareils |
| | `POST /auth/2fa/setup` | Setup 2FA |
| | `POST /auth/2fa/verify` | Vérifier et activer 2FA |
| | `POST /auth/2fa/disable` | Désactiver 2FA |
| | `GET /auth/2fa/status` | Statut 2FA |
| **PasswordController** (3) | | |
| | `POST /auth/password/forgot` | Demande reset |
| | `POST /auth/password/reset` | Reset mot de passe |
| | `POST /auth/password/verify-token` | Vérifier token |
| **SessionsController** (2) | | |
| | `GET /auth/sessions` | Lister sessions |
| | `DELETE /auth/sessions/{id}` | Révoquer session |
| **OAuthController** (3) | | |
| | `GET /auth/oauth` | Lister providers OAuth |
| | `GET /auth/oauth/{provider}` | Redirection OAuth |
| | `GET /auth/oauth/{provider}/callback` | Callback OAuth |
| **EmailVerificationController** (4) | | |
| | `POST /auth/email/send-verification` | Envoyer email vérification |
| | `POST /auth/email/verify` | Vérifier email |
| | `POST /auth/email/resend` | Renvoyer email |
| | `GET /auth/email/status` | Statut vérification |
| **MagicLinkController** (3) | | |
| | `POST /auth/magic-link/request` | Demander magic link |
| | `POST /auth/magic-link/verify` | Vérifier magic link |
| | `POST /auth/magic-link/check` | Valider token |
| **GuestSessionController** (4) | | |
| | `POST /auth/guest/create` | Créer session guest |
| | `POST /auth/guest/convert` | Convertir en user |
| | `GET /auth/guest/{token}` | Récupérer données guest |
| | `PATCH /auth/guest/{token}` | Mettre à jour guest |
| **AccountLinkController** (4) | | |
| | `GET /auth/account/links` | Lister comptes liés |
| | `GET /auth/account/link/{provider}` | Initier liaison |
| | `GET /auth/account/link/{provider}/callback` | Callback liaison |
| | `DELETE /auth/account/link/{provider}` | Délier compte |
| **DeviceController** (6) | | |
| | `GET /auth/devices` | Lister appareils |
| | `GET /auth/devices/{id}` | Détails appareil |
| | `DELETE /auth/devices/{id}` | Révoquer appareil |
| | `POST /auth/devices/{id}/trust` | Marquer comme fiable |
| | `DELETE /auth/devices/{id}/trust` | Retirer confiance |
| | `POST /auth/devices/revoke-all` | Révoquer tous sauf actuel |

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

### Auto-Configuration des Repositories

Le bundle détecte automatiquement les entités `App\Entity\*` et configure les repositories Doctrine via `EntityAutoConfigurationPass`. **Aucune configuration manuelle dans `services.yaml` n'est nécessaire.**

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

### Manage User Fields

Add or remove optional fields (`name`, `avatar`) after installation:

```bash
# Add the name field
php bin/console better-auth:user-fields add name

# Add multiple fields
php bin/console better-auth:user-fields add name,avatar

# Remove a field (WARNING: data loss after migration!)
php bin/console better-auth:user-fields remove avatar

# Remove all optional fields
php bin/console better-auth:user-fields remove name,avatar

# Force without confirmation
php bin/console better-auth:user-fields remove name --force
```

**After modifying fields:**

```bash
# Generate migration for the changes
php bin/console doctrine:migrations:diff

# Apply migration
php bin/console doctrine:migrations:migrate
```

**Available optional fields:**
| Field | Type | Description |
|-------|------|-------------|
| `name` | VARCHAR(255) | User display name |
| `avatar` | VARCHAR(500) | User avatar URL |

### Add Controller

Add individual controllers after installation:

```bash
# List all available controllers
php bin/console better-auth:add-controller --list

# Add a specific controller interactively
php bin/console better-auth:add-controller

# Add OAuth controller
php bin/console better-auth:add-controller oauth

# Add all optional controllers at once
php bin/console better-auth:add-controller --all

# Force overwrite existing
php bin/console better-auth:add-controller oauth --force
```

**Available Controllers:**

| Controller | Description | Endpoints |
|------------|-------------|-----------|
| `auth` | Core authentication (register, login, 2FA) | 11 |
| `password` | Password reset flow | 3 |
| `sessions` | Session management | 2 |
| `oauth` | OAuth providers (Google, GitHub, etc.) | 3 |
| `email-verification` | Email verification flow | 4 |
| `magic-link` | Passwordless authentication | 3 |
| `guest` | Guest/anonymous sessions | 4 |
| `account-link` | Link third-party accounts | 4 |
| `devices` | Device management & tracking | 6 |

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
