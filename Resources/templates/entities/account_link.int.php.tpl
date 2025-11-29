<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\AccountLink as BaseAccountLink;
use Doctrine\ORM\Mapping as ORM;

/**
 * AccountLink entity with INT IDs.
 */
#[ORM\Entity]
#[ORM\Table(name: 'account_links')]
#[ORM\UniqueConstraint(name: 'user_provider_unique', columns: ['user_id', 'provider'])]
#[ORM\Index(columns: ['provider', 'provider_id'])]
class AccountLink extends BaseAccountLink
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(type: 'integer')]
    protected int $userId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(string|int $id): static
    {
        $this->id = (int) $id;

        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(string|int $userId): static
    {
        $this->userId = (int) $userId;

        return $this;
    }
}
