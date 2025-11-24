<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched after a token is decoded but before validation.
 * Allows custom validation of the decoded payload.
 * Similar to Lexik's JWTDecodedEvent.
 *
 * Use cases:
 * - Add custom validation rules
 * - Check custom claims
 * - Log token decoding
 */
final class TokenDecodedEvent extends Event
{
    private bool $valid = true;

    public function __construct(
        private array $payload,
        private readonly string $token,
    ) {
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    /**
     * Mark the token as invalid (will fail authentication).
     */
    public function markAsInvalid(): void
    {
        $this->valid = false;
    }

    public function isValid(): bool
    {
        return $this->valid;
    }
}
