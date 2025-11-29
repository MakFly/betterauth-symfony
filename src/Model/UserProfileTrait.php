<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Optional profile fields for User entity.
 *
 * Use this trait in your User entity to add username and avatar fields.
 * These fields are optional and can be excluded during installation
 * using the --exclude-fields option.
 *
 * @example Adding profile fields to User:
 * ```php
 * #[ORM\Entity]
 * class User extends BaseUser {
 *     use UserProfileTrait;
 *
 *     #[ORM\Id]
 *     #[ORM\Column(type: Types::STRING, length: 36)]
 *     protected string $id;
 * }
 * ```
 *
 * @example Adding only specific fields manually:
 * ```php
 * #[ORM\Entity]
 * class User extends BaseUser {
 *     // Only add username, not avatar
 *     #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
 *     protected ?string $username = null;
 *
 *     public function getUsername(): ?string { return $this->username; }
 *     public function setUsername(?string $username): static { $this->username = $username; return $this; }
 * }
 * ```
 */
trait UserProfileTrait
{
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $username = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    protected ?string $avatar = null;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }
}
