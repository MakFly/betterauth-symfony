<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Core\Entities\TotpData as BaseTotpData;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'totp_data')]
class TotpData extends BaseTotpData
{
}
