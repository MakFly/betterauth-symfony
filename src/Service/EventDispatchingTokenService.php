<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Service;

use BetterAuth\Core\Interfaces\TokenSignerInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Symfony\Event\BetterAuthEvents;
use BetterAuth\Symfony\Event\TokenCreatedEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Decorator for the Core TokenService.
 * Intercepts token creation to dispatch Symfony events, allowing payload modification.
 */
class EventDispatchingTokenService implements TokenSignerInterface
{
    public function __construct(
        private readonly TokenSignerInterface $inner,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    public function sign(array $payload, int $expiresIn): string
    {
        // Try to find the user to pass it to the event
        $user = null;
        if (isset($payload['sub'])) {
            $user = $this->userRepository->findById($payload['sub']);
        }

        // Dispatch event to allow payload modification
        $event = new TokenCreatedEvent($payload, $user);
        $this->dispatcher->dispatch($event, BetterAuthEvents::TOKEN_CREATED);

        // Sign with modified payload
        return $this->inner->sign($event->getPayload(), $expiresIn);
    }

    public function verify(string $token): ?array
    {
        return $this->inner->verify($token);
    }

    public function decode(string $token): ?array
    {
        return $this->inner->decode($token);
    }
}
