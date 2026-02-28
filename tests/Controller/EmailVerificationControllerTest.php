<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Providers\EmailVerificationProvider\EmailVerificationProvider;
use BetterAuth\Symfony\Controller\EmailVerificationController;
use BetterAuth\Symfony\Tests\Controller\Trait\ControllerTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for EmailVerificationController.
 *
 * Tests: send verification email, verify email, verification status.
 */
class EmailVerificationControllerTest extends TestCase
{
    use ControllerTestTrait;

    private MockObject&AuthManager $authManager;
    private MockObject&EmailVerificationProvider $emailVerificationProvider;
    private EmailVerificationController $controller;

    protected function setUp(): void
    {
        $this->authManager = $this->createMock(AuthManager::class);
        $this->emailVerificationProvider = $this->createMock(EmailVerificationProvider::class);
        $this->controller = new EmailVerificationController(
            $this->authManager,
            $this->emailVerificationProvider,
            null,
            'http://localhost:5173',
        );
        $this->setUpControllerContainer($this->controller);
    }

    private function createAuthenticatedRequest(string $token = 'valid-token', array $body = []): Request
    {
        $request = new Request(content: json_encode($body));
        $request->headers->set('Authorization', "Bearer $token");
        return $request;
    }

    private function createMockUser(
        string $id = 'uuid-1',
        string $email = 'test@example.com',
        bool $verified = false
    ): MockObject&User {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getEmail')->willReturn($email);
        $user->method('isEmailVerified')->willReturn($verified);
        return $user;
    }

    // ========================================
    // SEND VERIFICATION TESTS
    // ========================================

    /**
     * @test
     */
    public function send_verification_returns_200_on_success(): void
    {
        $user = $this->createMockUser(verified: false);
        $this->authManager->method('getCurrentUser')->willReturn($user);

        $this->emailVerificationProvider->expects($this->once())
            ->method('sendVerificationEmail')
            ->with('uuid-1', 'test@example.com', 'http://localhost:5173/auth/email/verify')
            ->willReturn(['expiresIn' => 3600]);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->sendVerification($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Verification email sent successfully', $data['message']);
        $this->assertSame(3600, $data['expiresIn']);
    }

    /**
     * @test
     */
    public function send_verification_returns_401_when_no_token(): void
    {
        $request = new Request();
        $response = $this->controller->sendVerification($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('No token provided', $data['error']);
    }

    /**
     * @test
     */
    public function send_verification_returns_401_when_user_not_found(): void
    {
        $this->authManager->method('getCurrentUser')->willReturn(null);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->sendVerification($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Invalid token', $data['error']);
    }

    /**
     * @test
     */
    public function send_verification_returns_400_when_already_verified(): void
    {
        $user = $this->createMockUser(verified: true);
        $this->authManager->method('getCurrentUser')->willReturn($user);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->sendVerification($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Email already verified', $data['error']);
    }

    /**
     * @test
     */
    public function send_verification_uses_custom_callback_url(): void
    {
        $user = $this->createMockUser(verified: false);
        $this->authManager->method('getCurrentUser')->willReturn($user);

        $this->emailVerificationProvider->expects($this->once())
            ->method('sendVerificationEmail')
            ->with('uuid-1', 'test@example.com', 'https://myapp.com/verify')
            ->willReturn(['expiresIn' => 3600]);

        $request = $this->createAuthenticatedRequest('valid-token', ['callbackUrl' => 'https://myapp.com/verify']);
        $response = $this->controller->sendVerification($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    // ========================================
    // VERIFY EMAIL TESTS
    // ========================================

    /**
     * @test
     */
    public function verify_returns_200_on_valid_token(): void
    {
        $this->emailVerificationProvider->expects($this->once())
            ->method('verifyEmail')
            ->with('valid-verification-token')
            ->willReturn(['success' => true]);

        $request = new Request(content: json_encode(['token' => 'valid-verification-token']));
        $response = $this->controller->verify($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Email verified successfully', $data['message']);
        $this->assertTrue($data['verified']);
    }

    /**
     * @test
     */
    public function verify_returns_400_when_token_missing(): void
    {
        $request = new Request(content: json_encode([]));
        $response = $this->controller->verify($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Verification token is required', $data['error']);
    }

    /**
     * @test
     */
    public function verify_returns_400_on_invalid_token(): void
    {
        $this->emailVerificationProvider->method('verifyEmail')
            ->willReturn(['success' => false, 'error' => 'Token has expired']);

        $request = new Request(content: json_encode(['token' => 'expired-token']));
        $response = $this->controller->verify($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Token has expired', $data['error']);
    }

    /**
     * @test
     */
    public function verify_returns_400_on_exception(): void
    {
        $this->emailVerificationProvider->method('verifyEmail')
            ->willThrowException(new \Exception('Database error'));

        $request = new Request(content: json_encode(['token' => 'some-token']));
        $response = $this->controller->verify($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================
    // VERIFICATION STATUS TESTS
    // ========================================

    /**
     * @test
     */
    public function verification_status_returns_verified_true(): void
    {
        $user = $this->createMockUser(verified: true);
        $this->authManager->method('getCurrentUser')->willReturn($user);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->verificationStatus($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['verified']);
        $this->assertSame('test@example.com', $data['email']);
    }

    /**
     * @test
     */
    public function verification_status_returns_verified_false_for_unverified_user(): void
    {
        $user = $this->createMockUser(verified: false);
        $this->authManager->method('getCurrentUser')->willReturn($user);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->verificationStatus($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['verified']);
    }

    /**
     * @test
     */
    public function verification_status_returns_401_when_no_token(): void
    {
        $request = new Request();
        $response = $this->controller->verificationStatus($request);

        $this->assertSame(401, $response->getStatusCode());
    }

    /**
     * @test
     */
    public function verification_status_returns_401_when_user_not_found(): void
    {
        $this->authManager->method('getCurrentUser')->willReturn(null);

        $request = $this->createAuthenticatedRequest();
        $response = $this->controller->verificationStatus($request);

        $this->assertSame(401, $response->getStatusCode());
    }
}
