<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\GuestSession;
use BetterAuth\Core\Entities\User;
use BetterAuth\Providers\GuestSessionProvider\GuestSessionProvider;
use BetterAuth\Symfony\Controller\GuestSessionController;
use BetterAuth\Symfony\Tests\Controller\Trait\ControllerTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for GuestSessionController.
 *
 * Tests: create guest session, get guest session, convert to user, delete guest session.
 */
class GuestSessionControllerTest extends TestCase
{
    use ControllerTestTrait;

    private MockObject&GuestSessionProvider $guestSessionProvider;
    private MockObject&AuthManager $authManager;
    private GuestSessionController $controller;

    protected function setUp(): void
    {
        $this->guestSessionProvider = $this->createMock(GuestSessionProvider::class);
        $this->authManager = $this->createMock(AuthManager::class);
        $this->controller = new GuestSessionController(
            $this->guestSessionProvider,
            $this->authManager,
        );
        $this->setUpControllerContainer($this->controller);
    }

    private function createGuestSession(string $id = 'guest-id-1', string $token = 'guest-token-abc'): GuestSession
    {
        return new GuestSession(
            id: $id,
            token: $token,
            deviceInfo: 'Mozilla/5.0',
            ipAddress: '127.0.0.1',
            createdAt: '2026-01-01T10:00:00+00:00',
            expiresAt: '2027-01-01T10:00:00+00:00',
            metadata: null,
        );
    }

    private function createExpiredGuestSession(string $id = 'expired-id'): GuestSession
    {
        return new GuestSession(
            id: $id,
            token: 'expired-token',
            deviceInfo: null,
            ipAddress: '127.0.0.1',
            createdAt: '2023-01-01T10:00:00+00:00',
            expiresAt: '2023-06-01T10:00:00+00:00',
            metadata: null,
        );
    }

    // ========================================
    // CREATE GUEST SESSION TESTS
    // ========================================

