<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('better_auth');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->scalarNode('mode')
                    ->defaultValue('hybrid')
                    ->info('Authentication mode: "session", "api", or "hybrid" (both)')
                    ->validate()
                        ->ifNotInArray(['session', 'api', 'hybrid'])
                        ->thenInvalid('Invalid mode "%s". Must be "session", "api", or "hybrid".')
                    ->end()
                ->end()
                ->scalarNode('secret')
                    ->defaultValue('change_me_in_production')
                    ->info('Secret key for token encryption - should be set via environment variable')
                ->end()
                ->arrayNode('session')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('lifetime')->defaultValue(604800)->end()
                        ->scalarNode('cookie_name')->defaultValue('better_auth_session')->end()
                    ->end()
                ->end()
                ->arrayNode('token')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('lifetime')->defaultValue(3600)->info('Access token lifetime in seconds')->end()
                        ->integerNode('refresh_lifetime')->defaultValue(2592000)->info('Refresh token lifetime in seconds')->end()
                    ->end()
                ->end()
                ->arrayNode('oauth')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('providers')
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->children()
                                    ->booleanNode('enabled')->defaultFalse()->end()
                                    ->scalarNode('client_id')->end()
                                    ->scalarNode('client_secret')->end()
                                    ->scalarNode('redirect_uri')->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('multi_tenant')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('default_role')->defaultValue('member')->end()
                    ->end()
                ->end()
                ->arrayNode('two_factor')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->info('Enable TOTP two-factor authentication')->end()
                        ->scalarNode('issuer')->defaultValue('BetterAuth')->info('Issuer name shown in authenticator apps')->end()
                        ->integerNode('backup_codes_count')->defaultValue(10)->info('Number of backup codes to generate')->end()
                    ->end()
                ->end()

                // Simple controllers toggle - if false, user creates their own
                ->arrayNode('controllers')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable bundle controllers. Set to false to use your own.')
                        ->end()
                    ->end()
                ->end()

                // Security auto-configuration
                ->arrayNode('security')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('auto_configure')
                            ->defaultTrue()
                            ->info('Auto-configure security.yaml (firewalls, providers, access_control)')
                        ->end()
                        ->scalarNode('firewall_name')
                            ->defaultValue('api')
                            ->info('Name of the protected API firewall')
                        ->end()
                        ->scalarNode('firewall_pattern')
                            ->defaultValue('^/api')
                            ->info('Regex pattern for the protected API firewall')
                        ->end()
                        ->scalarNode('public_routes_pattern')
                            ->defaultValue('^/auth')
                            ->info('Pattern for public auth routes')
                        ->end()
                    ->end()
                ->end()

                // CORS auto-configuration
                ->arrayNode('cors')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('auto_configure')
                            ->defaultTrue()
                            ->info('Auto-configure nelmio_cors.yaml for auth routes')
                        ->end()
                    ->end()
                ->end()

                // Routing auto-configuration
                ->arrayNode('routing')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('auto_configure')
                            ->defaultTrue()
                            ->info('Auto-configure routes.yaml prefix for custom controllers')
                        ->end()
                        ->scalarNode('custom_controllers_namespace')
                            ->defaultValue('App\Controller\Api')
                            ->info('Namespace of custom controllers to prefix')
                        ->end()
                    ->end()
                ->end()

                // OpenAPI documentation configuration
                ->arrayNode('openapi')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('path_prefix')
                            ->defaultNull()
                            ->info('Auth path prefix for OpenAPI docs (e.g., /api/v1/auth). If null, auto-detected from routes.')
                        ->end()
                        ->booleanNode('enabled')
                            ->defaultTrue()
                            ->info('Enable OpenAPI documentation for auth endpoints')
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
