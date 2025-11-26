<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
{{USE_PROFILE_TRAIT}}
use Doctrine\ORM\Mapping as ORM;

/**
 * User entity with auto-increment INT primary key.
 *
 * Extends BetterAuth base User which implements:
 * - UserInterface
 * - PasswordAuthenticatedUserInterface
 *
 * Add your custom fields by creating properties with Doctrine attributes.
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
{{PROFILE_TRAIT}}
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;
{{CUSTOM_FIELDS}}
    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(string|int $id): static
    {
        $this->id = (int) $id;

        return $this;
    }

    // Add your custom fields here
}
