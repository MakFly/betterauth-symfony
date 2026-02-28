<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Functional;

/**
 * Functional tests for TwoFactorController endpoints.
 *
 * Tests: POST /auth/2fa/setup, POST /auth/2fa/validate, POST /auth/2fa/verify,
 *        POST /auth/2fa/disable, POST /auth/2fa/backup-codes/regenerate, GET /auth/2fa/status
 */
class TwoFactorControllerFunctionalTest extends AbstractFunctionalTest
{
    // ========================================
    // SETUP TESTS
    // ========================================

    /**
     * @test
     */
    public function setup_returns_qr_code_when_authenticated(): void
    {
        $tokens = $this->registerUser('2fa_setup@example.com', 'password123');
        $accessToken = $tokens['access_token'];

        $this->postJson('/auth/2fa/setup', [], $this->bearerHeaders($accessToken));

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('secret', $data);
        $this->assertArrayHasKey('qrCode', $data);
        $this->assertArrayHasKey('backupCodes', $data);
        $this->assertNotEmpty($data['secret']);
    }

    /**
     * @test
     */
    public function setup_returns_401_without_token(): void
    {
        $this->postJson('/auth/2fa/setup', []);

        $this->assertStatusCode(401);
    }

    // ========================================
    // VALIDATE TESTS
    // ========================================

    /**
     * @test
     */
    public function validate_returns_400_when_code_missing(): void
    {
        $tokens = $this->registerUser('2fa_validate@example.com', 'password123');

        $this->postJson('/auth/2fa/validate', [], $this->bearerHeaders($tokens['access_token']));

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Verification code is required', $data['error']);
    }

    /**
     * @test
     */
    public function validate_returns_400_with_invalid_code(): void
    {
        $tokens = $this->registerUser('2fa_validate2@example.com', 'password123');

        // Setup first
        $this->postJson('/auth/2fa/setup', [], $this->bearerHeaders($tokens['access_token']));

        // Try to enable with wrong code
        $this->postJson(
            '/auth/2fa/validate',
            ['code' => '000000'],
            $this->bearerHeaders($tokens['access_token'])
        );

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Invalid verification code', $data['error']);
    }

    /**
     * @test
     */
    public function validate_returns_401_without_token(): void
    {
        $this->postJson('/auth/2fa/validate', ['code' => '123456']);

        $this->assertStatusCode(401);
    }

    // ========================================
    // VERIFY TESTS
    // ========================================

    /**
     * @test
     */
    public function verify_returns_400_when_code_missing(): void
    {
        $tokens = $this->registerUser('2fa_verify@example.com', 'password123');

        $this->postJson('/auth/2fa/verify', [], $this->bearerHeaders($tokens['access_token']));

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Verification code is required', $data['error']);
    }

    /**
     * @test
     */
    public function verify_returns_401_without_token(): void
    {
        $this->postJson('/auth/2fa/verify', ['code' => '123456']);

        $this->assertStatusCode(401);
    }

    // ========================================
    // DISABLE TESTS
    // ========================================

    /**
     * @test
     */
    public function disable_returns_400_when_backup_code_missing(): void
    {
        $tokens = $this->registerUser('2fa_disable@example.com', 'password123');

        $this->postJson('/auth/2fa/disable', [], $this->bearerHeaders($tokens['access_token']));

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Backup code is required to disable 2FA', $data['error']);
    }

    /**
     * @test
     */
    public function disable_returns_401_without_token(): void
    {
        $this->postJson('/auth/2fa/disable', ['backupCode' => 'code123']);

        $this->assertStatusCode(401);
    }

    // ========================================
    // STATUS TESTS
    // ========================================

    /**
     * @test
     */
    public function status_returns_2fa_disabled_by_default(): void
    {
        $tokens = $this->registerUser('2fa_status@example.com', 'password123');

        $this->getJson('/auth/2fa/status', $this->bearerHeaders($tokens['access_token']));

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertFalse($data['enabled']);
        $this->assertArrayHasKey('backupCodesRemaining', $data);
    }

    /**
     * @test
     */
    public function status_returns_401_without_token(): void
    {
        $this->getJson('/auth/2fa/status');

        $this->assertStatusCode(401);
    }

    // ========================================
    // RESET TESTS
    // ========================================

    /**
     * @test
     */
    public function reset_returns_400_when_backup_code_missing(): void
    {
        $tokens = $this->registerUser('2fa_reset@example.com', 'password123');

        $this->postJson('/auth/2fa/reset', [], $this->bearerHeaders($tokens['access_token']));

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Backup code is required to reset 2FA', $data['error']);
    }
}
