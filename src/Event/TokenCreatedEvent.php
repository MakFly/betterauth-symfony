<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

use BetterAuth\Core\Entities\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event dispatched when a token is created.
 * Allows listeners to modify the token payload before signing.
 *
 * This event is final to prevent over-specialization.
 */
final class TokenCreatedEvent extends Event
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        private array $payload,
        private readonly ?User $user
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
