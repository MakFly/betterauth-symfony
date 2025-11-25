# Migration Guide

Migrate from other authentication systems to BetterAuth.

## From LexikJWTAuthenticationBundle

### Key Differences

| Feature | LexikJWT | BetterAuth |
|---------|----------|------------|
| Token Format | JWT (RS256/HS256) | Paseto V4 |
| Refresh Tokens | Manual | Built-in |
| OAuth | Not included | Built-in |
| 2FA | Not included | Built-in |
| Session Support | No | Yes (hybrid mode) |
| Multi-tenant | No | Yes |

### Step 1: Install BetterAuth

```bash
composer require betterauth/symfony-bundle
composer remove lexik/jwt-authentication-bundle
```

### Step 2: Update Configuration

**Before (LexikJWT):**
```yaml
# config/packages/lexik_jwt_authentication.yaml
lexik_jwt_authentication:
    secret_key: '%env(resolve:JWT_SECRET_KEY)%'
    public_key: '%env(resolve:JWT_PUBLIC_KEY)%'
    pass_phrase: '%env(JWT_PASSPHRASE)%'
    token_ttl: 3600
```

**After (BetterAuth):**
```yaml
# config/packages/better_auth.yaml
better_auth:
    mode: 'api'
    secret: '%env(BETTER_AUTH_SECRET)%'
    token:
        lifetime: 3600
        refresh_lifetime: 2592000
```

### Step 3: Update Security

**Before:**
```yaml
# config/packages/security.yaml
security:
    firewalls:
        api:
            stateless: true
            jwt: ~
```

**After:**
```yaml
# config/packages/security.yaml
security:
    providers:
        better_auth:
            id: BetterAuth\Symfony\Security\BetterAuthUserProvider

    firewalls:
        api:
            stateless: true
            provider: better_auth
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator
```

### Step 4: Update Controllers

**Before (LexikJWT):**
```php
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

class AuthController
{
    public function login(JWTTokenManagerInterface $JWTManager)
    {
        $token = $JWTManager->create($user);
        return $this->json(['token' => $token]);
    }
}
```

**After (BetterAuth):**
```php
use BetterAuth\Core\AuthManager;

class AuthController
{
    public function login(AuthManager $authManager, Request $request)
    {
        $result = $authManager->signIn(
            $email,
            $password,
            $request->getClientIp(),
            $request->headers->get('User-Agent')
        );

        return $this->json([
            'access_token' => $result['access_token'],
            'refresh_token' => $result['refresh_token'],
            'user' => $result['user'],
        ]);
    }
}
```

### Step 5: Update Frontend

**Before (LexikJWT):**
```typescript
// Store single token
localStorage.setItem('token', response.token);

// Use in header
headers: { 'Authorization': `Bearer ${token}` }
```

**After (BetterAuth):**
```typescript
// Store both tokens
localStorage.setItem('access_token', response.access_token);
localStorage.setItem('refresh_token', response.refresh_token);

// Implement refresh logic
if (response.status === 401) {
    const newTokens = await refreshToken();
    // Retry request
}
```

### Step 6: Database Migration

Generate migration for new entities:
```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

---

## From Symfony Native Authentication

### Step 1: Install

```bash
composer require betterauth/symfony-bundle
php bin/console better-auth:install
```

### Step 2: Update User Entity

**Before:**
```php
use Symfony\Component\Security\Core\User\UserInterface;

class User implements UserInterface
{
    private ?int $id = null;
    private ?string $email = null;
    private ?string $password = null;
    private array $roles = [];

