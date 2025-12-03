<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

use BetterAuth\Core\Entities\User as CoreUser;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Dispatched when authentication is successful.
 * Allows customizing the success response.
 *
 * Use cases:
 * - Add custom data to response
 * - Log successful logins
 * - Track login statistics
 *
 * The user can be either:
 * - BetterAuth\Core\Entities\User (DTO from TokenAuthManager)
 * - App\Entity\User (Doctrine entity via Symfony Security)
 */
final class AuthenticationSuccessEvent extends Event
{
    private ?Response $response = null;

    public function __construct(
        private readonly UserInterface|CoreUser $user,
        private array $data = [],
    ) {
    }

    public function getUser(): UserInterface|CoreUser
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
