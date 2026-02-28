<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Entities\User;
use BetterAuth\Symfony\Controller\SessionController;
use BetterAuth\Symfony\Tests\Controller\Trait\ControllerTestTrait;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for SessionController.
 *
 * Tests: list sessions, revoke session endpoints.
 */
class SessionControllerTest extends TestCase
{
    use ControllerTestTrait;

    private MockObject&AuthManager $authManager;
    private SessionController $controller;

    protected function setUp(): void
    {
        $this->authManager = $this->createMock(AuthManager::class);
        $this->controller = new SessionController($this->authManager);
        $this->setUpControllerContainer($this->controller);
    }

    private function createAuthenticatedRequest(string $token = 'valid-token'): Request
    {
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");
        return $request;
    }

    private function createMockUser(string $id = 'uuid-1'): MockObject&User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        return $user;
    }

    private function createMockSession(string $token = 'session-token-1'): MockObject&Session
    {
        $session = $this->createMock(Session::class);
        $session->method('getToken')->willReturn($token);
        $session->method('getMetadata')->willReturn([
            'device' => 'Desktop',
            'browser' => 'Chrome',
            'os' => 'Linux',
        ]);
        $session->method('getIpAddress')->willReturn('127.0.0.1');
        $session->method('getCreatedAt')->willReturn(new DateTimeImmutable('2024-01-01 10:00:00'));
        $session->method('getUpdatedAt')->willReturn(new DateTimeImmutable('2024-01-01 12:00:00'));
        $session->method('getExpiresAt')->willReturn(new DateTimeImmutable('2024-02-01 10:00:00'));
        return $session;
    }

    // ========================================
    // LIST SESSIONS TESTS
    // ========================================

    /**
     * @test
     */
    public function list_returns_sessions_with_current_flag(): void
    {
        $user = $this->createMockUser();
        $session1 = $this->createMockSession('current-token');
        $session2 = $this->createMockSession('other-token');

        $this->authManager->method('getCurrentUser')->with('current-token')->willReturn($user);
        $this->authManager->method('getUserSessions')->with('uuid-1')->willReturn([$session1, $session2]);

        $request = $this->createAuthenticatedRequest('current-token');
        $response = $this->controller->list($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertCount(2, $data['sessions']);
        $this->assertTrue($data['sessions'][0]['current']);
        $this->assertFalse($data['sessions'][1]['current']);
    }

    /**
     * @test
     */
    public function list_returns_401_when_no_token(): void
    {
        $request = new Request();
        $response = $this->controller->list($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('No token provided', $data['error']);
    }

    /**
     * @test
     */
    public function list_returns_401_when_token_invalid(): void
    {
        $this->authManager->method('getCurrentUser')->willReturn(null);

        $request = $this->createAuthenticatedRequest('bad-token');
        $response = $this->controller->list($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid token', $data['error']);
    }

    /**
     * @test
     */
    public function list_returns_session_structure(): void
    {
        $user = $this->createMockUser();
        $session = $this->createMockSession('valid-token');

        $this->authManager->method('getCurrentUser')->willReturn($user);
        $this->authManager->method('getUserSessions')->willReturn([$session]);

        $request = $this->createAuthenticatedRequest('valid-token');
        $response = $this->controller->list($request);

        $data = json_decode($response->getContent(), true);
        $sessionData = $data['sessions'][0];

        $this->assertArrayHasKey('id', $sessionData);
        $this->assertArrayHasKey('device', $sessionData);
        $this->assertArrayHasKey('browser', $sessionData);
        $this->assertArrayHasKey('os', $sessionData);
        $this->assertArrayHasKey('ip', $sessionData);
        $this->assertArrayHasKey('current', $sessionData);
        $this->assertArrayHasKey('createdAt', $sessionData);
        $this->assertArrayHasKey('lastActiveAt', $sessionData);
        $this->assertArrayHasKey('expiresAt', $sessionData);
    }

    /**
     * @test
     */
    public function list_returns_400_on_exception(): void
    {
        $user = $this->createMockUser();
        $this->authManager->method('getCurrentUser')->willReturn($user);
        $this->authManager->method('getUserSessions')
            ->willThrowException(new \Exception('Database error'));

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->list($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================
    // REVOKE SESSION TESTS
    // ========================================

    /**
     * @test
     */
    public function revoke_returns_200_on_success(): void
    {
        $user = $this->createMockUser();

        $this->authManager->method('getCurrentUser')->willReturn($user);
        $this->authManager->expects($this->once())
            ->method('revokeSession')
            ->with('uuid-1', 'session-to-revoke');

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->revoke('session-to-revoke', $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Session revoked successfully', $data['message']);
    }

    /**
     * @test
     */
    public function revoke_returns_401_when_no_token(): void
    {
        $request = new Request();
        $response = $this->controller->revoke('session-id', $request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('No token provided', $data['error']);
    }

    /**
     * @test
     */
    public function revoke_returns_401_when_user_not_found(): void
    {
        $this->authManager->method('getCurrentUser')->willReturn(null);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->revoke('session-id', $request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid token', $data['error']);
    }

    /**
     * @test
     */
    public function revoke_returns_400_on_exception(): void
    {
        $user = $this->createMockUser();
        $this->authManager->method('getCurrentUser')->willReturn($user);
        $this->authManager->method('revokeSession')
            ->willThrowException(new \Exception('Session not found'));

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->revoke('non-existent-session', $request);

        $this->assertSame(400, $response->getStatusCode());
    }
}
