<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Providers\OAuth;

use BetterAuth\Providers\OAuthProvider\AppleProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AppleProvider.
 *
 * Covers:
 * - JWT structure validation
 * - Issuer (iss) claim validation
 * - Expiration (exp) claim validation
 * - Valid token parsing
 * - Edge cases (missing fields, malformed JWT)
 */
class AppleProviderTest extends TestCase
{
    private AppleProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new AppleProvider(
            clientId: 'com.example.app',
            clientSecret: 'jwt-client-secret',
            redirectUri: 'https://example.com/auth/apple/callback',
        );
    }

    // ========================================
    // PROVIDER METADATA TESTS
    // ========================================

    public function testGetNameReturnsApple(): void
    {
        $this->assertSame('apple', $this->provider->getName());
    }

    // ========================================
    // AUTHORIZATION URL TESTS
    // ========================================

    public function testGetAuthorizationUrlContainsRequiredParams(): void
    {
        $url = $this->provider->getAuthorizationUrl('test-state-123');

        $this->assertStringContainsString('appleid.apple.com/auth/authorize', $url);
        $this->assertStringContainsString('client_id=com.example.app', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('state=test-state-123', $url);
        $this->assertStringContainsString('response_mode=form_post', $url);
    }

    public function testGetAuthorizationUrlIncludesDefaultScopes(): void
    {
        $url = $this->provider->getAuthorizationUrl('state-abc');

        $this->assertStringContainsString('scope=', $url);
        $this->assertStringContainsString('name', $url);
        $this->assertStringContainsString('email', $url);
    }

    public function testGetAuthorizationUrlSupportsCustomResponseMode(): void
    {
        $url = $this->provider->getAuthorizationUrl('state-xyz', ['response_mode' => 'query']);

        $this->assertStringContainsString('response_mode=query', $url);
    }

    public function testGetAuthorizationUrlSupportsCustomScopes(): void
    {
        $url = $this->provider->getAuthorizationUrl('state-xyz', ['scopes' => ['email']]);

        $this->assertStringContainsString('scope=email', $url);
    }

    // ========================================
    // getUserInfo() — MUST THROW
    // ========================================

    public function testGetUserInfoThrowsRequiringSpecialHandling(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Apple provider requires special handling/');

        $this->provider->getUserInfo('any-access-token');
    }

    // ========================================
    // JWT ID TOKEN VALIDATION TESTS
    // ========================================

    public function testGetUserInfoFromIdTokenThrowsOnInvalidStructure(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JWT token');

        // Not a 3-part JWT
        $this->provider->getUserInfoFromIdToken('not.a.valid.jwt.structure.extra');
    }

    public function testGetUserInfoFromIdTokenThrowsOnMissingJwtParts(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JWT token');

        $this->provider->getUserInfoFromIdToken('only.two');
    }

    public function testGetUserInfoFromIdTokenThrowsOnInvalidHeader(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Invalid JWT (token|header)/');

        // header without kid/alg
        $header = base64_encode(json_encode(['typ' => 'JWT']));
        $payload = base64_encode(json_encode(['sub' => 'test']));
        $signature = 'fakesignature';

        $this->provider->getUserInfoFromIdToken("$header.$payload.$signature");
    }

    public function testGetUserInfoFromIdTokenThrowsOnInvalidIssuer(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid JWT issuer');

        $header = rtrim(strtr(base64_encode(json_encode(['kid' => 'key1', 'alg' => 'RS256'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode([
            'iss' => 'https://evil.com',
            'sub' => 'user-123',
            'email' => 'user@example.com',
            'exp' => time() + 3600,
            'aud' => 'com.example.app',
        ])), '+/', '-_'), '=');

        $this->provider->getUserInfoFromIdToken("$header.$payload.fakesig");
    }

    public function testGetUserInfoFromIdTokenThrowsOnExpiredToken(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JWT token has expired');

        $header = rtrim(strtr(base64_encode(json_encode(['kid' => 'key1', 'alg' => 'RS256'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode([
            'iss' => 'https://appleid.apple.com',
            'sub' => 'user-123',
            'email' => 'user@example.com',
            'exp' => time() - 3600, // Expired 1 hour ago
            'aud' => 'com.example.app',
        ])), '+/', '-_'), '=');

        $this->provider->getUserInfoFromIdToken("$header.$payload.fakesig");
    }

    public function testGetUserInfoFromIdTokenReturnsProviderUserOnValidToken(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['kid' => 'key1', 'alg' => 'RS256'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode([
            'iss' => 'https://appleid.apple.com',
            'sub' => 'apple-user-abc123',
            'email' => 'user@privaterelay.appleid.com',
            'email_verified' => 'true',
            'exp' => time() + 3600,
            'aud' => 'com.example.app',
        ])), '+/', '-_'), '=');

        $providerUser = $this->provider->getUserInfoFromIdToken("$header.$payload.fakesig");

        $this->assertSame('apple-user-abc123', $providerUser->providerId);
        $this->assertSame('user@privaterelay.appleid.com', $providerUser->email);
        $this->assertTrue($providerUser->emailVerified);
        $this->assertNull($providerUser->name); // Apple doesn't always provide name
        $this->assertNull($providerUser->avatar);
    }

    public function testGetUserInfoFromIdTokenHandlesUnverifiedEmail(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['kid' => 'key1', 'alg' => 'RS256'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode([
            'iss' => 'https://appleid.apple.com',
            'sub' => 'apple-user-def456',
            'email' => 'unverified@example.com',
            'email_verified' => 'false',
            'exp' => time() + 3600,
        ])), '+/', '-_'), '=');

        $providerUser = $this->provider->getUserInfoFromIdToken("$header.$payload.fakesig");

        $this->assertFalse($providerUser->emailVerified);
    }

    public function testGetUserInfoFromIdTokenHandlesMissingOptionalFields(): void
    {
        $header = rtrim(strtr(base64_encode(json_encode(['kid' => 'key1', 'alg' => 'RS256'])), '+/', '-_'), '=');
        $payload = rtrim(strtr(base64_encode(json_encode([
            'iss' => 'https://appleid.apple.com',
            // sub and email missing — edge case
            'exp' => time() + 3600,
        ])), '+/', '-_'), '=');

        $providerUser = $this->provider->getUserInfoFromIdToken("$header.$payload.fakesig");

        // Falls back to empty strings for missing required fields
        $this->assertSame('', $providerUser->providerId);
        $this->assertSame('', $providerUser->email);
    }

    // ========================================
    // generateClientSecret() TESTS
    // ========================================

    public function testGenerateClientSecretThrowsOnInvalidPrivateKey(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid private key');

        AppleProvider::generateClientSecret(
            teamId: 'TEAM123',
            clientId: 'com.example.app',
            keyId: 'KEY123',
            privateKey: 'not-a-valid-key',
        );
    }
}
