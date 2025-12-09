# Entity Customization

Extend and customize BetterAuth entities for your application.

## Methods Overview

| Method | Use Case | Complexity |
|--------|----------|------------|
| **Auto-generate with setup-features** | Add features (Magic Link, 2FA, etc.) | **Very Low** ⭐ |
| Use UserProfileTrait | Include optional fields (name, avatar) | Very Low |
| Exclude optional fields | Minimal User entity | Very Low |
| Extend entities | Add custom fields | Low |
| Migration only | Simple field additions | Very Low |
| Complete custom | Full control | High |

> ⭐ **Recommended**: Use `better-auth:setup-features` to automatically generate entities for Magic Link, 2FA, OAuth, and other features. See [Installation Guide](01-INSTALLATION.md#setup-features-add-magic-link-2fa-oauth-etc) for details.

---

## Auto-Generate Entities with setup-features

**The easiest way to add entities** for features like Magic Link, 2FA, OAuth, etc.:

```bash
# Enable Magic Link - automatically generates MagicLinkToken entity
php bin/console better-auth:setup-features --enable=magic_link --migrate

# Enable 2FA - automatically generates TotpData entity
php bin/console better-auth:setup-features --enable=two_factor --migrate

# Enable multiple features at once
php bin/console better-auth:setup-features --enable=magic_link --enable=two_factor --migrate
```

This command will:
- ✅ Generate missing entity PHP files from templates
- ✅ Update `config/packages/better_auth.yaml`
- ✅ Optionally generate controllers (with `--with-controllers`)
- ✅ Optionally run migrations (with `--migrate`)

**Available entities that can be auto-generated:**

| Entity | Feature | Command |
|--------|---------|---------|
| `MagicLinkToken` | Magic Link | `--enable=magic_link` |
| `TotpData` | Two-Factor Auth | `--enable=two_factor` |
| `EmailVerificationToken` | Email Verification | `--enable=email_verification` |
| `PasswordResetToken` | Password Reset | `--enable=password_reset` |
| `Device` | Device Tracking | `--enable=device_tracking` |
| `SecurityEvent` | Security Monitoring | `--enable=security_monitoring` |
| `GuestSession` | Guest Sessions | `--enable=guest_sessions` |
| `Organization` | Multi-Tenant | `--enable=multi_tenant` |
| `OrganizationMember` | Multi-Tenant | `--enable=multi_tenant` |

See [Installation Guide - Setup Features](01-INSTALLATION.md#setup-features-add-magic-link-2fa-oauth-etc) for complete documentation.

---

## Optional User Fields (name, avatar)

BetterAuth provides optional profile fields (`name`, `avatar`) that can be included or excluded based on your needs.

### During Installation

```bash
# Include all fields (default)
php bin/console better-auth:install --id-strategy=uuid --mode=api

# Exclude all optional fields (minimal)
php bin/console better-auth:install --id-strategy=uuid --mode=api --minimal

# Exclude specific fields
php bin/console better-auth:install --id-strategy=uuid --mode=api --exclude-fields=avatar
```

### After Installation

Use the `better-auth:user-fields` command to add or remove fields:

```bash
# Add fields
php bin/console better-auth:user-fields add name
php bin/console better-auth:user-fields add name,avatar

# Remove fields (WARNING: data loss after migration!)
php bin/console better-auth:user-fields remove avatar
php bin/console better-auth:user-fields remove name,avatar --force

# Generate and run migration
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

### Using UserProfileTrait

If you want profile fields, your User entity can use the `UserProfileTrait`:

```php
<?php
// src/Entity/User.php

namespace App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
use BetterAuth\Symfony\Model\UserProfileTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    use UserProfileTrait;  // Adds name and avatar fields

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    protected string $id;

    // Your custom fields...
}
```

### Minimal User Entity (without profile fields)

For authentication-only use cases:

```php
<?php
// src/Entity/User.php

namespace App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    // No UserProfileTrait = no name/avatar fields

    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    protected string $id;

    // Essential fields only: email, password, roles, emailVerified, timestamps
}
```

---

## Method 1: Extend Entities (Recommended)

Create your own entity classes that extend BetterAuth base entities.

### Custom User Entity

```php
<?php
// src/Entity/User.php

namespace App\Entity;

use BetterAuth\Core\Entities\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferences = null;

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function setCompany(?string $company): self
    {
        $this->company = $company;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getPreferences(): ?array
    {
        return $this->preferences;
    }

    public function setPreferences(?array $preferences): self
    {
        $this->preferences = $preferences;
        return $this;
    }
}
```

### Custom Session Entity

```php
<?php
// src/Entity/Session.php

namespace App\Entity;

