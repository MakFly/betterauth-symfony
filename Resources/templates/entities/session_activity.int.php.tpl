<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\SessionActivity as BaseSessionActivity;
use Doctrine\ORM\Mapping as ORM;

/**
 * SessionActivity entity with INT IDs.
 */
#[ORM\Entity]
#[ORM\Table(name: 'session_activity')]
#[ORM\Index(columns: ['session_id'])]
#[ORM\Index(columns: ['created_at'])]
class SessionActivity extends BaseSessionActivity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    protected string $sessionId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(string|int $id): static
    {
        $this->id = (int) $id;

        return $this;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    public function setSessionId(string $sessionId): static
    {
        $this->sessionId = $sessionId;

        return $this;
    }
}
