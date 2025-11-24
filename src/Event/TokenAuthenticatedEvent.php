<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

use BetterAuth\Core\Entities\User;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after the token is fully validated and user authenticated.
 * Similar to Lexik's JWTAuthenticatedEvent.
 *
 * Use cases:
 * - Log successful authentication
 * - Update user last activity
 * - Trigger post-auth hooks
 */
final class TokenAuthenticatedEvent extends Event
{
    public function __construct(
        private readonly array $payload,
        private readonly User $user,
        private readonly string $token,
    ) {
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getToken(): string
    {
        return $this->token;
    }
}
