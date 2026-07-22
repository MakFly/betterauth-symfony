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
 * CSRF protection for cookie-authenticated requests (SEC-09).
 *
 * CSRF only threatens ambient credentials the browser attaches automatically —
 * i.e. the auth cookie. This subscriber therefore requires a valid double-submit
 * `X-CSRF-TOKEN` header on every state-changing request that carries the auth
 * cookie, on ANY path and in ANY mode, and skips:
 *  - requests using an `Authorization: Bearer` token (API clients, immune to CSRF);
 *  - requests with no auth cookie (e.g. login/register set the cookie, they can't
 *    be ridden yet).
 *
 * Note: the auth cookie is opt-in — it is not part of the default token-extractor
 * chain (see services.yaml). Enforcement here is defense-in-depth for apps that
 * enable cookie-based auth.
 */
final class CsrfProtectionSubscriber implements EventSubscriberInterface
{
    private const CSRF_HEADER = 'X-CSRF-TOKEN';
    private const CSRF_TOKEN_ID = 'better_auth';
    private const STATE_CHANGING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function __construct(
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly string $cookieName = 'access_token',
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

        $request = $event->getRequest();

        // Only check state-changing methods
        if (!in_array($request->getMethod(), self::STATE_CHANGING_METHODS, true)) {
            return;
        }

        // Skip if request has a Bearer token (API client, not vulnerable to CSRF)
        $authHeader = $request->headers->get('Authorization', '');
        if (str_starts_with($authHeader, 'Bearer ')) {
            return;
        }

        // Only cookie-authenticated requests are CSRF-exposed: without the auth
        // cookie there is no ambient credential to ride (login/register/etc.).
        if (!$request->cookies->has($this->cookieName)) {
            return;
        }

        // Validate CSRF token from header (double-submit)
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
