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
            ->end();

        return $treeBuilder;
    }
}
