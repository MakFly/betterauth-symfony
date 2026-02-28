<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Core\Entities\PasswordResetToken as BasePasswordResetToken;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'password_reset_tokens')]
class PasswordResetToken extends BasePasswordResetToken
{
}
