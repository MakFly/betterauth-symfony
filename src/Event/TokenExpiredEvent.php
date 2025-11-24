<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when a token has expired.
 * Similar to Lexik's JWTExpiredEvent.
 *
 * Use cases:
 * - Log expired token usage
 * - Customize error response with refresh instructions
 * - Auto-refresh token if refresh_token available
 */
final class TokenExpiredEvent extends Event
{
    private ?Response $response = null;

    public function __construct(
        private readonly string $token,
        private readonly ?\DateTimeImmutable $expiredAt = null,
    ) {
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function getExpiredAt(): ?\DateTimeImmutable
    {
        return $this->expiredAt;
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
