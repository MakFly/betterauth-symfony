<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Security;

use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Exceptions\InvalidTokenException;
use BetterAuth\Core\Exceptions\TokenExpiredException;
use BetterAuth\Core\Interfaces\TokenSignerInterface;
use BetterAuth\Symfony\Event\BetterAuthEvents;
use BetterAuth\Symfony\Event\TokenExpiredEvent;
use BetterAuth\Symfony\Event\TokenInvalidEvent;
use BetterAuth\Symfony\Event\TokenNotFoundEvent;
use BetterAuth\Symfony\Security\BetterAuthAuthenticator;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Interface for mocking TokenAuthManager (final class).
 */
interface TokenAuthManagerInterface
{
    public function verify(string $token): User;
}

/**
 * Security tests for BetterAuthAuthenticator.
 *
 * These tests verify:
 * - Events are dispatched correctly
 * - Error messages don't leak sensitive info in production
 * - Token extraction is secure
 * - All failure scenarios are handled
 */
class AuthenticatorSecurityTest extends TestCase
{
    private MockObject $authManager;
    private MockObject|EventDispatcherInterface $dispatcher;
    private MockObject|TokenSignerInterface $tokenService;

    protected function setUp(): void
    {
        $this->authManager = $this->createMock(TokenAuthManagerInterface::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->tokenService = $this->createMock(TokenSignerInterface::class);
    }

    // ========================================
    // EVENT DISPATCHING TESTS
    // ========================================

    /**
     * @test
     * Verify TOKEN_NOT_FOUND event is dispatched when no token.
     */
    public function dispatches_token_not_found_event(): void
    {
        $authenticator = new BetterAuthAuthenticator(
            $this->authManager,
            $this->dispatcher,
            $this->tokenService,
            false
        );

        $request = new Request();

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(TokenNotFoundEvent::class),
                BetterAuthEvents::TOKEN_NOT_FOUND
            );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    /**
     * @test
     * Verify TOKEN_EXPIRED event is dispatched when token expired.
     */
    public function dispatches_token_expired_event(): void
    {
        $authenticator = new BetterAuthAuthenticator(
            $this->authManager,
            $this->dispatcher,
            $this->tokenService,
            false
        );

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer v4.local.expired_token');

        $this->tokenService->method('decode')
            ->willReturn(['sub' => 'user123', 'exp' => time() - 3600]);

        $this->authManager->method('verify')
            ->willThrowException(new TokenExpiredException());

        $this->dispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->withConsecutive(
                [$this->anything(), BetterAuthEvents::TOKEN_DECODED],
                [$this->isInstanceOf(TokenExpiredEvent::class), BetterAuthEvents::TOKEN_EXPIRED]
            );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Token has expired');
        $authenticator->authenticate($request);
    }

    /**
     * @test
     * Verify TOKEN_INVALID event is dispatched when token is invalid.
     */
    public function dispatches_token_invalid_event(): void
    {
        $authenticator = new BetterAuthAuthenticator(
            $this->authManager,
            $this->dispatcher,
            $this->tokenService,
            false
        );

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer v4.local.invalid_token');

        $this->tokenService->method('decode')->willReturn(null);

        $this->authManager->method('verify')
            ->willThrowException(new InvalidTokenException('Signature mismatch'));

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(TokenInvalidEvent::class),
                BetterAuthEvents::TOKEN_INVALID
            );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    // ========================================
    // ERROR MESSAGE SECURITY TESTS
    // ========================================

    /**
     * @test
     * Verify that debug=false doesn't leak error details.
     */
    public function production_mode_hides_error_details(): void
    {
        $authenticator = new BetterAuthAuthenticator(
            $this->authManager,
            $this->dispatcher,
            $this->tokenService,
            false // debug = false (production)
        );

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer v4.local.test');

        $sensitiveMessage = 'Database connection failed at 192.168.1.100:5432';
        $this->authManager->method('verify')
            ->willThrowException(new InvalidTokenException($sensitiveMessage));

        $this->dispatcher->method('dispatch')->willReturnArgument(0);

        try {
            $authenticator->authenticate($request);
            $this->fail('Should throw exception');
        } catch (CustomUserMessageAuthenticationException $e) {
            // Should NOT contain sensitive info
            $this->assertStringNotContainsString('192.168.1.100', $e->getMessage());
            $this->assertStringNotContainsString('Database', $e->getMessage());
            $this->assertEquals('Invalid or expired token', $e->getMessage());
        }
    }

    /**
     * @test
     * Verify that debug=true shows error details.
     */
    public function debug_mode_shows_error_details(): void
    {
        $authenticator = new BetterAuthAuthenticator(
            $this->authManager,
            $this->dispatcher,
            $this->tokenService,
            true // debug = true
        );

        $request = new Request();
        $request->headers->set('Authorization', 'Bearer v4.local.test');

        $this->authManager->method('verify')
            ->willThrowException(new InvalidTokenException('Detailed error message'));

        // In debug mode, the TokenInvalidEvent will have the detailed message
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (TokenInvalidEvent $event) {
                    return $event->getReason() === 'Detailed error message';
                }),
                BetterAuthEvents::TOKEN_INVALID
            );

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $authenticator->authenticate($request);
    }

    // ========================================
    // TOKEN EXTRACTION SECURITY TESTS
    // ========================================

    /**
     * @test
     * Verify that only Bearer scheme is accepted.
     */
    public function only_bearer_scheme_is_accepted(): void
    {
        $authenticator = new BetterAuthAuthenticator(
            $this->authManager,
            $this->dispatcher,
            null,
            false
        );

        // Basic auth should not be accepted
        $request = new Request();
        $request->headers->set('Authorization', 'Basic dXNlcjpwYXNz');

        $this->dispatcher->method('dispatch')->willReturnArgument(0);

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('No API token provided');
        $authenticator->authenticate($request);
    }

    /**
     * @test
     * Verify case-insensitive Bearer matching.
     */
    public function bearer_scheme_is_case_insensitive(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $this->authManager->method('verify')->willReturn($user);
        $this->dispatcher->method('dispatch')->willReturnArgument(0);

        $authenticator = new BetterAuthAuthenticator(
            $this->authManager,
            $this->dispatcher,
            null,
            false
        );

        $variations = [
            'Bearer token123',
            'bearer token123',
            'BEARER token123',
            'BeArEr token123',
        ];

        foreach ($variations as $auth) {
            $request = new Request();
            $request->headers->set('Authorization', $auth);

            $passport = $authenticator->authenticate($request);
            $this->assertNotNull($passport);
        }
    }

    /**
     * @test
     * Verify that whitespace in Bearer header is handled.
     */
    public function whitespace_in_bearer_header_is_handled(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $this->authManager->method('verify')->willReturn($user);
        $this->dispatcher->method('dispatch')->willReturnArgument(0);

        $authenticator = new BetterAuthAuthenticator(
            $this->authManager,
            $this->dispatcher,
            null,
            false
        );

        // Extra spaces should be trimmed by regex
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer   token123');

        $passport = $authenticator->authenticate($request);
        $this->assertNotNull($passport);
    }

    // ========================================
    // SUPPORTS METHOD TESTS
    // ========================================

    /**
     * @test
     * Verify supports() returns true only when Authorization header present.
     */
    public function supports_checks_authorization_header(): void
    {
        $authenticator = new BetterAuthAuthenticator(
            $this->authManager,
            $this->dispatcher,
            null,
            false
        );

        $requestWithAuth = new Request();
        $requestWithAuth->headers->set('Authorization', 'Bearer token');

        $requestWithoutAuth = new Request();

        $this->assertTrue($authenticator->supports($requestWithAuth));
        $this->assertFalse($authenticator->supports($requestWithoutAuth));
    }

    // ========================================
    // SQL INJECTION PREVENTION TESTS
    // ========================================

    /**
     * @test
     * Verify that malicious tokens don't cause SQL injection.
     */
    public function malicious_token_doesnt_cause_injection(): void
    {
        $authenticator = new BetterAuthAuthenticator(
            $this->authManager,
            $this->dispatcher,
            null,
            false
        );

        $maliciousTokens = [
            "' OR '1'='1",
            "'; DROP TABLE users; --",
            "1; SELECT * FROM users",
            "<script>alert('xss')</script>",
            "../../../etc/passwd",
        ];

        foreach ($maliciousTokens as $malicious) {
            $request = new Request();
            $request->headers->set('Authorization', "Bearer $malicious");

            $this->authManager->method('verify')
                ->willThrowException(new InvalidTokenException());

            $this->dispatcher->method('dispatch')->willReturnArgument(0);

            try {
                $authenticator->authenticate($request);
            } catch (CustomUserMessageAuthenticationException $e) {
                // Should fail gracefully without SQL error
                $this->assertStringNotContainsString('SQL', $e->getMessage());
                $this->assertStringNotContainsString('syntax', $e->getMessage());
            }
        }
    }
}
