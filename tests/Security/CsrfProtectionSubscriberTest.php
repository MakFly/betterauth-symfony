<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Security;

use BetterAuth\Symfony\EventSubscriber\CsrfProtectionSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * SEC-09 — CSRF must be enforced on cookie-authenticated state-changing requests,
 * on any path, while Bearer and cookie-less requests stay exempt.
 */
final class CsrfProtectionSubscriberTest extends TestCase
{
    private function event(Request $request): RequestEvent
    {
        return new RequestEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );
    }

    private function subscriber(bool $tokenValid): CsrfProtectionSubscriber
    {
        $manager = $this->createMock(CsrfTokenManagerInterface::class);
        $manager->method('isTokenValid')
            ->willReturnCallback(fn (CsrfToken $t): bool => $tokenValid);

        return new CsrfProtectionSubscriber($manager);
    }

    public function testCookieRequestWithoutCsrfHeaderIsBlockedOnApiPath(): void
    {
        $request = Request::create('/api/orders', 'POST', cookies: ['access_token' => 'v4.local.xxx']);
        $event = $this->event($request);

        $this->subscriber(false)->onKernelRequest($event);

        self::assertNotNull($event->getResponse());
        self::assertSame(403, $event->getResponse()->getStatusCode());
    }

    public function testCookieRequestWithValidCsrfHeaderPasses(): void
    {
        $request = Request::create('/api/orders', 'POST', cookies: ['access_token' => 'v4.local.xxx']);
        $request->headers->set('X-CSRF-TOKEN', 'valid-token');
        $event = $this->event($request);

        $this->subscriber(true)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testBearerRequestIsExemptEvenWithCookie(): void
    {
        $request = Request::create('/api/orders', 'POST', cookies: ['access_token' => 'v4.local.xxx']);
        $request->headers->set('Authorization', 'Bearer sometoken');
        $event = $this->event($request);

        $this->subscriber(false)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testCookielessRequestIsExempt(): void
    {
        // e.g. login/register — no ambient credential to ride.
        $request = Request::create('/auth/login', 'POST');
        $event = $this->event($request);

        $this->subscriber(false)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }

    public function testSafeMethodIsExempt(): void
    {
        $request = Request::create('/api/orders', 'GET', cookies: ['access_token' => 'v4.local.xxx']);
        $event = $this->event($request);

        $this->subscriber(false)->onKernelRequest($event);

        self::assertNull($event->getResponse());
    }
}
