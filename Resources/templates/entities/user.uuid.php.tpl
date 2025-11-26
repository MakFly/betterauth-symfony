<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
{{USE_PROFILE_TRAIT}}
use Doctrine\ORM\Mapping as ORM;

/**
 * User entity with UUID v7 primary key.
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
    #[ORM\Column(type: 'string', length: 36)]
    protected string $id;
{{CUSTOM_FIELDS}}
    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string|int $id): static
    {
        $this->id = (string) $id;

        return $this;
    }

    // Add your custom fields here
}
