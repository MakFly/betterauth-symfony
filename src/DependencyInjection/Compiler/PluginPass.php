<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\DependencyInjection\Compiler;

use BetterAuth\Core\Plugin\PluginInterface;
use BetterAuth\Core\Plugin\PluginManager;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Compiler pass to auto-register BetterAuth plugins.
 *
 * Any service tagged with 'better_auth.plugin' will be automatically
 * registered with the PluginManager.
 *
 * Usage in services.yaml:
 * ```yaml
 * App\BetterAuth\Plugin\MyPlugin:
 *     tags: ['better_auth.plugin']
 * ```
 */
class PluginPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Check if PluginManager service exists
        if (!$container->hasDefinition(PluginManager::class)) {
            return;
        }

        $pluginManagerDefinition = $container->getDefinition(PluginManager::class);

        // Find all services tagged with 'better_auth.plugin'
        $taggedServices = $container->findTaggedServiceIds('better_auth.plugin');

        foreach ($taggedServices as $serviceId => $tags) {
            // Verify service implements PluginInterface
            $serviceDefinition = $container->getDefinition($serviceId);
            $serviceClass = $serviceDefinition->getClass();

            if ($serviceClass !== null && !is_subclass_of($serviceClass, PluginInterface::class)) {
                throw new \InvalidArgumentException(
                    sprintf(
                        'Service "%s" tagged with "better_auth.plugin" must implement %s',
                        $serviceId,
                        PluginInterface::class
                    )
                );
            }

            // Register plugin with PluginManager
            $pluginManagerDefinition->addMethodCall('register', [new Reference($serviceId)]);
        }

        // Auto-load all plugins after registration
        $pluginManagerDefinition->addMethodCall('loadAll', []);
    }
}
