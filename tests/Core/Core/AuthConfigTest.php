<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Core;

use BetterAuth\Core\Config\AuthConfig;
use BetterAuth\Core\Config\AuthMode;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AuthConfig - including the new forHybrid() factory.
 */
class AuthConfigTest extends TestCase
{
    private const SECRET_KEY = 'test-secret-key-that-is-at-least-32-characters';

    public function testForMonolith(): void
    {
        $config = AuthConfig::forMonolith(self::SECRET_KEY);

        $this->assertTrue($config->isMonolith());
        $this->assertFalse($config->isApi());
        $this->assertFalse($config->isHybrid());
        $this->assertFalse($config->supportsTokens());
        $this->assertTrue($config->supportsSessions());
        $this->assertSame(AuthMode::MONOLITH, $config->mode);
        $this->assertFalse($config->enableRefreshTokens);
    }

    public function testForApi(): void
    {
        $config = AuthConfig::forApi(self::SECRET_KEY);

        $this->assertFalse($config->isMonolith());
        $this->assertTrue($config->isApi());
        $this->assertFalse($config->isHybrid());
        $this->assertTrue($config->supportsTokens());
        $this->assertFalse($config->supportsSessions());
        $this->assertSame(AuthMode::API, $config->mode);
        $this->assertTrue($config->enableRefreshTokens);
    }

    public function testForHybrid(): void
    {
        $config = AuthConfig::forHybrid(self::SECRET_KEY);

        $this->assertFalse($config->isMonolith());
        $this->assertFalse($config->isApi());
        $this->assertTrue($config->isHybrid());
        $this->assertTrue($config->supportsTokens());
        $this->assertTrue($config->supportsSessions());
        $this->assertSame(AuthMode::HYBRID, $config->mode);
        $this->assertTrue($config->enableRefreshTokens);
    }

    public function testForHybridWithOverrides(): void
    {
        $config = AuthConfig::forHybrid(self::SECRET_KEY, [
            'tokenLifetime' => 7200,
            'sessionLifetime' => 86400,
        ]);

        $this->assertTrue($config->isHybrid());
        $this->assertSame(7200, $config->tokenLifetime);
        $this->assertSame(86400, $config->sessionLifetime);
    }

    public function testDefaultValues(): void
    {
        $config = new AuthConfig(
            mode: AuthMode::HYBRID,
            secretKey: self::SECRET_KEY,
        );

        $this->assertSame(604800, $config->sessionLifetime); // 7 days
        $this->assertSame(3600, $config->tokenLifetime); // 1 hour
        $this->assertSame(2592000, $config->refreshTokenLifetime); // 30 days
        $this->assertTrue($config->enableRefreshTokens);
        $this->assertSame('better_auth_session', $config->cookieName);
        $this->assertTrue($config->cookieHttpOnly);
        $this->assertTrue($config->cookieSecure);
        $this->assertSame('lax', $config->cookieSameSite);
    }

    public function testProxyMethods(): void
    {
        $configMonolith = AuthConfig::forMonolith(self::SECRET_KEY);
        $configApi = AuthConfig::forApi(self::SECRET_KEY);
        $configHybrid = AuthConfig::forHybrid(self::SECRET_KEY);

        // isMonolith
        $this->assertTrue($configMonolith->isMonolith());
        $this->assertFalse($configApi->isMonolith());
        $this->assertFalse($configHybrid->isMonolith());

        // isApi
        $this->assertFalse($configMonolith->isApi());
        $this->assertTrue($configApi->isApi());
        $this->assertFalse($configHybrid->isApi());

        // isHybrid
        $this->assertFalse($configMonolith->isHybrid());
        $this->assertFalse($configApi->isHybrid());
        $this->assertTrue($configHybrid->isHybrid());

        // supportsTokens
        $this->assertFalse($configMonolith->supportsTokens());
        $this->assertTrue($configApi->supportsTokens());
        $this->assertTrue($configHybrid->supportsTokens());

        // supportsSessions
        $this->assertTrue($configMonolith->supportsSessions());
        $this->assertFalse($configApi->supportsSessions());
        $this->assertTrue($configHybrid->supportsSessions());
    }
}
