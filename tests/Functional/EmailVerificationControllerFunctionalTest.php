<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Functional;

/**
 * Functional tests for EmailVerificationController endpoints.
 *
 * Tests: POST /auth/email/send-verification, POST /auth/email/verify, GET /auth/email/verification-status
 */
class EmailVerificationControllerFunctionalTest extends AbstractFunctionalTest
{
    // ========================================
    // SEND VERIFICATION TESTS
    // ========================================

    /**
     * @test
     */
    public function send_verification_returns_200_for_unverified_user(): void
    {
        $tokens = $this->registerUser('unverified@example.com', 'password123');
        $accessToken = $tokens['access_token'];

        $this->postJson('/auth/email/send-verification', [], $this->bearerHeaders($accessToken));

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertSame('Verification email sent successfully', $data['message']);
        $this->assertArrayHasKey('expiresIn', $data);
    }

    /**
     * @test
     */
    public function send_verification_returns_401_without_token(): void
    {
        $this->postJson('/auth/email/send-verification', []);

        $this->assertStatusCode(401);
    }

    // ========================================
    // VERIFY EMAIL TESTS
    // ========================================

    /**
     * @test
     */
    public function verify_returns_400_when_token_missing(): void
    {
        $this->postJson('/auth/email/verify', []);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Verification token is required', $data['error']);
    }

    /**
     * @test
     */
    public function verify_returns_400_with_invalid_token(): void
    {
        $this->postJson('/auth/email/verify', ['token' => 'invalid-verification-token']);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('error', $data);
    }

    // ========================================
    // VERIFICATION STATUS TESTS
    // ========================================

    /**
     * @test
     */
    public function verification_status_returns_false_for_new_user(): void
    {
        $tokens = $this->registerUser('status_test@example.com', 'password123');
        $accessToken = $tokens['access_token'];

        $this->getJson('/auth/email/verification-status', $this->bearerHeaders($accessToken));

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertFalse($data['verified']);
        $this->assertSame('status_test@example.com', $data['email']);
    }

    /**
     * @test
     */
    public function verification_status_returns_401_without_token(): void
    {
        $this->getJson('/auth/email/verification-status');

        $this->assertStatusCode(401);
    }
}
