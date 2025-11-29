<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\AccountLink as BaseAccountLink;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * AccountLink entity with UUID IDs.
 */
#[ORM\Entity]
#[ORM\Table(name: 'account_links')]
#[ORM\UniqueConstraint(name: 'user_provider_unique', columns: ['user_id', 'provider'])]
#[ORM\Index(columns: ['provider', 'provider_id'])]
class AccountLink extends BaseAccountLink
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    protected Uuid $id;

    #[ORM\Column(type: UuidType::NAME)]
    protected Uuid $userId;

    public function __construct()
    {
        parent::__construct();
        $this->id = Uuid::v7();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function setId(string|int $id): static
    {
        $this->id = $id instanceof Uuid ? $id : Uuid::fromString((string) $id);

        return $this;
    }

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function setUserId(string|int $userId): static
    {
        $this->userId = $userId instanceof Uuid ? $userId : Uuid::fromString((string) $userId);

        return $this;
    }
}
