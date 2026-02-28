<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\DependencyInjection\Compiler;

use BetterAuth\Symfony\OAuth\OAuthProviderRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass that collects all services tagged with 'better_auth.oauth_provider'
 * and registers them in the OAuthProviderRegistry.
 *
 * Usage: tag your OAuth provider service with:
 *   tags:
 *     - { name: 'better_auth.oauth_provider', provider: 'my_provider' }
 */
final class OAuthProviderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(OAuthProviderRegistry::class)) {
            return;
        }

        $definition = $container->findDefinition(OAuthProviderRegistry::class);

        $taggedServices = $container->findTaggedServiceIds('better_auth.oauth_provider');
        foreach ($taggedServices as $id => $tags) {
            foreach ($tags as $attributes) {
                $name = $attributes['provider'] ?? $id;
                $definition->addMethodCall('register', [$name, new Reference($id)]);
            }
        }
    }
}
