<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

use BetterAuth\Core\Entities\User;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when authentication is successful.
 * Allows customizing the success response.
 *
 * Use cases:
 * - Add custom data to response
 * - Log successful logins
 * - Track login statistics
 */
final class AuthenticationSuccessEvent extends Event
{
    private ?Response $response = null;

    public function __construct(
        private readonly User $user,
        private array $data = [],
    ) {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): void
    {
        $this->data = $data;
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
