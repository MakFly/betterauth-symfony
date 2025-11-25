# Entity Customization

Extend and customize BetterAuth entities for your application.

## Methods Overview

| Method | Use Case | Complexity |
|--------|----------|------------|
| Extend entities | Add custom fields | Low |
| Migration only | Simple field additions | Very Low |
| Complete custom | Full control | High |

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
