<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Core;

use BetterAuth\Core\Config\AuthMode;
use PHPUnit\Framework\TestCase;

/**
 * Tests for AuthMode enum - including the new HYBRID mode.
 */
class AuthModeTest extends TestCase
{
    public function testMonolithMode(): void
    {
        $mode = AuthMode::MONOLITH;

        $this->assertTrue($mode->isMonolith());
        $this->assertFalse($mode->isApi());
        $this->assertFalse($mode->isHybrid());
        $this->assertFalse($mode->supportsTokens());
        $this->assertTrue($mode->supportsSessions());
    }

    public function testApiMode(): void
    {
        $mode = AuthMode::API;

        $this->assertFalse($mode->isMonolith());
        $this->assertTrue($mode->isApi());
        $this->assertFalse($mode->isHybrid());
        $this->assertTrue($mode->supportsTokens());
        $this->assertFalse($mode->supportsSessions());
    }

    public function testHybridMode(): void
    {
        $mode = AuthMode::HYBRID;

        $this->assertFalse($mode->isMonolith());
        $this->assertFalse($mode->isApi());
        $this->assertTrue($mode->isHybrid());
        $this->assertTrue($mode->supportsTokens());
        $this->assertTrue($mode->supportsSessions());
    }

    public function testModeValues(): void
    {
        $this->assertSame('monolith', AuthMode::MONOLITH->value);
        $this->assertSame('api', AuthMode::API->value);
        $this->assertSame('hybrid', AuthMode::HYBRID->value);
    }

    /**
     * @dataProvider tokenSupportDataProvider
     */
    public function testSupportsTokens(AuthMode $mode, bool $expected): void
    {
        $this->assertSame($expected, $mode->supportsTokens());
    }

    public static function tokenSupportDataProvider(): array
    {
        return [
            'monolith does not support tokens' => [AuthMode::MONOLITH, false],
            'api supports tokens' => [AuthMode::API, true],
            'hybrid supports tokens' => [AuthMode::HYBRID, true],
        ];
    }

    /**
     * @dataProvider sessionSupportDataProvider
     */
    public function testSupportsSessions(AuthMode $mode, bool $expected): void
    {
        $this->assertSame($expected, $mode->supportsSessions());
    }

    public static function sessionSupportDataProvider(): array
    {
        return [
            'monolith supports sessions' => [AuthMode::MONOLITH, true],
            'api does not support sessions' => [AuthMode::API, false],
            'hybrid supports sessions' => [AuthMode::HYBRID, true],
        ];
    }
}
