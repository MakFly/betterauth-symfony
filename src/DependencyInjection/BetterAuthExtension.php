<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class BetterAuthExtension extends Extension implements PrependExtensionInterface
{
    public function prepend(ContainerBuilder $container): void
    {
        // Auto-configure Doctrine ORM mappings for BetterAuth entities
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        $projectDir = $container->getParameter('kernel.project_dir');

        // Try to locate betterauth-core package
        $possiblePaths = [
            // Path repository (development)
            dirname($projectDir) . '/betterauth-core/src/core',
            // Vendor installation
            $projectDir . '/vendor/betterauth/core/src/core',
        ];

        $betterAuthCorePath = null;
        foreach ($possiblePaths as $path) {
            if (is_dir($path)) {
                $betterAuthCorePath = $path;
                break;
            }
        }

        if ($betterAuthCorePath === null) {
            return;
        }

        // Register BetterAuth entity mappings automatically
        $container->prependExtensionConfig('doctrine', [
            'orm' => [
                'mappings' => [
                    'BetterAuth' => [
                        'type' => 'attribute',
                        'is_bundle' => false,
                        'dir' => $betterAuthCorePath . '/Entities',
                        'prefix' => 'BetterAuth\\Core\\Entities',
                        'alias' => 'BetterAuth',
                    ],
                ],
            ],
        ]);

        // Auto-configure Doctrine migrations for BetterAuth
        if ($container->hasExtension('doctrine_migrations')) {
            // Try to locate betterauth-symfony package migrations
            $migrationsPaths = [
                // Path repository (development)
                dirname($projectDir) . '/betterauth-symfony/migrations',
                // Vendor installation
                $projectDir . '/vendor/betterauth/symfony-bundle/migrations',
            ];

            $betterAuthMigrationsPath = null;
            foreach ($migrationsPaths as $path) {
                if (is_dir($path)) {
                    $betterAuthMigrationsPath = $path;
                    break;
                }
            }

            if ($betterAuthMigrationsPath !== null) {
                $container->prependExtensionConfig('doctrine_migrations', [
                    'migrations_paths' => [
                        'BetterAuth\\Symfony\\Migrations' => $betterAuthMigrationsPath,
                    ],
                ]);
            }
        }
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store config as parameters
        $container->setParameter('better_auth.mode', $config['mode']);
        $container->setParameter('better_auth.secret', $config['secret']);
        $container->setParameter('better_auth.session', $config['session']);
        $container->setParameter('better_auth.token', $config['token']);
        $container->setParameter('better_auth.oauth', $config['oauth']);
        $container->setParameter('better_auth.multi_tenant', $config['multi_tenant']);
        $container->setParameter('better_auth.two_factor', $config['two_factor']);

        // Load services
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.yaml');

        // Create AuthConfig service dynamically based on mode
        $authConfigDefinition = $container->getDefinition(\BetterAuth\Core\Config\AuthConfig::class);

        // api = pure stateless API, session/hybrid = monolith (supports both)
        $factory = $config['mode'] === 'api'
            ? [\BetterAuth\Core\Config\AuthConfig::class, 'forApi']
            : [\BetterAuth\Core\Config\AuthConfig::class, 'forMonolith'];

        $authConfigDefinition->setFactory($factory);
        $authConfigDefinition->setArguments([
            '$secretKey' => $config['secret'],
            '$overrides' => [
                'tokenLifetime' => $config['token']['lifetime'],
                'refreshTokenLifetime' => $config['token']['refresh_lifetime'],
                'sessionLifetime' => $config['session']['lifetime'],
            ],
        ]);

        // Register OAuth providers
        $this->registerOAuthProviders($container, $config['oauth']['providers']);

        // Configure TOTP provider
        if ($config['two_factor']['enabled']) {
            $totpProviderDefinition = $container->getDefinition(\BetterAuth\Providers\TotpProvider\TotpProvider::class);
            $totpProviderDefinition->setArgument('$issuer', $config['two_factor']['issuer']);
        }
    }

    /**
     * Register OAuth providers from configuration.
     */
    private function registerOAuthProviders(\Symfony\Component\DependencyInjection\ContainerBuilder $container, array $providers): void
    {
        $oauthManagerDefinition = $container->getDefinition(\BetterAuth\Providers\OAuthProvider\OAuthManager::class);

        foreach ($providers as $name => $providerConfig) {
            if (!($providerConfig['enabled'] ?? false)) {
                continue;
            }

            $providerClass = match ($name) {
                'google' => \BetterAuth\Providers\OAuthProvider\GoogleProvider::class,
                'facebook' => \BetterAuth\Providers\OAuthProvider\FacebookProvider::class,
                'github' => \BetterAuth\Providers\OAuthProvider\GitHubProvider::class,
                'discord' => \BetterAuth\Providers\OAuthProvider\DiscordProvider::class,
                'microsoft' => \BetterAuth\Providers\OAuthProvider\MicrosoftProvider::class,
                'twitter' => \BetterAuth\Providers\OAuthProvider\TwitterProvider::class,
                'apple' => \BetterAuth\Providers\OAuthProvider\AppleProvider::class,
                default => null,
            };

            if ($providerClass === null || !class_exists($providerClass)) {
                continue;
            }

            // Create provider service
            $providerServiceId = 'better_auth.oauth_provider.' . $name;
            $providerDefinition = $container->register($providerServiceId, $providerClass);
            $providerDefinition->setArguments([
                '$clientId' => $providerConfig['client_id'],
                '$clientSecret' => $providerConfig['client_secret'],
                '$redirectUri' => $providerConfig['redirect_uri'],
            ]);

            // Add provider to OAuthManager
            $oauthManagerDefinition->addMethodCall('addProvider', [
                new \Symfony\Component\DependencyInjection\Reference($providerServiceId),
            ]);
        }
    }

    public function getAlias(): string
    {
        return 'better_auth';
    }
}
