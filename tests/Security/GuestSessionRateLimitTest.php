<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Security;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Interfaces\GuestSessionRepositoryInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\Utils\RateLimiter;
use BetterAuth\Providers\GuestSessionProvider\GuestSessionProvider;
use BetterAuth\Symfony\Controller\GuestSessionController;
use BetterAuth\Symfony\Tests\Controller\Trait\ControllerTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * SEC-24 — unauthenticated guest-session creation must be rate-limited per IP to
 * prevent resource exhaustion / table pollution.
 */
final class GuestSessionRateLimitTest extends TestCase
{
    use ControllerTestTrait;

    public function testCreateIsBlockedOncePerIpLimitReached(): void
    {
        $rateLimiter = new RateLimiter(allowInMemoryFallback: true);
        // Saturate the per-IP budget (CREATE_MAX_ATTEMPTS = 20) up front.
        for ($i = 0; $i < 20; $i++) {
            $rateLimiter->hit('guest_create:203.0.113.7', 60);
        }

        $provider = new GuestSessionProvider(
            $this->createMock(GuestSessionRepositoryInterface::class),
            $this->createMock(UserRepositoryInterface::class),
        );

        $controller = new GuestSessionController(
            $provider,
            $this->createMock(AuthManager::class),
            null,
            $rateLimiter,
        );
        $this->setUpControllerContainer($controller);

        $request = Request::create('/auth/guest/create', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7'], content: '{}');
        $response = $controller->createGuestSession($request);

        $this->assertSame(429, $response->getStatusCode());
    }
}
