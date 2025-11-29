<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\SuspiciousActivity as BaseSuspiciousActivity;
use Doctrine\ORM\Mapping as ORM;

/**
 * SuspiciousActivity entity with INT IDs.
 */
#[ORM\Entity]
#[ORM\Table(name: 'suspicious_activities')]
#[ORM\Index(columns: ['user_id'])]
#[ORM\Index(columns: ['status'])]
#[ORM\Index(columns: ['risk_level'])]
#[ORM\Index(columns: ['detected_at'])]
class SuspiciousActivity extends BaseSuspiciousActivity
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
