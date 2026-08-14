<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\DependencyInjection\Security;

use BetterAuth\Symfony\Security\BetterAuthAuthenticator;
use Symfony\Bundle\SecurityBundle\DependencyInjection\Security\Factory\AuthenticatorFactoryInterface;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

final class BetterAuthFactory implements AuthenticatorFactoryInterface
{
    public function getPriority(): int
    {
        return -20;
    }

    public function getKey(): string
    {
        return 'better_auth';
    }

    public function addConfiguration(NodeDefinition $node): void
    {
        if (!$node instanceof ArrayNodeDefinition) {
            throw new \LogicException('BetterAuth firewall configuration must be an array.');
        }

        $node
            ->addDefaultsIfNotSet()
            ->children()
                ->scalarNode('token_extractor')->defaultValue('better_auth.token_extractor')->end()
            ->end();
    }

    public function createAuthenticator(ContainerBuilder $container, string $firewallName, array $config, string $userProviderId): string
    {
        $extractor = $config['token_extractor'] ?? null;
        if (!is_string($extractor) || $extractor === '') {
            throw new \LogicException('A BetterAuth token extractor service is required.');
        }

        $serviceId = sprintf('better_auth.authenticator.%s', $firewallName);
        $container
            ->setDefinition($serviceId, new ChildDefinition(BetterAuthAuthenticator::class))
            ->replaceArgument('$extractor', new Reference($extractor))
            ->replaceArgument('$userProvider', new Reference($userProviderId))
            ->setPublic(true);

        return $serviceId;
    }
}
