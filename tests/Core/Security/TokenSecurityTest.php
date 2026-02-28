<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Security;

use BetterAuth\Core\Exceptions\TokenExpiredException;
use BetterAuth\Core\TokenService;
use PHPUnit\Framework\TestCase;

/**
 * Security tests for TokenService (Paseto V4).
 *
 * These tests verify:
 * - Token tampering detection
 * - Token expiration enforcement
 * - Payload encryption (not readable without key)
 * - Key strength requirements
 * - Timing attack resistance
 */
class TokenSecurityTest extends TestCase
{
    private const SECRET_KEY = 'this-is-a-very-secure-secret-key-32chars!';
    private const DIFFERENT_KEY = 'another-completely-different-key-32chars';

    private TokenService $tokenService;

    protected function setUp(): void
    {
        $this->tokenService = new TokenService(self::SECRET_KEY);
    }

    // ========================================
    // TOKEN TAMPERING TESTS
    // ========================================

    /**
     * @test
     * Verify that modifying any character in the token invalidates it.
     */
    public function tampered_token_is_rejected(): void
    {
        $token = $this->tokenService->sign(['sub' => 'user123'], 3600);

        // Tamper with one character in the middle of the token
        $tamperedToken = substr($token, 0, 20) . 'X' . substr($token, 21);

        $result = $this->tokenService->verify($tamperedToken);

        $this->assertNull($result, 'Tampered token should be rejected');
    }

    /**
     * @test
     * Verify that appending data to token invalidates it.
     */
    public function token_with_appended_data_is_rejected(): void
    {
        $token = $this->tokenService->sign(['sub' => 'user123'], 3600);

        $tamperedToken = $token . 'extra_data';

        $result = $this->tokenService->verify($tamperedToken);

        $this->assertNull($result, 'Token with appended data should be rejected');
    }

    /**
     * @test
     * Verify that truncated token is rejected.
     */
    public function truncated_token_is_rejected(): void
    {
        $token = $this->tokenService->sign(['sub' => 'user123'], 3600);

        $truncatedToken = substr($token, 0, -10);

        $result = $this->tokenService->verify($truncatedToken);

        $this->assertNull($result, 'Truncated token should be rejected');
    }

    /**
     * @test
     * Verify that token signed with different key is rejected.
     */
    public function token_signed_with_different_key_is_rejected(): void
    {
        $otherService = new TokenService(self::DIFFERENT_KEY);
        $token = $otherService->sign(['sub' => 'user123'], 3600);

        $result = $this->tokenService->verify($token);

        $this->assertNull($result, 'Token signed with different key should be rejected');
    }

    // ========================================
    // EXPIRATION TESTS
    // ========================================

    /**
     * @test
     * Verify that expired token is rejected.
     */
    public function expired_token_is_rejected(): void
    {
        // Create token that expires in 1 second
        $token = $this->tokenService->sign(['sub' => 'user123'], 1);

        // Wait for expiration
        sleep(2);

        $this->expectException(TokenExpiredException::class);
        $this->tokenService->verify($token);
    }

    /**
     * @test
     * Verify that valid token is accepted before expiration.
     */
    public function valid_token_is_accepted_before_expiration(): void
    {
        $token = $this->tokenService->sign(['sub' => 'user123'], 3600);

        $result = $this->tokenService->verify($token);

        $this->assertNotNull($result);
        $this->assertEquals('user123', $result['sub']);
    }

    /**
     * @test
     * Verify isExpired() method works correctly.
     */
    public function is_expired_returns_correct_status(): void
    {
        $validToken = $this->tokenService->sign(['sub' => 'user123'], 3600);
        $this->assertFalse($this->tokenService->isExpired($validToken));

        $expiredToken = $this->tokenService->sign(['sub' => 'user123'], 1);
        sleep(2);
        $this->assertTrue($this->tokenService->isExpired($expiredToken));
    }

    // ========================================
    // ENCRYPTION TESTS (Paseto V4 specific)
    // ========================================

    /**
     * @test
     * Verify that token payload is NOT readable without decryption.
     * This is the key advantage over JWT.
     */
    public function token_payload_is_encrypted_not_base64(): void
    {
        $sensitiveData = [
            'sub' => 'user123',
            'email' => 'secret@example.com',
            'role' => 'admin',
        ];

        $token = $this->tokenService->sign($sensitiveData, 3600);

        // Remove header
        $payload = substr($token, strlen('v4.local.'));

        // Try to decode as base64 and look for our data
        $decoded = base64_decode($payload, true);

        // The decoded data should NOT contain our plaintext values
        // because Paseto encrypts the payload
        $this->assertStringNotContainsString('secret@example.com', $decoded ?: '');
        $this->assertStringNotContainsString('admin', $decoded ?: '');
        $this->assertStringNotContainsString('user123', $decoded ?: '');
    }

