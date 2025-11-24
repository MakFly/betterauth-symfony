<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a token is invalid (bad signature, tampered, etc).
 * Similar to Lexik's JWTInvalidEvent.
 *
 * Use cases:
 * - Log security events
 * - Customize error response
 * - Alert on repeated invalid tokens (potential attack)
 */
final class TokenInvalidEvent extends Event
{
    private ?Response $response = null;

    public function __construct(
        private readonly string $reason,
        private readonly ?string $token = null,
        private readonly ?\Throwable $exception = null,
    ) {
    }

    public function getReason(): string
    {
        return $this->reason;
    }

    public function getToken(): ?string
    {
        return $this->token;
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }

    public function setResponse(Response $response): void
    {
        $this->response = $response;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }
}
