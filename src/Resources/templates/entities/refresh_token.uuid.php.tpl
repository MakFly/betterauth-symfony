<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\RefreshToken as BaseRefreshToken;
use Doctrine\ORM\Mapping as ORM;

/**
 * RefreshToken entity - Extends BetterAuth base RefreshToken (UUID v7 strategy).
 *
 * Refresh tokens are identified by their token (string).
 * User ID is stored as UUID v7 (string, time-ordered).
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
    // Add custom fields here if needed
}