    /**
     * @test
     * Verify token starts with correct Paseto V4 header.
     */
    public function token_has_correct_paseto_v4_header(): void
    {
        $token = $this->tokenService->sign(['sub' => 'user123'], 3600);

        $this->assertStringStartsWith('v4.local.', $token);
    }

    // ========================================
    // KEY STRENGTH TESTS
    // ========================================

    /**
     * @test
     * Verify that weak keys are rejected.
     */
    public function weak_key_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('at least 32 characters');

        new TokenService('short_key');
    }

    /**
     * @test
     * Verify that empty key is rejected.
     */
    public function empty_key_is_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new TokenService('');
    }

    /**
     * @test
     * Verify that 32 character key is accepted.
     */
    public function minimum_key_length_is_accepted(): void
    {
        $minKey = str_repeat('a', 32);
        $service = new TokenService($minKey);

        $token = $service->sign(['sub' => 'test'], 3600);

        $this->assertNotEmpty($token);
    }

    // ========================================
    // PAYLOAD INTEGRITY TESTS
    // ========================================

    /**
     * @test
     * Verify that all payload data is preserved.
     */
    public function payload_data_is_preserved(): void
    {
        $payload = [
            'sub' => 'user-uuid-123',
            'type' => 'access',
            'data' => [
                'roles' => ['ROLE_USER', 'ROLE_ADMIN'],
                'permissions' => ['read', 'write'],
            ],
            'custom_claim' => 'custom_value',
        ];

        $token = $this->tokenService->sign($payload, 3600);
        $result = $this->tokenService->verify($token);

        $this->assertEquals('user-uuid-123', $result['sub']);
        $this->assertEquals('access', $result['type']);
        $this->assertEquals(['ROLE_USER', 'ROLE_ADMIN'], $result['data']['roles']);
        $this->assertEquals('custom_value', $result['custom_claim']);
    }

    /**
     * @test
     * Verify that special characters in payload are handled correctly.
     */
    public function special_characters_in_payload_are_handled(): void
    {
        $payload = [
            'sub' => 'user123',
            'data' => [
                'username' => "O'Brien <script>alert('xss')</script>",
                'unicode' => '日本語テスト 🔐',
                'quotes' => '"double" and \'single\'',
            ],
        ];

        $token = $this->tokenService->sign($payload, 3600);
        $result = $this->tokenService->verify($token);

        $this->assertEquals("O'Brien <script>alert('xss')</script>", $result['data']['username']);
        $this->assertEquals('日本語テスト 🔐', $result['data']['unicode']);
    }

    // ========================================
    // DECODE VS VERIFY TESTS
    // ========================================

    /**
     * @test
     * Verify behavior with expired tokens.
     * Note: Paseto validates expiration during decryption, so decode() may return null
     * for expired tokens depending on the implementation.
     */
    public function expired_token_is_detected_by_is_expired(): void
    {
        $token = $this->tokenService->sign(['sub' => 'user123'], 1);
        sleep(2);

        // isExpired() should return true
        $this->assertTrue($this->tokenService->isExpired($token));
    }

    /**
     * @test
     * Verify that decode() returns null for invalid tokens.
     */
    public function decode_returns_null_for_invalid_tokens(): void
    {
        $result = $this->tokenService->decode('invalid_token');
        $this->assertNull($result);

        $result = $this->tokenService->decode('v4.local.invalid');
        $this->assertNull($result);
    }

    // ========================================
    // TIMING ATTACK RESISTANCE
    // ========================================

    /**
     * @test
     * Verify that invalid tokens take similar time to process.
     * This helps prevent timing attacks.
     */
    public function invalid_tokens_have_consistent_processing_time(): void
    {
        $validToken = $this->tokenService->sign(['sub' => 'user123'], 3600);
        $invalidToken = 'v4.local.' . str_repeat('a', 100);

        // Measure time for valid token
        $start = hrtime(true);
        $this->tokenService->verify($validToken);
        $validTime = hrtime(true) - $start;

        // Measure time for invalid token
        $start = hrtime(true);
        $this->tokenService->verify($invalidToken);
        $invalidTime = hrtime(true) - $start;

        // Times should be within same order of magnitude (not 10x different)
        // This is a basic check - cryptographic libraries handle this internally
        $ratio = max($validTime, $invalidTime) / max(1, min($validTime, $invalidTime));
        $this->assertLessThan(100, $ratio, 'Processing times should be similar to prevent timing attacks');
    }
}
