<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Plugin;

use BetterAuth\Core\Plugin\PluginInterface;
use BetterAuth\Core\Plugin\PluginManager;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Symfony-specific plugin loader.
 *
 * Integrates the plugin system with Symfony's service container
 * and event dispatcher.
 */
class SymfonyPluginLoader
{
    public function __construct(
        private readonly PluginManager $pluginManager,
        private readonly ?EventDispatcherInterface $eventDispatcher = null
    ) {
        // Set Symfony event dispatcher for plugin events
        if ($this->eventDispatcher !== null) {
            $this->pluginManager->setEventDispatcher(
                fn (object $event) => $this->eventDispatcher->dispatch($event)
            );
        }
    }

    /**
     * Get the plugin manager.
     *
     * @return PluginManager
     */
    public function getPluginManager(): PluginManager
    {
        return $this->pluginManager;
    }

    /**
     * Load a plugin by name.
     *
     * @param string $pluginName Plugin name
     * @return void
     */
    public function loadPlugin(string $pluginName): void
    {
        $this->pluginManager->load($pluginName);
    }

    /**
     * Load all registered plugins.
     *
     * @return void
     */
    public function loadAll(): void
    {
        $this->pluginManager->loadAll();
    }

    /**
     * Execute a hook.
     *
     * @param string $hookName Hook name
     * @param mixed $data Hook data
     * @return void
     */
    public function executeHook(string $hookName, mixed $data): void
    {
        $this->pluginManager->executeHook($hookName, $data);
    }

    /**
     * Get a loaded plugin.
     *
     * @param string $pluginName Plugin name
     * @return PluginInterface|null
     */
    public function getPlugin(string $pluginName): ?PluginInterface
    {
        return $this->pluginManager->getPlugin($pluginName);
    }

    /**
     * Get all loaded plugins.
     *
     * @return array<string, PluginInterface>
     */
    public function getLoadedPlugins(): array
    {
        return $this->pluginManager->getLoadedPlugins();
    }

    /**
     * Check if a plugin is loaded.
     *
     * @param string $pluginName Plugin name
     * @return bool
     */
    public function isPluginLoaded(string $pluginName): bool
    {
        return $this->pluginManager->isLoaded($pluginName);
    }
}
