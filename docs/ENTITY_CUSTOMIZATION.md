# Entity Customization Guide

BetterAuth provides base entities as **Mapped Superclasses**, allowing you to easily extend and customize them for your application.

## Why Mapped Superclasses?

- ✅ **Fully customizable**: Add your own fields, methods, and business logic
- ✅ **No coupling**: Your entities remain in your namespace (`App\Entity`)
- ✅ **Type safety**: Full IDE autocompletion and type hints
- ✅ **Framework integration**: Works seamlessly with Symfony, API Platform, etc.

---

## Quick Start

### Default Setup (Zero Customization)

When you install BetterAuth, create these minimal entities:

```php
// src/Entity/User.php
namespace App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    // Ready to go! No custom fields needed.
}
```

```php
// src/Entity/Session.php
namespace App\Entity;

use BetterAuth\Symfony\Model\Session as BaseSession;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
class Session extends BaseSession
{
}
```

```php
// src/Entity/RefreshToken.php
namespace App\Entity;

use BetterAuth\Symfony\Model\RefreshToken as BaseRefreshToken;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
}
```

Then run migrations:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

---

## Adding Custom Fields

### Example: Add Phone Number to User

```php
// src/Entity/User.php
namespace App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isActive = true;

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(?string $phoneNumber): self
    {
        $this->phoneNumber = $phoneNumber;
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

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }
}
```

After adding fields:

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

---

## Advanced: Relationships

### Example: User has many Posts

```php
// src/Entity/User.php
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'author', cascade: ['remove'])]
    private Collection $posts;

    public function __construct()
    {
        parent::__construct(); // IMPORTANT: Call parent constructor
        $this->posts = new ArrayCollection();
    }

    /**
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): self
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setAuthor($this);
        }
        return $this;
    }
}
```

---

## Multiple User Types (Single Table Inheritance)

For apps with different user types (Admin, Customer, etc.):

```php
// src/Entity/User.php
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'user_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'admin' => AdminUser::class,
    'customer' => CustomerUser::class,
    'seller' => SellerUser::class
])]
abstract class User extends BaseUser
{
    // Common fields for all user types
}
```

```php
// src/Entity/AdminUser.php
#[ORM\Entity]
class AdminUser extends User
{
    #[ORM\Column(type: Types::JSON)]
    private array $permissions = [];

    // Admin-specific methods
}
```

```php
// src/Entity/CustomerUser.php
#[ORM\Entity]
class CustomerUser extends User
{
    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $loyaltyCard = null;

    // Customer-specific methods
}
```

---

## API Platform Integration

Make your User entity an API Resource:

```php
// src/Entity/User.php
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ApiResource(
    operations: [
        new Get(security: "is_granted('ROLE_USER') and object == user"),
        new GetCollection(security: "is_granted('ROLE_ADMIN')"),
        new Patch(security: "is_granted('ROLE_USER') and object == user")
    ]
)]
class User extends BaseUser
{
    // Your custom fields...
}
```

---

## Base Entity Properties

### User Entity

The base `BetterAuth\Symfony\Model\User` provides:

```php
protected string $id;
protected string $email;
protected ?string $passwordHash;
protected ?string $name;
protected ?string $avatar;
protected bool $emailVerified;
protected ?DateTimeImmutable $emailVerifiedAt;
protected DateTimeImmutable $createdAt;
protected DateTimeImmutable $updatedAt;
protected ?array $metadata;
```

All properties are **protected**, so you can access them in your extended class.

### Session Entity

```php
protected string $token; // Primary key
protected string $userId;
protected DateTimeImmutable $expiresAt;
protected string $ipAddress;
protected string $userAgent;
protected ?array $metadata;
protected ?string $activeOrganizationId; // For multi-tenant
protected ?string $activeTeamId; // For multi-tenant
```

### RefreshToken Entity

```php
protected string $token; // Primary key
protected string $userId;
protected DateTimeImmutable $expiresAt;
protected DateTimeImmutable $createdAt;
protected bool $revoked;
protected ?string $replacedBy; // Token rotation
```

---

## Customizing Table Names

```php
#[ORM\Entity]
#[ORM\Table(name: 'app_users')] // Custom table name
class User extends BaseUser
{
}
```

---

## Migration Tips

### From existing User entity

If you already have a User entity:

1. **Backup your existing User.php**
2. **Extend BaseUser** and migrate your custom fields:

```php
// Before:
class User implements UserInterface
{
    private string $id;
    private string $email;
    private string $password;
    private string $phoneNumber; // Your custom field
}

// After:
class User extends BaseUser // Provides id, email, passwordHash
{
    private string $phoneNumber; // Keep only your custom field
}
```

3. **Update references**: Change `$password` → `$passwordHash`
4. **Run migrations**: `doctrine:migrations:diff`

---

## FAQ

### Can I override getters/setters?

Yes! All methods in base entities are public, so you can override them:

```php
class User extends BaseUser
{
    public function getEmail(): string
    {
        return strtolower(parent::getEmail()); // Force lowercase
    }

    public function setEmail(string $email): self
    {
        parent::setEmail(strtolower($email));
        return $this;
    }
}
```

### Can I use a different namespace?

Yes, but update the repository configuration:

```yaml
# config/services.yaml
BetterAuth\Symfony\Storage\Doctrine\DoctrineUserRepository:
    arguments:
        $entityManager: '@Doctrine\ORM\EntityManagerInterface'
        $userClass: 'MyApp\\Domain\\User' # Custom namespace
```

### Can I use UUID instead of ULID?

Yes, override the ID generation:

```php
class User extends BaseUser
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    protected string $id;
}
```

---

## Best Practices

1. ✅ **Always call parent::__construct()** if you override the constructor
2. ✅ **Use migrations** for schema changes (`doctrine:migrations:diff`)
3. ✅ **Keep authentication logic in repositories**, not entities
4. ✅ **Use metadata field** for flexible JSON storage instead of many columns
5. ✅ **Test migrations** on a copy of production data before deploying

---

## Need Help?

- 📚 [BetterAuth Documentation](https://github.com/your-repo/better-auth-php)
- 💬 [GitHub Issues](https://github.com/your-repo/better-auth-php/issues)
- 🔐 [Security Guide](./SECURITY.md)
