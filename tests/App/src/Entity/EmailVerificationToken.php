<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Core\Entities\EmailVerificationToken as BaseEmailVerificationToken;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'email_verification_tokens')]
class EmailVerificationToken extends BaseEmailVerificationToken
{
}
