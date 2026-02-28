<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Functional;

/**
 * Functional tests for PasswordResetController endpoints.
 *
 * Tests: POST /auth/password/forgot, POST /auth/password/reset, POST /auth/password/verify-token
 */
class PasswordResetControllerFunctionalTest extends AbstractFunctionalTest
{
    // ========================================
    // FORGOT PASSWORD TESTS
    // ========================================

    /**
     * @test
     */
    public function forgot_password_returns_200_for_existing_email(): void
    {
        $this->registerUser('forgot@example.com', 'password123');

        $this->postJson('/auth/password/forgot', ['email' => 'forgot@example.com']);

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertStringContainsString('password reset link', $data['message']);
        $this->assertArrayHasKey('expiresIn', $data);
    }

    /**
     * @test
     */
    public function forgot_password_returns_200_even_for_non_existing_email(): void
    {
        // Security: prevent email enumeration
        $this->postJson('/auth/password/forgot', ['email' => 'nonexistent@example.com']);

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertStringContainsString('password reset link', $data['message']);
        $this->assertArrayNotHasKey('error', $data);
    }

    /**
     * @test
     */
    public function forgot_password_returns_400_when_email_missing(): void
    {
        $this->postJson('/auth/password/forgot', []);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Email is required', $data['error']);
    }

    // ========================================
    // RESET PASSWORD TESTS
    // ========================================

    /**
     * @test
     */
    public function reset_password_returns_400_when_fields_missing(): void
    {
        $this->postJson('/auth/password/reset', ['token' => 'some-token']);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Token and new password are required', $data['error']);
    }

    /**
     * @test
     */
    public function reset_password_returns_400_when_password_too_short(): void
    {
        $this->postJson('/auth/password/reset', [
            'token' => 'some-token',
            'newPassword' => 'short',
        ]);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Password must be at least 8 characters long', $data['error']);
    }

    /**
     * @test
     */
    public function reset_password_returns_400_with_invalid_token(): void
    {
        $this->postJson('/auth/password/reset', [
            'token' => 'invalid-or-expired-token',
            'newPassword' => 'newpassword123',
        ]);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('error', $data);
    }

    // ========================================
    // VERIFY TOKEN TESTS
    // ========================================

    /**
     * @test
     */
    public function verify_reset_token_returns_400_when_token_missing(): void
    {
        $this->postJson('/auth/password/verify-token', []);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Token is required', $data['error']);
    }

    /**
     * @test
     */
    public function verify_reset_token_returns_400_with_invalid_token(): void
    {
        $this->postJson('/auth/password/verify-token', ['token' => 'invalid-token']);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertFalse($data['valid']);
    }
}
