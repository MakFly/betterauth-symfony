<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\SessionActivity as BaseSessionActivity;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Uid\Uuid;

/**
 * SessionActivity entity with UUID IDs.
 */
#[ORM\Entity]
#[ORM\Table(name: 'session_activity')]
#[ORM\Index(columns: ['session_id'])]
#[ORM\Index(columns: ['created_at'])]
class SessionActivity extends BaseSessionActivity
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    protected Uuid $id;

    #[ORM\Column(type: 'string', length: 255)]
    protected string $sessionId;

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
