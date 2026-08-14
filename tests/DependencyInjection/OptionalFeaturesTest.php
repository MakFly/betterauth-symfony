<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\DependencyInjection;

use BetterAuth\Symfony\DependencyInjection\BetterAuthExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

final class OptionalFeaturesTest extends TestCase
{
    public function testOptionalServicesAreRegisteredOnlyWhenEnabled(): void
    {
        $container = new ContainerBuilder();
        (new BetterAuthExtension())->load([[
            'secret' => str_repeat('a', 32),
            'refresh_token' => ['enabled' => false],
        ]], $container);
        self::assertFalse($container->hasDefinition('better_auth.feature.totp'));

        $container = new ContainerBuilder();
        (new BetterAuthExtension())->load([[
            'secret' => str_repeat('a', 32),
            'refresh_token' => ['enabled' => false],
            'features' => array_fill_keys(['totp', 'magic_link', 'email_reset', 'guest', 'device', 'monitoring', 'multi_tenant'], true),
            'feature_ports' => array_combine(['totp', 'magic_link', 'email_reset', 'guest', 'device', 'monitoring', 'multi_tenant'], ['app.totp', 'app.magic', 'app.reset', 'app.guest', 'app.device', 'app.monitor', 'app.tenant']),
        ]], $container);

        foreach (['totp', 'magic_link', 'email_reset', 'guest', 'device', 'monitoring', 'multi_tenant'] as $feature) {
            self::assertTrue($container->hasDefinition('better_auth.feature.'.$feature));
        }
    }
}
