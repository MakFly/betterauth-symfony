<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\RefreshToken as BaseRefreshToken;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken extends BaseRefreshToken
{
}
