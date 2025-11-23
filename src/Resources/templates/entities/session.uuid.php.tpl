<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\Session as BaseSession;
use Doctrine\ORM\Mapping as ORM;

/**
 * Session entity - Extends BetterAuth base Session (UUID v7 strategy).
 *
 * Sessions are identified by their token (string).
 * User ID is stored as UUID v7 (string, time-ordered).
 */
#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
class Session extends BaseSession
{
    // Add custom fields here if needed
    // Example:
    // #[ORM\Column(type: Types::STRING, nullable: true)]
    // private ?string $deviceName = null;
}
