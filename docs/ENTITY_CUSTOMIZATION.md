# Entity Customization Guide

BetterAuth provides flexible ways to customize entities according to your needs.

## Method 1: Extend Entities (Recommended)

Create your own entity classes that extend the BetterAuth base entities:

### Example: Custom User Entity

```php
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
}
```

### Configure the Repository

Tell BetterAuth to use your custom entity:

```yaml
# config/services.yaml
services:
    BetterAuth\Symfony\Storage\Doctrine\DoctrineUserRepository:
        arguments:
            $userClass: 'App\Entity\User'
```

### Generate Migration for Custom Fields

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

## Method 2: Add Fields via Migrations Only

Keep using BetterAuth entities but add custom fields via migrations:

```bash
php bin/console make:migration
```

Edit the generated migration to add your fields:

```php
public function up(Schema $schema): void
{
    $this->addSql('ALTER TABLE users ADD phone VARCHAR(20) DEFAULT NULL');
    $this->addSql('ALTER TABLE users ADD company VARCHAR(255) DEFAULT NULL');
}
```

## Method 3: Complete Custom Entities

For full control, create completely custom entities and configure all repositories.

See the full documentation for advanced customization options.

## Best Practices

1. **Start with Method 1**: Extend base entities for most use cases
2. **Use Method 2**: For simple field additions without custom entity classes  
3. **Always make custom fields nullable** to avoid breaking existing functionality
4. **Test authentication flows** after customization
