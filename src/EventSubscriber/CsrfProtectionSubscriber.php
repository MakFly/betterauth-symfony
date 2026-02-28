<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * CSRF protection for session-based authentication.
 *
 * Only active when mode is 'session' or 'hybrid'.
 * Does NOT apply to API mode (Bearer tokens are not vulnerable to CSRF).
 */
final class CsrfProtectionSubscriber implements EventSubscriberInterface
{
    private const CSRF_HEADER = 'X-CSRF-TOKEN';
    private const CSRF_TOKEN_ID = 'better_auth';
    private const STATE_CHANGING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly string $authMode,
        private readonly string $authRoutePrefix = '/auth',
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        // Only protect session/hybrid modes; API mode relies on Bearer tokens (not vulnerable to CSRF)
        if ($this->authMode === 'api') {
            return;
        }

        $request = $event->getRequest();

        // Only check state-changing methods
        if (!in_array($request->getMethod(), self::STATE_CHANGING_METHODS, true)) {
            return;
        }

        // Only protect auth routes
        if (!str_starts_with($request->getPathInfo(), $this->authRoutePrefix)) {
            return;
        }

        // Skip if request has a Bearer token (API client, not a browser session)
        $authHeader = $request->headers->get('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return;
        }

        // Validate CSRF token from header
        $csrfToken = $request->headers->get(self::CSRF_HEADER);
        if (
            $csrfToken === null
            || !$this->csrfTokenManager->isTokenValid(new CsrfToken(self::CSRF_TOKEN_ID, $csrfToken))
        ) {
            $event->setResponse(new JsonResponse(
                ['error' => 'Invalid or missing CSRF token'],
                403
            ));
        }
    }
}
