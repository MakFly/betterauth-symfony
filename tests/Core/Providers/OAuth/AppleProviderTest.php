<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Providers\OAuth;

use BetterAuth\Providers\OAuthProvider\AppleProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AppleProvider.
 *
 * AppleProvider is currently disabled because JWT signature verification
 * against Apple JWKS is not yet implemented. The constructor throws
 * a RuntimeException to prevent insecure usage.
 */
class AppleProviderTest extends TestCase
{
    public function testConstructorThrowsBecauseProviderIsDisabled(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/AppleProvider is disabled/');

        new AppleProvider(
            clientId: 'com.example.app',
            clientSecret: 'jwt-client-secret',
            redirectUri: 'https://example.com/auth/apple/callback',
        );
    }

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
