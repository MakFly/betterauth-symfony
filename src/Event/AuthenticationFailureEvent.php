<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Event;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Contracts\EventDispatcher\Event;

class AuthenticationFailureEvent extends Event
{
    public function __construct(
        private readonly AuthenticationException $exception,
        private ?Response $response
    ) {
    }

    public function getException(): AuthenticationException
    {
        return $this->exception;
    }

    public function getResponse(): ?Response
    {
        return $this->response;
    }

    public function setResponse(?Response $response): void
    {
        $this->response = $response;
    }
}
