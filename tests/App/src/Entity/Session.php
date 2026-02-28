<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\Session as BaseSession;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
class Session extends BaseSession
{
}
