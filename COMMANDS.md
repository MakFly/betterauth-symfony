# BetterAuth Symfony Commands

## 🚀 Available Commands

### 1. **Setup Dependencies**

Install and configure all required dependencies for BetterAuth.

```bash
php bin/console better-auth:setup:dependencies
```

**What it does:**
- ✅ Checks for required Composer packages
- ✅ Installs missing packages automatically
- ✅ Configures services.yaml
- ✅ Creates necessary directories

**Options:**
```bash
--skip-install    # Skip composer install (dry-run)
--with-dev        # Include dev dependencies
```

**Example:**
```bash
# Install all dependencies
php bin/console better-auth:setup:dependencies

# Preview what would be installed
php bin/console better-auth:setup:dependencies --skip-install

# Include dev tools
php bin/console better-auth:setup:dependencies --with-dev
```

**Packages installed:**
- `symfony/security-bundle`
- `symfony/validator`
- `symfony/monolog-bundle`
- `doctrine/doctrine-bundle`
- `doctrine/orm`

---

### 2. **Setup Logging**

Configure Monolog for BetterAuth logging.

```bash
php bin/console better-auth:setup:logging
```

**What it does:**
- ✅ Creates/updates `config/packages/monolog.yaml`
- ✅ Configures BetterAuth log channel
- ✅ Sets up file and console handlers
- ✅ Configures per-environment logging

**Options:**
```bash
--channel=NAME    # Log channel name (default: betterauth)
--level=LEVEL     # Log level (default: info)
--path=PATH       # Log file path (default: var/log/betterauth.log)
```

**Examples:**
```bash
# Default setup
php bin/console better-auth:setup:logging

# Debug mode
php bin/console better-auth:setup:logging --level=debug

# Custom channel and path
php bin/console better-auth:setup:logging --channel=auth --path=/var/log/auth.log
```

**Log levels:**
- `debug` - Detailed debug information
- `info` - Informational messages (default)
- `notice` - Normal but significant events
- `warning` - Warning messages
- `error` - Error messages
- `critical` - Critical conditions

**Usage in code:**
```php
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AuthService
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.betterauth')]
        private LoggerInterface $logger
    ) {}

    public function authenticate(string $email): void
    {
        $this->logger->info('User authentication attempt', [
            'email' => $email,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ]);

        // Your authentication logic...

        $this->logger->info('User authenticated successfully', [
            'user_id' => $userId,
        ]);
    }
}
```

**View logs:**
```bash
# Tail logs in real-time
tail -f var/log/betterauth.log

# Search for errors
grep ERROR var/log/betterauth.log

# Last 100 lines
tail -n 100 var/log/betterauth.log
```

---

### 3. **Update Configuration**

Update BetterAuth configuration files.

```bash
php bin/console better-auth:config:update [config-type]
```

**Config types:**
- `all` - Update all configuration files (default)
- `security` - Update `config/packages/security.yaml`
- `better_auth` - Update `config/packages/better_auth.yaml`
- `monolog` - Update `config/packages/monolog.yaml`
- `services` - Update `config/services.yaml`

**Options:**
```bash
--dry-run         # Preview changes without applying
--force, -f       # Overwrite existing files
```

**Examples:**
```bash
# Update all configs
php bin/console better-auth:config:update

# Update only security config
php bin/console better-auth:config:update security

# Preview changes
php bin/console better-auth:config:update --dry-run

# Force overwrite existing configs
php bin/console better-auth:config:update --force
```

**Generated configurations:**

#### security.yaml
```yaml
security:
    password_hashers:
        Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface: 'auto'

    providers:
        better_auth:
            id: BetterAuth\Symfony\Security\BetterAuthUserProvider

    firewalls:
        api:
            pattern: ^/api
            stateless: true
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator
```

#### better_auth.yaml
```yaml
better_auth:
    secret: '%env(BETTER_AUTH_SECRET)%'
    session:
        lifetime: 604800
        cookie_name: better_auth_session
    api:
        enabled: true
        access_token_lifetime: 3600
    oauth:
        providers:
            google:
                enabled: true
                client_id: '%env(OAUTH_GOOGLE_CLIENT_ID)%'
    multi_tenant:
        enabled: true
    logging:
        enabled: true
        channel: betterauth
```

---

### 4. **Install Command**

Complete installation wizard (existing command).

```bash
php bin/console better-auth:install
```

---

## 📋 Complete Setup Workflow

### Initial Setup

```bash
# 1. Install BetterAuth bundle
composer require betterauth/symfony-bundle

# 2. Install dependencies
php bin/console better-auth:setup:dependencies

# 3. Configure logging
php bin/console better-auth:setup:logging

# 4. Update configuration files
php bin/console better-auth:config:update

# 5. Create database schema
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate

# 6. Clear cache
php bin/console cache:clear
```

### Environment Variables

Add to `.env`:

```env
###> betterauth/symfony-bundle ###
BETTER_AUTH_SECRET=your-secret-key-min-32-characters
OAUTH_GOOGLE_ENABLED=false
OAUTH_GOOGLE_CLIENT_ID=
OAUTH_GOOGLE_CLIENT_SECRET=
OAUTH_GITHUB_ENABLED=false
OAUTH_GITHUB_CLIENT_ID=
OAUTH_GITHUB_CLIENT_SECRET=
###< betterauth/symfony-bundle ###
```

### Generate Secret Key

```bash
# Linux/Mac
openssl rand -base64 32

# Or PHP
php -r "echo bin2hex(random_bytes(32));"
```

---

## 🔧 Troubleshooting

### Command not found

If commands are not available:

```bash
# Clear cache
php bin/console cache:clear

# Rebuild container
php bin/console cache:warmup

# Check if bundle is registered
php bin/console debug:container BetterAuth
```

### Permission errors

```bash
# Fix permissions for logs
chmod -R 777 var/log/
chmod -R 777 var/cache/
```

### Configuration not applied

```bash
# Clear configuration cache
php bin/console cache:clear --env=prod

# Validate YAML syntax
php bin/console lint:yaml config/
```

---

## 📚 Additional Commands

### Doctrine

```bash
# Create migration
php bin/console doctrine:migrations:diff

# Execute migrations
php bin/console doctrine:migrations:migrate

# Rollback migration
php bin/console doctrine:migrations:migrate prev
```

### Cache

```bash
# Clear all caches
php bin/console cache:clear

# Warmup cache
php bin/console cache:warmup

# Clear specific pool
php bin/console cache:pool:clear cache.app
```

### Debug

```bash
# List all services
php bin/console debug:container

# Check BetterAuth services
php bin/console debug:container BetterAuth

# Check security configuration
php bin/console debug:firewall

# Check routes
php bin/console debug:router
```

---

## 💡 Tips

1. **Always run in dev first**: Test commands in development before production
2. **Use --dry-run**: Preview changes before applying them
3. **Backup configs**: Keep backups before running update commands
4. **Check logs**: Monitor `var/log/betterauth.log` for issues
5. **Environment-specific**: Adjust configs per environment (dev/prod/test)

---

**Made with ❤️ by [MakFly](https://github.com/MakFly)**
