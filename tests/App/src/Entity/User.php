<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\User as BaseUser;
use BetterAuth\Symfony\Model\UserProfileTrait;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'users')]
class User extends BaseUser
{
    use UserProfileTrait;
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
