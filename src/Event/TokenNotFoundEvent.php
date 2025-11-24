<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when no token is found in the request.
 * Similar to Lexik's JWTNotFoundEvent.
 *
 * Use cases:
 * - Log missing token requests
 * - Customize error response
 * - Allow anonymous access in specific cases
 */
final class TokenNotFoundEvent extends Event
{
    private ?Response $response = null;

    public function __construct(
        private readonly string $context = 'Authorization header missing or invalid',
    ) {
    }

    public function getContext(): string
    {
        return $this->context;
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
