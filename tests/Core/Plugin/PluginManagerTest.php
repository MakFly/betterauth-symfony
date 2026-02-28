<?php

declare(strict_types=1);

namespace BetterAuth\Tests\Plugin;

use BetterAuth\Core\Config\AuthConfig;
use BetterAuth\Core\Plugin\PluginContext;
use BetterAuth\Core\Plugin\PluginInterface;
use BetterAuth\Core\Plugin\PluginManager;
use PHPUnit\Framework\TestCase;

class PluginManagerTest extends TestCase
{
    private PluginManager $pluginManager;

    protected function setUp(): void
    {
        $config = AuthConfig::forMonolith('test-secret-key');
        $this->pluginManager = new PluginManager($config);
    }

    public function testRegisterPlugin(): void
    {
        $plugin = $this->createMockPlugin('test-plugin');
        $this->pluginManager->register($plugin);

        $this->assertContains($plugin, $this->pluginManager->getAllPlugins());
    }

    public function testLoadPlugin(): void
    {
        $plugin = $this->createMockPlugin('test-plugin');
        $this->pluginManager->register($plugin);
        $this->pluginManager->load('test-plugin');

        $this->assertTrue($this->pluginManager->isLoaded('test-plugin'));
    }

    public function testExecuteHook(): void
    {
        $called = false;

        $plugin = new class ('test-plugin', $called) implements PluginInterface {
            private bool $hookCalled = false;

            public function __construct(private string $name, private bool &$calledRef)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getVersion(): string
            {
                return '1.0.0';
            }

            public function getDependencies(): array
            {
                return [];
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function getConfig(): array
            {
                return [];
            }

            public function install(PluginContext $context): void
            {
                $context->registerHook('test.hook', function ($data) {
                    $this->calledRef = true;
                });
            }
        };

        $this->pluginManager->register($plugin);
        $this->pluginManager->load('test-plugin');
        $this->pluginManager->executeHook('test.hook', []);

        $this->assertTrue($called);
    }

    private function createMockPlugin(string $name): PluginInterface
    {
        return new class ($name) implements PluginInterface {
            public function __construct(private string $name)
            {
            }

            public function getName(): string
            {
                return $this->name;
            }

            public function getVersion(): string
            {
                return '1.0.0';
            }

            public function getDependencies(): array
            {
                return [];
            }

            public function isEnabled(): bool
            {
                return true;
            }

            public function getConfig(): array
            {
                return [];
            }

            public function install(PluginContext $context): void
            {
            }
        };
    }
}
