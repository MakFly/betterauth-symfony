<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Controller;

use BetterAuth\Providers\PasswordResetProvider\PasswordResetProvider;
use BetterAuth\Symfony\Controller\PasswordResetController;
use BetterAuth\Symfony\Tests\Controller\Trait\ControllerTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Unit tests for PasswordResetController.
 *
 * Tests: forgot password, reset password, verify reset token.
 */
class PasswordResetControllerTest extends TestCase
{
    use ControllerTestTrait;

    private MockObject&PasswordResetProvider $passwordResetProvider;
    private PasswordResetController $controller;

    protected function setUp(): void
    {
        $this->passwordResetProvider = $this->createMock(PasswordResetProvider::class);
        $this->controller = new PasswordResetController(
            $this->passwordResetProvider,
            null,
            'http://localhost:5173',
        );
        $this->setUpControllerContainer($this->controller);
    }

    private function createJsonRequest(array $data): Request
    {
        return new Request(content: json_encode($data));
    }

    // ========================================
    // FORGOT PASSWORD TESTS
    // ========================================

    /**
     * @test
     */
    public function forgot_password_returns_200_for_existing_email(): void
    {
        $this->passwordResetProvider->expects($this->once())
            ->method('sendResetEmail')
            ->with('test@example.com', 'http://localhost:5173/reset-password');

        $request = $this->createJsonRequest(['email' => 'test@example.com']);
        $response = $this->controller->forgotPassword($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('password reset link has been sent', $data['message']);
        $this->assertSame(3600, $data['expiresIn']);
    }

    /**
     * @test
     */
    public function forgot_password_returns_400_when_email_missing(): void
    {
        $request = $this->createJsonRequest([]);
        $response = $this->controller->forgotPassword($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Email is required', $data['error']);
    }

    /**
     * @test
     */
    public function forgot_password_does_not_reveal_non_existing_email(): void
    {
        $this->passwordResetProvider->method('sendResetEmail')
            ->willThrowException(new \Exception('User not found'));

        $request = $this->createJsonRequest(['email' => 'nonexistent@example.com']);
        $response = $this->controller->forgotPassword($request);

        // Should still return 200 to prevent email enumeration
        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('password reset link has been sent', $data['message']);
        // Must NOT contain error details
        $this->assertArrayNotHasKey('error', $data);
    }

    // ========================================
    // RESET PASSWORD TESTS
    // ========================================

    /**
     * @test
     */
    public function reset_password_returns_200_on_success(): void
    {
        $this->passwordResetProvider->expects($this->once())
            ->method('resetPassword')
            ->with('valid-reset-token', 'newpassword123')
            ->willReturn(['success' => true]);

        $request = $this->createJsonRequest([
            'token' => 'valid-reset-token',
            'newPassword' => 'newpassword123',
        ]);
        $response = $this->controller->resetPassword($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Password reset successfully', $data['message']);
        $this->assertTrue($data['success']);
    }

    /**
     * @test
     */
    public function reset_password_returns_400_when_fields_missing(): void
    {
        $request = $this->createJsonRequest(['token' => 'some-token']);
        $response = $this->controller->resetPassword($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Token and new password are required', $data['error']);
    }

    /**
     * @test
     */
    public function reset_password_returns_400_when_password_too_short(): void
    {
        $request = $this->createJsonRequest([
            'token' => 'valid-token',
            'newPassword' => 'short',
        ]);
        $response = $this->controller->resetPassword($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Password must be at least 8 characters long', $data['error']);
    }

    /**
     * @test
     */
    public function reset_password_returns_400_on_invalid_token(): void
    {
        $this->passwordResetProvider->method('resetPassword')
            ->willReturn(['success' => false, 'error' => 'Token has expired']);

        $request = $this->createJsonRequest([
            'token' => 'expired-token',
            'newPassword' => 'newpassword123',
        ]);
        $response = $this->controller->resetPassword($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Token has expired', $data['error']);
    }

    /**
     * @test
     */
    public function reset_password_returns_400_on_exception(): void
    {
        $this->passwordResetProvider->method('resetPassword')
            ->willThrowException(new \Exception('Database error'));

        $request = $this->createJsonRequest([
            'token' => 'some-token',
            'newPassword' => 'newpassword123',
        ]);
        $response = $this->controller->resetPassword($request);

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================
    // VERIFY RESET TOKEN TESTS
    // ========================================

    /**
     * @test
     */
    public function verify_reset_token_returns_valid_with_email(): void
    {
        $this->passwordResetProvider->expects($this->once())
            ->method('verifyResetToken')
            ->with('valid-token')
            ->willReturn(['valid' => true, 'email' => 'test@example.com']);

        $request = $this->createJsonRequest(['token' => 'valid-token']);
        $response = $this->controller->verifyResetToken($request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertTrue($data['valid']);
        $this->assertSame('test@example.com', $data['email']);
    }

    /**
     * @test
     */
    public function verify_reset_token_returns_400_when_token_missing(): void
    {
        $request = $this->createJsonRequest([]);
        $response = $this->controller->verifyResetToken($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame('Token is required', $data['error']);
    }

    /**
     * @test
     */
    public function verify_reset_token_returns_400_on_invalid_token(): void
    {
        $this->passwordResetProvider->method('verifyResetToken')
            ->willReturn(['valid' => false]);

        $request = $this->createJsonRequest(['token' => 'invalid-token']);
        $response = $this->controller->verifyResetToken($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertFalse($data['valid']);
        $this->assertSame('Invalid or expired token', $data['error']);
    }
}
