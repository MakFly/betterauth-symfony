<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

use BetterAuth\Core\Entities\User;
use Symfony\Contracts\EventDispatcher\Event;

class TokenCreatedEvent extends Event
{
    public function __construct(
        private array $payload,
        private readonly ?User $user
    ) {
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
