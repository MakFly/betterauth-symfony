<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Functional;

/**
 * Functional tests for MagicLinkController endpoints.
 *
 * Tests: POST /auth/magic-link/send, POST /auth/magic-link/verify, GET /auth/magic-link/verify/{token}
 */
class MagicLinkControllerFunctionalTest extends AbstractFunctionalTest
{
    // ========================================
    // SEND MAGIC LINK TESTS
    // ========================================

    /**
     * @test
     */
    public function send_magic_link_returns_200_for_registered_user(): void
    {
        $this->registerUser('magic@example.com', 'password123');

        $this->postJson('/auth/magic-link/send', ['email' => 'magic@example.com']);

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertSame('Magic link sent successfully', $data['message']);
        $this->assertArrayHasKey('expiresIn', $data);
    }

    /**
     * @test
     */
    public function send_magic_link_returns_400_when_email_missing(): void
    {
        $this->postJson('/auth/magic-link/send', []);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Email is required', $data['error']);
    }

    /**
     * @test
     */
    public function send_magic_link_returns_200_for_non_existing_user(): void
    {
        // Security: MagicLinkProvider returns success even for unknown emails to prevent enumeration
        $this->postJson('/auth/magic-link/send', ['email' => 'unknown@example.com']);

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertSame('Magic link sent successfully', $data['message']);
    }

    // ========================================
    // VERIFY MAGIC LINK (POST) TESTS
    // ========================================

    /**
     * @test
     */
    public function verify_magic_link_post_returns_400_when_token_missing(): void
    {
        $this->postJson('/auth/magic-link/verify', []);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Magic link token is required', $data['error']);
    }

    /**
     * @test
     */
    public function verify_magic_link_post_returns_400_with_invalid_token(): void
    {
        $this->postJson('/auth/magic-link/verify', ['token' => 'invalid-or-expired-magic-token']);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('error', $data);
    }

    // ========================================
    // VERIFY MAGIC LINK (GET) TESTS
    // ========================================

    /**
     * @test
     */
    public function verify_magic_link_get_returns_400_with_invalid_token(): void
    {
        $this->getJson('/auth/magic-link/verify/invalid-magic-link-token');

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('error', $data);
    }
}
