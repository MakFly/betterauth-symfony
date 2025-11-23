<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

/**
 * User entity - Extends BetterAuth base User (UUID v7 strategy).
 *
 * This entity uses UUID v7 as primary key for enhanced security and performance.
 * UUID v7 is time-ordered, improving database indexing performance compared to UUID v4.
 * Add your custom fields here by creating properties with Doctrine attributes.
 *
 * @example
 * #[ORM\Column(type: Types::STRING, nullable: true)]
 * private ?string $phoneNumber = null;
 */
#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    // Add custom fields here if needed
    // Example:
    // #[ORM\Column(type: Types::STRING, nullable: true)]
    // private ?string $phoneNumber = null;
    //
    // public function getPhoneNumber(): ?string
    // {
    //     return $this->phoneNumber;
    // }
    //
    // public function setPhoneNumber(?string $phoneNumber): self
    // {
    //     $this->phoneNumber = $phoneNumber;
    //     return $this;
    // }
}