use BetterAuth\Core\Entities\Session as BaseSession;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
class Session extends BaseSession
{
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $deviceFingerprint = null;

    public function getDeviceFingerprint(): ?string
    {
        return $this->deviceFingerprint;
    }

    public function setDeviceFingerprint(?string $deviceFingerprint): self
    {
        $this->deviceFingerprint = $deviceFingerprint;
        return $this;
    }
}
```

### Configure Repositories

Tell BetterAuth to use your custom entities:

```yaml
# config/services_betterauth.yaml
services:
    BetterAuth\Symfony\Storage\Doctrine\DoctrineUserRepository:
        arguments:
            $userClass: 'App\Entity\User'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineSessionRepository:
        arguments:
            $sessionClass: 'App\Entity\Session'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineRefreshTokenRepository:
        arguments:
            $refreshTokenClass: 'App\Entity\RefreshToken'
```

### Generate Migration

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

---

## Method 2: Migration Only

Add custom fields without custom entity classes:

```bash
php bin/console make:migration
```

Edit the generated migration:

```php
public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE users ADD phone VARCHAR(20) DEFAULT NULL');
    $this->addSql('ALTER TABLE users ADD company VARCHAR(255) DEFAULT NULL');
    $this->addSql('ALTER TABLE users ADD avatar VARCHAR(255) DEFAULT NULL');
}
```

---

## Method 3: Complete Custom Entities

For full control, create completely custom entities:

```php
<?php
// src/Entity/User.php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    private string $email;

    #[ORM\Column(type: 'string')]
    private string $password;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name = null;

    #[ORM\Column(type: 'boolean')]
    private bool $emailVerified = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // Add all your custom fields...

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
    }

    // Implement all getters/setters...
}
```

Then implement the repository interface:

```php
<?php
// src/Repository/UserRepository.php

namespace App\Repository;

use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

class UserRepository extends ServiceEntityRepository implements UserRepositoryInterface
{
    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findById(string $id): ?User
    {
        return $this->find($id);
    }

    // Implement all interface methods...
}
```

---

## Adding Custom Fields to API Response

### Extend formatUser in Controller

```php
// src/Controller/AuthController.php

private function formatUser(User $user): array
{
    return [
        'id' => $user->getId(),
        'email' => $user->getEmail(),
        'name' => $user->getName(),
        'emailVerified' => $user->isEmailVerified(),
        // Custom fields
        'phone' => $user->getPhone(),
        'company' => $user->getCompany(),
        'avatar' => $user->getAvatar(),
        'preferences' => $user->getPreferences(),
    ];
}
```

### Update Registration

```php
#[Route('/auth/register', methods: ['POST'])]
public function register(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);

    $additionalData = [
        'name' => $data['name'] ?? null,
        'phone' => $data['phone'] ?? null,
        'company' => $data['company'] ?? null,
    ];

    $user = $this->authManager->signUp(
        $data['email'],
        $data['password'],
        $additionalData
    );

    // ...
}
```

---

## Common Customizations

### Add Profile Picture

```php
#[ORM\Column(type: 'string', length: 255, nullable: true)]
private ?string $avatar = null;

public function getAvatarUrl(): ?string
{
    if (!$this->avatar) {
        return null;
    }
    return '/uploads/avatars/' . $this->avatar;
}
```

### Add Roles

```php
#[ORM\Column(type: 'json')]
private array $roles = ['ROLE_USER'];

public function getRoles(): array
{
    $roles = $this->roles;
    $roles[] = 'ROLE_USER'; // Ensure ROLE_USER
    return array_unique($roles);
}
```

### Add Timestamps

```php
#[ORM\Column(type: 'datetime_immutable', nullable: true)]
private ?\DateTimeImmutable $lastLoginAt = null;

public function updateLastLogin(): void
{
    $this->lastLoginAt = new \DateTimeImmutable();
}
```

### Add Soft Delete

```php
#[ORM\Column(type: 'datetime_immutable', nullable: true)]
private ?\DateTimeImmutable $deletedAt = null;

public function isDeleted(): bool
{
    return $this->deletedAt !== null;
}

public function delete(): void
{
    $this->deletedAt = new \DateTimeImmutable();
}
```

---

## Best Practices

1. **Start with Method 1**: Extend base entities for most use cases
2. **Make custom fields nullable**: Avoid breaking existing functionality
3. **Test authentication flows**: After customization
4. **Use migrations**: For production database changes
5. **Document custom fields**: For team awareness

---

## Next Steps

- [Email Templates](15-EMAIL-TEMPLATES.md)
- [Configuration](02-CONFIGURATION.md)
- [API Reference](09-API-REFERENCE.md)
