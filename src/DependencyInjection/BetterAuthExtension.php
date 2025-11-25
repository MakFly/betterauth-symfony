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
        $configs = $container->getExtensionConfig($this->getAlias());
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Doctrine auto-configuration
        $this->prependDoctrineConfig($container);

        // Security auto-configuration
        if ($config['security']['auto_configure'] ?? true) {
            $this->prependSecurityConfig($container, $config);
        }

        // CORS auto-configuration
        if ($config['cors']['auto_configure'] ?? true) {
            $this->prependCorsConfig($container, $config);
        }

        // Routing auto-configuration
        if ($config['routing']['auto_configure'] ?? true) {
            $this->prependRoutingConfig($container, $config);
        }
    }

    private function prependDoctrineConfig(ContainerBuilder $container): void
    {
        if (!$container->hasExtension('doctrine')) {
            return;
        }

        $projectDir = $container->getParameter('kernel.project_dir');

        $possiblePaths = [
            dirname($projectDir) . '/betterauth-core/src/core',
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

        if ($container->hasExtension('doctrine_migrations')) {
            $migrationsPaths = [
                dirname($projectDir) . '/betterauth-symfony/migrations',
                $projectDir . '/vendor/betterauth/symfony-bundle/migrations',
            ];

            foreach ($migrationsPaths as $path) {
                if (is_dir($path)) {
                    $container->prependExtensionConfig('doctrine_migrations', [
                        'migrations_paths' => [
                            'BetterAuth\\Symfony\\Migrations' => $path,
                        ],
                    ]);
                    break;
                }
            }
        }
    }

    private function prependSecurityConfig(ContainerBuilder $container, array $config): void
    {
        if (!$container->hasExtension('security')) {
            return;
        }

        $securityConfig = $config['security'] ?? [];
        $publicRoutesPattern = $securityConfig['public_routes_pattern'] ?? '^/auth';
        $firewallName = $securityConfig['firewall_name'] ?? 'api';
        $firewallPattern = $securityConfig['firewall_pattern'] ?? '^/api';

        // Public endpoints that don't require authentication
        $publicEndpoints = '(register|login|refresh|password|oauth|magic-link|email|guest)';

        // Detect if auth routes are under an API pattern (e.g., /api/v1/auth)
        $authBasePattern = trim($publicRoutesPattern, '^$');
        $apiBasePattern = trim($firewallPattern, '^$');
        
        // Check if auth pattern contains API pattern (e.g., /api/v1/auth contains /api)
        $isAuthUnderApi = !empty($apiBasePattern) && str_contains($authBasePattern, $apiBasePattern);
        
        // Extract the API version pattern if auth is under API (e.g., /api/v1 from /api/v1/auth)
        $apiVersionPattern = null;
        if ($isAuthUnderApi && $authBasePattern !== $apiBasePattern) {
            // Extract the version part (e.g., /api/v1 from /api/v1/auth)
            $apiVersionPattern = '^' . preg_replace('#/auth.*$#', '', $authBasePattern);
        }

        $firewalls = [];
        $accessControl = [];

        // Public firewall for auth routes (allows public endpoints)
        $firewalls['better_auth_public'] = [
            'pattern' => $publicRoutesPattern,
            'stateless' => true,
            'security' => false,
        ];

        // Protected firewall for API routes
        // If auth is under API, create a specific firewall for the API version
        if ($apiVersionPattern && $apiVersionPattern !== $firewallPattern) {
            // Create firewall for the specific API version (e.g., /api/v1)
            $versionFirewallName = str_replace(['^', '/'], ['', '_'], $apiVersionPattern);
            $versionFirewallName = trim($versionFirewallName, '_');
            $firewalls[$versionFirewallName] = [
                'pattern' => $apiVersionPattern,
                'stateless' => true,
                'provider' => 'better_auth_provider',
                'custom_authenticators' => [
                    'BetterAuth\\Symfony\\Security\\BetterAuthAuthenticator',
                ],
            ];
        }

        // Main API firewall (if different from version-specific one)
        if (!$apiVersionPattern || $firewallPattern !== $apiVersionPattern) {
            $firewalls[$firewallName] = [
                'pattern' => $firewallPattern,
                'stateless' => true,
                'provider' => 'better_auth_provider',
                'custom_authenticators' => [
                    'BetterAuth\\Symfony\\Security\\BetterAuthAuthenticator',
                ],
            ];
        }

        // Access control rules
        // Public endpoints (registration, login, password reset, refresh, oauth, etc.)
        $accessControl[] = [
            'path' => $publicRoutesPattern . '/' . $publicEndpoints,
            'roles' => 'PUBLIC_ACCESS',
        ];

        // Protected auth endpoints require authentication
        $accessControl[] = [
            'path' => $publicRoutesPattern,
            'roles' => 'ROLE_USER',
        ];

        // Protected API endpoints require authentication
        if ($apiVersionPattern && $apiVersionPattern !== $firewallPattern) {
            $accessControl[] = [
                'path' => $apiVersionPattern,
                'roles' => 'ROLE_USER',
            ];
        }
        
        if ($firewallPattern !== $publicRoutesPattern) {
            $accessControl[] = [
                'path' => $firewallPattern,
                'roles' => 'ROLE_USER',
            ];
        }

        $container->prependExtensionConfig('security', [
            'providers' => [
                'better_auth_provider' => [
                    'id' => 'BetterAuth\\Symfony\\Security\\BetterAuthUserProvider',
                ],
            ],
            'firewalls' => $firewalls,
            'access_control' => $accessControl,
        ]);
    }

    private function prependCorsConfig(ContainerBuilder $container, array $config): void
    {
        if (!$container->hasExtension('nelmio_cors')) {
            return;
        }

        $securityConfig = $config['security'] ?? [];
        $publicRoutesPattern = $securityConfig['public_routes_pattern'] ?? '^/auth';
        $firewallPattern = $securityConfig['firewall_pattern'] ?? '^/api';

        // Extract base pattern (remove ^ and $ if present, but keep regex)
        $authPattern = $publicRoutesPattern;
        $apiPattern = $firewallPattern;

        // Build CORS paths configuration
        // Use regex patterns as keys (nelmio_cors supports them)
        $corsPaths = [];

        // Add specific pattern for auth routes (most specific first)
        if ($authPattern) {
            $corsPaths[$authPattern] = null; // null means use defaults from nelmio_cors.defaults
        }

        // Add pattern for API routes if different from auth
        // This covers cases like /api/v1/auth where auth is under /api
        if ($apiPattern && $apiPattern !== $authPattern && !str_contains($authPattern, $apiPattern)) {
            $corsPaths[$apiPattern] = null;
        }

        // Only prepend if we have paths to configure
        if (!empty($corsPaths)) {
            $container->prependExtensionConfig('nelmio_cors', [
                'paths' => $corsPaths,
            ]);
        }
    }

    private function prependRoutingConfig(ContainerBuilder $container, array $config): void
    {
        $securityConfig = $config['security'] ?? [];
        $publicRoutesPattern = $securityConfig['public_routes_pattern'] ?? '^/auth';
        $routingConfig = $config['routing'] ?? [];
        $customNamespace = $routingConfig['custom_controllers_namespace'] ?? 'App\Controller\Api';

        // Extract API prefix from public routes pattern (e.g., /api/v1 from /api/v1/auth)
        $authBasePattern = trim($publicRoutesPattern, '^$');
        $apiPrefix = null;

        // Check if auth pattern contains /api/ (versioned API)
        if (preg_match('#^/api(/v\d+)?/auth#', $authBasePattern, $matches)) {
            // Extract prefix (e.g., /api/v1 from /api/v1/auth)
            $apiPrefix = preg_replace('#/auth.*$#', '', $authBasePattern);
        }

        // Store routing prefix and namespace as parameters
        // These can be used in user's routes.yaml via %better_auth.routing.prefix%
        // Default to empty string if no prefix detected (routes at /auth/*)
        $container->setParameter('better_auth.routing.prefix', $apiPrefix ?: '');
        $container->setParameter('better_auth.routing.custom_namespace', $customNamespace);
        $container->setParameter('better_auth.routing.has_prefix', $apiPrefix !== null);
    }

    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Store config parameters
        $container->setParameter('better_auth.config', $config);
        $container->setParameter('better_auth.mode', $config['mode']);
        $container->setParameter('better_auth.secret', $config['secret']);
        $container->setParameter('better_auth.session', $config['session']);
        $container->setParameter('better_auth.token', $config['token']);
        $container->setParameter('better_auth.oauth', $config['oauth']);
        $container->setParameter('better_auth.multi_tenant', $config['multi_tenant']);
        $container->setParameter('better_auth.two_factor', $config['two_factor']);
        $container->setParameter('better_auth.controllers', $config['controllers']);
        $container->setParameter('better_auth.security', $config['security']);
        $container->setParameter('better_auth.cors', $config['cors']);
        $container->setParameter('better_auth.routing', $config['routing']);

        // Load services
        $loader = new YamlFileLoader(
            $container,
            new FileLocator(__DIR__ . '/../../config')
        );
        $loader->load('services.yaml');
        $loader->load('commands.yaml');

        // Configure AuthConfig based on mode
        $authConfigDefinition = $container->getDefinition(\BetterAuth\Core\Config\AuthConfig::class);

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

        // Configure OpenAPI decorator with auth path prefix
        $this->configureOpenApiDecorator($container, $config);
    }

    private function configureOpenApiDecorator(ContainerBuilder $container, array $config): void
    {
        if (!$container->hasDefinition(\BetterAuth\Symfony\OpenApi\AuthenticationDecorator::class)) {
            return;
        }

        $openApiConfig = $config['openapi'] ?? [];
        
        // If OpenAPI is disabled, remove the decorator
        if (!($openApiConfig['enabled'] ?? true)) {
            $container->removeDefinition(\BetterAuth\Symfony\OpenApi\AuthenticationDecorator::class);
            return;
        }

        $decoratorDefinition = $container->getDefinition(\BetterAuth\Symfony\OpenApi\AuthenticationDecorator::class);

        // Only inject authPathPrefix if explicitly configured
        // Otherwise, let the decorator auto-detect from routes
        $pathPrefix = $openApiConfig['path_prefix'] ?? null;
        if ($pathPrefix !== null) {
            $decoratorDefinition->setArgument('$authPathPrefix', $pathPrefix);
        }
        // If null, the decorator will use the router to auto-detect the prefix
    }

    private function registerOAuthProviders(ContainerBuilder $container, array $providers): void
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

            $providerServiceId = 'better_auth.oauth_provider.' . $name;
            $providerDefinition = $container->register($providerServiceId, $providerClass);
            $providerDefinition->setArguments([
                '$clientId' => $providerConfig['client_id'],
                '$clientSecret' => $providerConfig['client_secret'],
                '$redirectUri' => $providerConfig['redirect_uri'],
            ]);

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
