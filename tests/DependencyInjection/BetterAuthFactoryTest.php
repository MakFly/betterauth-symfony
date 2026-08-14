<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\DependencyInjection;

use BetterAuth\Symfony\DependencyInjection\Security\BetterAuthFactory;
use BetterAuth\Symfony\BetterAuthBundle;
use BetterAuth\Symfony\Security\BetterAuthAuthenticator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class BetterAuthFactoryTest extends TestCase
{
    public function testItInjectsTheFirewallUserProvider(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(BetterAuthAuthenticator::class, new Definition(BetterAuthAuthenticator::class));

        $serviceId = (new BetterAuthFactory())->createAuthenticator(
            $container,
            'api',
            ['token_extractor' => 'app.access_token_extractor'],
            'security.user.provider.concrete.app_users',
        );

        $definition = $container->getDefinition($serviceId);
        self::assertSame('better_auth.authenticator.api', $serviceId);
        self::assertInstanceOf(Reference::class, $definition->getArgument('$userProvider'));
        self::assertSame('security.user.provider.concrete.app_users', (string) $definition->getArgument('$userProvider'));
    }

    public function testTheFactoryMakesTheFirewallKeyValidSecurityConfiguration(): void
    {
        $container = new ContainerBuilder();
        $security = new SecurityExtension();
        $container->registerExtension($security);
        (new BetterAuthBundle())->build($container);

        $configuration = $security->getConfiguration([], $container);
        self::assertNotNull($configuration);
        $processed = (new Processor())->processConfiguration($configuration, [[
            'providers' => ['app_users' => ['id' => 'App\\Security\\UserProvider']],
            'firewalls' => ['api' => ['provider' => 'app_users', 'better_auth' => null]],
        ]]);

        self::assertIsArray($processed['firewalls']);
        self::assertIsArray($processed['firewalls']['api']);
        self::assertArrayHasKey('better_auth', $processed['firewalls']['api']);
    }
}
