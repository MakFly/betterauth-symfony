<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Core\Entities\MagicLinkToken as BaseMagicLinkToken;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'magic_link_tokens')]
class MagicLinkToken extends BaseMagicLinkToken
{
}
