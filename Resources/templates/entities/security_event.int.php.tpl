<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\SecurityEvent as BaseSecurityEvent;
use Doctrine\ORM\Mapping as ORM;

/**
 * SecurityEvent entity with INT IDs.
 */
#[ORM\Entity]
#[ORM\Table(name: 'security_events')]
#[ORM\Index(columns: ['user_id'])]
#[ORM\Index(columns: ['event_type'])]
#[ORM\Index(columns: ['severity'])]
#[ORM\Index(columns: ['created_at'])]
class SecurityEvent extends BaseSecurityEvent
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
