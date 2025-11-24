<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Security;

use BetterAuth\Core\Entities\User;
use BetterAuth\Symfony\Event\AuthenticationFailureEvent;
use BetterAuth\Symfony\Event\AuthenticationSuccessEvent;
use BetterAuth\Symfony\Event\TokenAuthenticatedEvent;
use BetterAuth\Symfony\Event\TokenCreatedEvent;
use BetterAuth\Symfony\Event\TokenDecodedEvent;
use BetterAuth\Symfony\Event\TokenExpiredEvent;
use BetterAuth\Symfony\Event\TokenInvalidEvent;
use BetterAuth\Symfony\Event\TokenNotFoundEvent;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

/**
 * Security tests for BetterAuth Events.
 *
 * These tests verify:
 * - Events contain correct data
 * - Response modification works
 * - Events can be used for security logging
 */
class EventSecurityTest extends TestCase
{
    // ========================================
    // TOKEN CREATED EVENT TESTS
    // ========================================

    /**
     * @test
     * Verify TokenCreatedEvent allows payload modification.
     */
    public function token_created_event_allows_payload_modification(): void
    {
        $user = $this->createMock(User::class);
        $originalPayload = ['sub' => 'user123', 'type' => 'access'];

        $event = new TokenCreatedEvent($originalPayload, $user);

        // Modify payload
        $newPayload = $event->getPayload();
        $newPayload['custom_claim'] = 'custom_value';
        $newPayload['roles'] = ['ROLE_ADMIN'];
        $event->setPayload($newPayload);

        $this->assertArrayHasKey('custom_claim', $event->getPayload());
        $this->assertEquals('custom_value', $event->getPayload()['custom_claim']);
        $this->assertEquals(['ROLE_ADMIN'], $event->getPayload()['roles']);
    }

    /**
     * @test
     * Verify TokenCreatedEvent provides access to user.
     */
    public function token_created_event_provides_user(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');
        $user->method('getEmail')->willReturn('test@example.com');

        $event = new TokenCreatedEvent(['sub' => 'user-123'], $user);

        $this->assertEquals('user-123', $event->getUser()->getId());
        $this->assertEquals('test@example.com', $event->getUser()->getEmail());
    }

    // ========================================
    // TOKEN DECODED EVENT TESTS
    // ========================================

    /**
     * @test
     * Verify TokenDecodedEvent can mark token as invalid.
     */
    public function token_decoded_event_can_mark_invalid(): void
    {
        $event = new TokenDecodedEvent(
            ['sub' => 'user123', 'type' => 'access'],
            'v4.local.token'
        );

        $this->assertTrue($event->isValid());

        $event->markAsInvalid();

        $this->assertFalse($event->isValid());
    }

    /**
     * @test
     * Verify TokenDecodedEvent provides payload for validation.
     */
    public function token_decoded_event_provides_payload(): void
    {
        $payload = [
            'sub' => 'user123',
            'type' => 'access',
            'iat' => time(),
            'exp' => time() + 3600,
            'custom' => 'data',
        ];

        $event = new TokenDecodedEvent($payload, 'v4.local.token');

        $this->assertEquals($payload, $event->getPayload());
        $this->assertEquals('v4.local.token', $event->getToken());
    }

    // ========================================
    // TOKEN INVALID EVENT TESTS
    // ========================================

    /**
     * @test
     * Verify TokenInvalidEvent contains reason.
     */
    public function token_invalid_event_contains_reason(): void
    {
        $event = new TokenInvalidEvent(
            'Signature verification failed',
            'v4.local.invalid',
            new \Exception('Original error')
        );

        $this->assertEquals('Signature verification failed', $event->getReason());
        $this->assertEquals('v4.local.invalid', $event->getToken());
        $this->assertInstanceOf(\Exception::class, $event->getException());
    }

    /**
     * @test
     * Verify TokenInvalidEvent allows custom response.
     */
    public function token_invalid_event_allows_custom_response(): void
    {
        $event = new TokenInvalidEvent('Invalid token');

        $this->assertNull($event->getResponse());

        $customResponse = new JsonResponse(['error' => 'Custom error'], 401);
        $event->setResponse($customResponse);

        $this->assertSame($customResponse, $event->getResponse());
    }

    // ========================================
    // TOKEN EXPIRED EVENT TESTS
    // ========================================

    /**
     * @test
     * Verify TokenExpiredEvent contains expiration info.
     */
    public function token_expired_event_contains_expiration(): void
    {
        $expiredAt = new \DateTimeImmutable('-1 hour');
        $event = new TokenExpiredEvent('v4.local.expired', $expiredAt);

        $this->assertEquals('v4.local.expired', $event->getToken());
        $this->assertEquals($expiredAt, $event->getExpiredAt());
    }

    /**
     * @test
     * Verify TokenExpiredEvent allows custom response with refresh instructions.
     */
    public function token_expired_event_allows_refresh_response(): void
    {
        $event = new TokenExpiredEvent('v4.local.expired');

        $response = new JsonResponse([
            'error' => 'Token expired',
            'code' => 'TOKEN_EXPIRED',
            'refresh_url' => '/auth/refresh',
        ], 401);
        $event->setResponse($response);

        $this->assertSame($response, $event->getResponse());
    }