    /**
     * @test
     */
    public function create_guest_session_returns_201_with_token(): void
    {
        $guestSession = $this->createGuestSession();

        $this->guestSessionProvider->expects($this->once())
            ->method('createGuestSession')
            ->willReturn($guestSession);

        $request = new Request(content: json_encode(['metadata' => ['cart_id' => 'cart-123']]));
        $response = $this->controller->createGuestSession($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('guest-token-abc', $data['guest_token']);
        $this->assertArrayHasKey('expires_at', $data);
        $this->assertArrayHasKey('created_at', $data);
    }

    /**
     * @test
     */
    public function create_guest_session_passes_device_info_from_request(): void
    {
        $guestSession = $this->createGuestSession();

        $this->guestSessionProvider->expects($this->once())
            ->method('createGuestSession')
            ->with('Custom Device', $this->anything(), null)
            ->willReturn($guestSession);

        $request = new Request(content: json_encode(['deviceInfo' => 'Custom Device']));
        $this->controller->createGuestSession($request);
    }

    /**
     * @test
     */
    public function create_guest_session_returns_500_on_exception(): void
    {
        $this->guestSessionProvider->method('createGuestSession')
            ->willThrowException(new \Exception('Database error'));

        $request = new Request(content: json_encode([]));
        $response = $this->controller->createGuestSession($request);

        $this->assertSame(500, $response->getStatusCode());
    }

    // ========================================
    // GET GUEST SESSION TESTS
    // ========================================

    /**
     * @test
     */
    public function get_guest_session_returns_session_data(): void
    {
        $guestSession = $this->createGuestSession();

        $this->guestSessionProvider->method('getGuestSession')
            ->with('guest-token-abc')
            ->willReturn($guestSession);

        $response = $this->controller->getGuestSession('guest-token-abc');

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('guest-id-1', $data['id']);
        $this->assertSame('guest-token-abc', $data['token']);
        $this->assertArrayHasKey('device_info', $data);
        $this->assertArrayHasKey('ip_address', $data);
        $this->assertArrayHasKey('created_at', $data);
        $this->assertArrayHasKey('expires_at', $data);
    }

    /**
     * @test
     */
    public function get_guest_session_returns_404_when_not_found(): void
    {
        $this->guestSessionProvider->method('getGuestSession')->willReturn(null);

        $response = $this->controller->getGuestSession('non-existent-token');

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Guest session not found', $data['error']);
    }

    /**
     * @test
     */
    public function get_guest_session_returns_410_when_expired(): void
    {
        $expiredSession = $this->createExpiredGuestSession();
        $this->guestSessionProvider->method('getGuestSession')->willReturn($expiredSession);

        $response = $this->controller->getGuestSession('expired-token');

        $this->assertSame(410, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Guest session has expired', $data['error']);
    }

    // ========================================
    // CONVERT TO USER TESTS
    // ========================================

    /**
     * @test
     */
    public function convert_to_user_returns_201_on_success(): void
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('user-uuid-1');
        $user->method('getEmail')->willReturn('converted@example.com');
        $user->method('getUsername')->willReturn(null);

        $this->guestSessionProvider->expects($this->once())
            ->method('convertToUser')
            ->with('guest-token-abc', $this->anything())
            ->willReturn($user);

        $this->authManager->method('signIn')->willReturn([
            'access_token' => 'new-access-token',
            'refresh_token' => 'new-refresh-token',
            'expires_in' => 3600,
        ]);

        $request = new Request(content: json_encode([
            'guest_token' => 'guest-token-abc',
            'email' => 'converted@example.com',
            'password' => 'securepassword123',
        ]));
        $response = $this->controller->convertToUser($request);

        $this->assertSame(201, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('user', $data);
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertStringContainsString('successfully', $data['message']);
    }

    /**
     * @test
     */
    public function convert_to_user_returns_400_when_guest_token_missing(): void
    {
        $request = new Request(content: json_encode(['email' => 'test@example.com']));
        $response = $this->controller->convertToUser($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('guest_token is required', $data['error']);
    }

    /**
     * @test
     */
    public function convert_to_user_returns_400_when_email_missing(): void
    {
        $request = new Request(content: json_encode(['guest_token' => 'guest-token-abc']));
        $response = $this->controller->convertToUser($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('email is required', $data['error']);
    }

    /**
     * @test
     */
    public function convert_to_user_returns_400_on_runtime_exception(): void
    {
        $this->guestSessionProvider->method('convertToUser')
            ->willThrowException(new \RuntimeException('Guest session not found'));

        $request = new Request(content: json_encode([
            'guest_token' => 'invalid-token',
            'email' => 'test@example.com',
        ]));
        $response = $this->controller->convertToUser($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * @test
     */
    public function convert_to_user_returns_500_on_generic_exception(): void
    {
        $this->guestSessionProvider->method('convertToUser')
            ->willThrowException(new \Exception('Database error'));

        $request = new Request(content: json_encode([
            'guest_token' => 'guest-token-abc',
            'email' => 'test@example.com',
        ]));
        $response = $this->controller->convertToUser($request);

        $this->assertSame(500, $response->getStatusCode());
    }

    // ========================================
    // DELETE GUEST SESSION TESTS
    // ========================================

    /**
     * @test
     */
    public function delete_guest_session_returns_200_on_success(): void
    {
        $guestSession = $this->createGuestSession();

        $this->guestSessionProvider->method('getGuestSession')->willReturn($guestSession);
        $this->guestSessionProvider->expects($this->once())
            ->method('deleteGuestSession')
            ->with('guest-id-1')
            ->willReturn(true);

        $response = $this->controller->deleteGuestSession('guest-token-abc');

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Guest session deleted successfully', $data['message']);
    }

    /**
     * @test
     */
    public function delete_guest_session_returns_404_when_not_found(): void
    {
        $this->guestSessionProvider->method('getGuestSession')->willReturn(null);

        $response = $this->controller->deleteGuestSession('non-existent-token');

        $this->assertSame(404, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Guest session not found', $data['error']);
    }

    /**
     * @test
     */
    public function delete_guest_session_returns_500_when_deletion_fails(): void
    {
        $guestSession = $this->createGuestSession();

        $this->guestSessionProvider->method('getGuestSession')->willReturn($guestSession);
        $this->guestSessionProvider->method('deleteGuestSession')->willReturn(false);

        $response = $this->controller->deleteGuestSession('guest-token-abc');

        $this->assertSame(500, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Failed to delete guest session', $data['error']);
    }
}
