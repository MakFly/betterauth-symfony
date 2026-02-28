<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    protected string $id;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string|int $id): static
    {
        $this->id = (string) $id;
        return $this;
    }
}
