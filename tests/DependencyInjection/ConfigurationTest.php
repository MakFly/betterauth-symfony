<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\DependencyInjection;

use BetterAuth\Symfony\DependencyInjection\Configuration;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Processor;

final class ConfigurationTest extends TestCase
{
    public function testItDefaultsEveryOptionalFeatureToDisabled(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'secret' => str_repeat('a', 32),
            'refresh_token' => ['store' => 'App\\Security\\RefreshTokenStore'],
        ]]);

        self::assertSame('sub', $config['user_id_claim']);
        self::assertIsArray($config['refresh_token']);
        self::assertIsArray($config['features']);
        self::assertTrue($config['refresh_token']['enabled']);
        self::assertFalse($config['features']['oauth']);
        self::assertFalse($config['features']['totp']);
        self::assertFalse($config['features']['monitoring']);
    }
}