    // Standard getters/setters
}
```

**After:**
```php
use BetterAuth\Core\Entities\User as BetterAuthUser;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class User extends BetterAuthUser
{
    // BetterAuth handles all base fields
    // Add custom fields here
}
```

### Step 3: Update Login Flow

**Before (Form Login):**
```php
// LoginFormAuthenticator.php
class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    public function authenticate(Request $request): Passport
    {
        // Form-based authentication
    }
}
```

**After (API-based):**
```php
// BetterAuth handles authentication automatically
// Just use the /auth/login endpoint
```

---

## From FOSUserBundle

### Note: FOSUserBundle is deprecated

### Step 1: Install BetterAuth

```bash
composer remove friendsofsymfony/user-bundle
composer require betterauth/symfony-bundle
```

### Step 2: Migrate User Data

```php
// Create migration command
class MigrateUsersCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $oldUsers = $this->oldUserRepository->findAll();

        foreach ($oldUsers as $oldUser) {
            // Password hashes are compatible (both use bcrypt/argon2)
            $newUser = new User();
            $newUser->setEmail($oldUser->getEmail());
            $newUser->setPassword($oldUser->getPassword()); // Keep hash
            $newUser->setName($oldUser->getUsername());

            $this->entityManager->persist($newUser);
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
```

### Step 3: Update Templates

**Before (FOSUser templates):**
```twig
{% extends "@FOSUser/layout.html.twig" %}
```

**After:**
```twig
{# Create your own templates or use API #}
```

---

## From Laravel Sanctum

### API Token Comparison

| Sanctum | BetterAuth |
|---------|------------|
| Personal Access Tokens | Paseto V4 Tokens |
| `$request->user()` | `$this->authManager->getCurrentUser($token)` |
| `auth()->user()` | Same, via Symfony Security |

### Migration Steps

1. Export Laravel users to JSON/CSV
2. Import into Symfony with BetterAuth
3. Password hashes are compatible (bcrypt)
4. Update API responses to match BetterAuth format

---

## Data Migration Script

### Export from old system

```php
// ExportUsersCommand.php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $users = $this->connection->fetchAllAssociative('SELECT * FROM users');

    file_put_contents('users.json', json_encode($users, JSON_PRETTY_PRINT));

    return Command::SUCCESS;
}
```

### Import to BetterAuth

```php
// ImportUsersCommand.php
protected function execute(InputInterface $input, OutputInterface $output): int
{
    $users = json_decode(file_get_contents('users.json'), true);

    foreach ($users as $userData) {
        $user = new User();
        $user->setEmail($userData['email']);
        $user->setPassword($userData['password']); // Hash is compatible
        $user->setName($userData['name'] ?? '');
        $user->setEmailVerified($userData['email_verified'] ?? false);

        $this->entityManager->persist($user);
    }

    $this->entityManager->flush();

    $output->writeln(sprintf('Imported %d users', count($users)));

    return Command::SUCCESS;
}
```

---

## Token Migration

### Invalidate old tokens

```bash
# Truncate old token tables
php bin/console doctrine:query:sql "TRUNCATE TABLE old_tokens"
```

### Generate new tokens

```php
// Force all users to re-login
// Their old tokens will be invalid
// New BetterAuth tokens will be issued on login
```

---

## Rollback Plan

### Keep old system temporarily

```yaml
# config/packages/security.yaml
security:
    firewalls:
        # New BetterAuth endpoints
        api_v2:
            pattern: ^/api/v2
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator

        # Old system (for migration period)
        api_v1:
            pattern: ^/api/v1
            # Old authenticator
```

### Gradual migration

1. Deploy BetterAuth alongside old system
2. Migrate users in batches
3. Monitor for issues
4. Switch traffic gradually
5. Remove old system

---

## Checklist

- [ ] Back up database
- [ ] Install BetterAuth
- [ ] Migrate user data
- [ ] Update security config
- [ ] Update controllers
- [ ] Update frontend
- [ ] Test authentication flows
- [ ] Test token refresh
- [ ] Deploy to staging
- [ ] Monitor errors
- [ ] Deploy to production

---

## Next Steps

- [Installation](01-INSTALLATION.md)
- [Configuration](02-CONFIGURATION.md)
- [API Reference](09-API-REFERENCE.md)