    // ========================================
    // TOKEN NOT FOUND EVENT TESTS
    // ========================================

    /**
     * @test
     * Verify TokenNotFoundEvent contains context.
     */
    public function token_not_found_event_contains_context(): void
    {
        $event = new TokenNotFoundEvent('Authorization header missing');

        $this->assertEquals('Authorization header missing', $event->getContext());
    }

    // ========================================
    // AUTHENTICATION SUCCESS EVENT TESTS
    // ========================================

    /**
     * @test
     * Verify AuthenticationSuccessEvent provides user data.
     */
    public function authentication_success_event_provides_user(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');
        $user->method('getEmail')->willReturn('test@example.com');

        $event = new AuthenticationSuccessEvent($user, [
            'access_token' => 'token',
            'token_type' => 'Bearer',
        ]);

        $this->assertEquals('user-123', $event->getUser()->getId());
        $this->assertArrayHasKey('access_token', $event->getData());
    }

    /**
     * @test
     * Verify AuthenticationSuccessEvent allows data modification.
     */
    public function authentication_success_event_allows_data_modification(): void
    {
        $user = $this->createMock(User::class);
        $event = new AuthenticationSuccessEvent($user, ['original' => 'data']);

        $event->setData([
            'original' => 'data',
            'added' => 'value',
            'user_roles' => ['ROLE_ADMIN'],
        ]);

        $this->assertArrayHasKey('added', $event->getData());
        $this->assertEquals('value', $event->getData()['added']);
    }

    // ========================================
    // AUTHENTICATION FAILURE EVENT TESTS
    // ========================================

    /**
     * @test
     * Verify AuthenticationFailureEvent provides exception.
     */
    public function authentication_failure_event_provides_exception(): void
    {
        $exception = new AuthenticationException('Invalid credentials');
        $response = new JsonResponse(['error' => 'Unauthorized'], 401);

        $event = new AuthenticationFailureEvent($exception, $response);

        $this->assertSame($exception, $event->getException());
        $this->assertEquals('Invalid credentials', $event->getException()->getMessage());
    }

    /**
     * @test
     * Verify AuthenticationFailureEvent allows response modification.
     */
    public function authentication_failure_event_allows_response_modification(): void
    {
        $exception = new AuthenticationException('Error');
        $originalResponse = new JsonResponse(['error' => 'Original'], 401);

        $event = new AuthenticationFailureEvent($exception, $originalResponse);

        $newResponse = new JsonResponse([
            'error' => 'Modified',
            'support_url' => 'https://help.example.com',
        ], 401);
        $event->setResponse($newResponse);

        $this->assertSame($newResponse, $event->getResponse());
    }

    // ========================================
    // TOKEN AUTHENTICATED EVENT TESTS
    // ========================================

    /**
     * @test
     * Verify TokenAuthenticatedEvent provides all data.
     */
    public function token_authenticated_event_provides_all_data(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-123');

        $payload = ['sub' => 'user-123', 'type' => 'access'];
        $token = 'v4.local.valid_token';

        $event = new TokenAuthenticatedEvent($payload, $user, $token);

        $this->assertEquals($payload, $event->getPayload());
        $this->assertEquals('user-123', $event->getUser()->getId());
        $this->assertEquals($token, $event->getToken());
    }

    // ========================================
    // SECURITY LOGGING USE CASES
    // ========================================

    /**
     * @test
     * Demonstrate how to use events for security logging.
     */
    public function events_can_be_used_for_security_logging(): void
    {
        $logs = [];

        // Simulate logging listener for TokenInvalidEvent
        $event = new TokenInvalidEvent(
            'Invalid signature',
            'v4.local.malicious',
            new \Exception('Attack detected')
        );

        // Security log entry
        $logs[] = [
            'type' => 'SECURITY_ALERT',
            'event' => 'TOKEN_INVALID',
            'reason' => $event->getReason(),
            'token_prefix' => substr($event->getToken() ?? '', 0, 20),
            'timestamp' => new \DateTimeImmutable(),
        ];

        $this->assertCount(1, $logs);
        $this->assertEquals('SECURITY_ALERT', $logs[0]['type']);
        $this->assertEquals('Invalid signature', $logs[0]['reason']);
    }

    /**
     * @test
     * Demonstrate how to detect suspicious patterns.
     */
    public function events_can_detect_suspicious_patterns(): void
    {
        // Simulate multiple expired token attempts (potential attack)
        $expiredAttempts = [];

        for ($i = 0; $i < 10; $i++) {
            $expiredAttempts[] = new TokenExpiredEvent(
                'v4.local.expired_' . $i,
                new \DateTimeImmutable('-1 hour')
            );
        }

        // Security rule: More than 5 expired tokens in short period = suspicious
        $this->assertGreaterThan(5, count($expiredAttempts));

        // This would trigger an alert in a real system
        $isSuspicious = count($expiredAttempts) > 5;
        $this->assertTrue($isSuspicious);
    }
}
