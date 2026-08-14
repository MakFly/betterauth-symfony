<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

final class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tree = new TreeBuilder('better_auth');

        $tree->getRootNode()
            ->children()
                ->scalarNode('secret')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('user_id_claim')->defaultValue('sub')->cannotBeEmpty()->end()
                ->arrayNode('access_token')->addDefaultsIfNotSet()->children()
                    ->integerNode('ttl')->defaultValue(3600)->min(1)->end()
                    ->arrayNode('parser')->addDefaultsIfNotSet()->children()
                        ->integerNode('max_token_length')->defaultValue(8192)->min(256)->end()
                        ->integerNode('max_json_length')->defaultValue(4096)->min(128)->end()
                        ->integerNode('max_claim_count')->defaultValue(32)->min(1)->end()
                        ->integerNode('max_claim_depth')->defaultValue(4)->min(1)->end()
                    ->end()->end()
                ->end()->end()
                ->arrayNode('refresh_token')->addDefaultsIfNotSet()->children()
                    ->booleanNode('enabled')->defaultTrue()->end()
                    ->integerNode('ttl')->defaultValue(2592000)->min(1)->end()
                    ->scalarNode('store')->defaultNull()->end()
                ->end()->end()
                ->arrayNode('token_extractors')->addDefaultsIfNotSet()->children()
                    ->arrayNode('authorization_header')->addDefaultsIfNotSet()->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->integerNode('max_length')->defaultValue(8192)->min(256)->end()
                    ->end()->end()
                    ->arrayNode('cookie')->addDefaultsIfNotSet()->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('name')->defaultValue('access_token')->end()
                        ->integerNode('max_length')->defaultValue(8192)->min(256)->end()
                    ->end()->end()
                ->end()->end()
                ->arrayNode('features')->addDefaultsIfNotSet()->children()
                    ->booleanNode('totp')->defaultFalse()->end()
                    ->booleanNode('magic_link')->defaultFalse()->end()
                    ->booleanNode('email_reset')->defaultFalse()->end()
                    ->booleanNode('guest')->defaultFalse()->end()
                    ->booleanNode('device')->defaultFalse()->end()
                    ->booleanNode('monitoring')->defaultFalse()->end()
                    ->booleanNode('multi_tenant')->defaultFalse()->end()
                ->end()->end()
                ->arrayNode('feature_ports')->addDefaultsIfNotSet()->children()
                    ->scalarNode('totp')->defaultNull()->end()
                    ->scalarNode('magic_link')->defaultNull()->end()
                    ->scalarNode('email_reset')->defaultNull()->end()
                    ->scalarNode('guest')->defaultNull()->end()
                    ->scalarNode('device')->defaultNull()->end()
                    ->scalarNode('monitoring')->defaultNull()->end()
                    ->scalarNode('multi_tenant')->defaultNull()->end()
                ->end()->end()
            ->end();

        return $tree;
    }
}
