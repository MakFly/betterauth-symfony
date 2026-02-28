<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Symfony\Controller\TokenController;
use BetterAuth\Symfony\Tests\Controller\Trait\ControllerTestTrait;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for TokenController.
 *
 * Tests: me, refresh, logout, revoke-all endpoints.
 */
class TokenControllerTest extends TestCase
{
    use ControllerTestTrait;

    private MockObject&AuthManager $authManager;
    private TokenController $controller;

    protected function setUp(): void
    {
        $this->authManager = $this->createMock(AuthManager::class);
        $this->controller = new TokenController($this->authManager);
        $this->setUpControllerContainer($this->controller);
    }

    private function createAuthenticatedRequest(string $token = 'valid-token'): Request
    {
        $request = new Request();
        $request->headers->set('Authorization', "Bearer $token");
        return $request;
    }

    private function createMockUser(string $id = 'uuid-1', string $email = 'test@example.com'): MockObject&User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getEmail')->willReturn($email);
        $user->method('getRoles')->willReturn(['ROLE_USER']);
        $user->method('getUsername')->willReturn('testuser');
        $user->method('getAvatar')->willReturn(null);
        $user->method('isEmailVerified')->willReturn(false);
        $user->method('getEmailVerifiedAt')->willReturn(null);
        $user->method('getCreatedAt')->willReturn(new DateTimeImmutable('2024-01-01'));
        $user->method('getUpdatedAt')->willReturn(new DateTimeImmutable('2024-01-01'));
        $user->method('getMetadata')->willReturn(null);
        return $user;
    }

    // ========================================
    // ME TESTS
    // ========================================

    /**
     * @test
     */
    public function me_returns_user_data_with_valid_token(): void
    {
        $user = $this->createMockUser();

        $this->authManager->expects($this->once())
            ->method('getCurrentUser')
            ->with('valid-token')
            ->willReturn($user);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->me($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('uuid-1', $data['id']);
        $this->assertSame('test@example.com', $data['email']);
    }

    /**
     * @test
     */
    public function me_returns_401_when_no_token(): void
    {
        $request = new Request();
        $response = $this->controller->me($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('No token provided', $data['error']);
    }

    /**
     * @test
     */
    public function me_returns_401_when_token_invalid(): void
    {
        $this->authManager->method('getCurrentUser')->willReturn(null);

        $request = $this->createAuthenticatedRequest('invalid-token');
        $response = $this->controller->me($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid token', $data['error']);
    }

    /**
     * @test
     */
    public function me_returns_401_on_exception(): void
    {
        $this->authManager->method('getCurrentUser')
            ->willThrowException(new \Exception('Token validation failed'));

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->me($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    // ========================================
    // REFRESH TESTS
    // ========================================

    /**
     * @test
     */
    public function refresh_returns_new_tokens_on_success(): void
    {
        $this->authManager->expects($this->once())
            ->method('refresh')
            ->with('valid-refresh-token')
            ->willReturn([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 3600,
            ]);

        $request = new Request(
            content: json_encode(['refreshToken' => 'valid-refresh-token'])
        );

        $response = $this->controller->refresh($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('new-access-token', $data['access_token']);
    }

    /**
     * @test
     */
    public function refresh_returns_400_when_no_refresh_token(): void
    {
        $request = new Request(content: json_encode([]));
        $response = $this->controller->refresh($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Refresh token is required', $data['error']);
    }

    /**
     * @test
     */
    public function refresh_returns_401_on_expired_token(): void
    {
        $this->authManager->method('refresh')
            ->willThrowException(new \Exception('Refresh token expired'));

        $request = new Request(content: json_encode(['refreshToken' => 'expired-token']));
        $response = $this->controller->refresh($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function refresh_returns_401_on_invalid_token(): void
    {
        $this->authManager->method('refresh')
            ->willThrowException(new \Exception('Invalid refresh token'));

        $request = new Request(content: json_encode(['refreshToken' => 'bad-token']));
        $response = $this->controller->refresh($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    // ========================================
    // LOGOUT TESTS
    // ========================================

    /**
     * @test
     */
    public function logout_returns_200_on_success(): void
    {
        $this->authManager->expects($this->once())
            ->method('signOut')
            ->with('valid-token')
            ->willReturn(true);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->logout($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Logged out successfully', $data['message']);
    }

    /**
     * @test
     */
    public function logout_returns_401_when_no_token(): void
    {
        $request = new Request();
        $response = $this->controller->logout($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('No token provided', $data['error']);
    }

    /**
     * @test
     */
    public function logout_returns_400_on_exception(): void
    {
        $this->authManager->method('signOut')
            ->willThrowException(new \Exception('Logout failed'));

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->logout($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================
    // REVOKE-ALL TESTS
    // ========================================

    /**
     * @test
     */
    public function revoke_all_returns_200_with_count(): void
    {
        $user = $this->createMockUser();

        $this->authManager->method('getCurrentUser')->willReturn($user);
        $this->authManager->expects($this->once())
            ->method('revokeAllTokens')
            ->with('uuid-1')
            ->willReturn(3);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->revokeAll($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('All sessions revoked successfully', $data['message']);
        $this->assertSame(3, $data['count']);
    }

    /**
     * @test
     */
    public function revoke_all_returns_401_when_no_token(): void
    {
        $request = new Request();
        $response = $this->controller->revokeAll($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('No token provided', $data['error']);
    }

    /**
     * @test
     */
    public function revoke_all_returns_401_when_user_not_found(): void
    {
        $this->authManager->method('getCurrentUser')->willReturn(null);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->revokeAll($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid token', $data['error']);
    }
}
