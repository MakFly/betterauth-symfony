<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Compiler pass to auto-configure Symfony Security integration.
 *
 * This pass automatically:
 * - Registers the BetterAuth user provider
 * - Configures the authenticator
 * - Sets up proper service tags
 *
 * Similar to LexikJWTAuthenticationBundle's compiler pass.
 */
class BetterAuthSecurityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Check if Security bundle is installed
        if (!$container->hasDefinition('security.authentication.manager')) {
            return;
        }

        // Auto-tag the authenticator for Symfony Security
        if ($container->hasDefinition('BetterAuth\Symfony\Security\BetterAuthAuthenticator')) {
            $definition = $container->getDefinition('BetterAuth\Symfony\Security\BetterAuthAuthenticator');
            $definition->addTag('security.authenticator');
        }

        // Auto-tag the user provider
        if ($container->hasDefinition('BetterAuth\Symfony\Security\BetterAuthUserProvider')) {
            $definition = $container->getDefinition('BetterAuth\Symfony\Security\BetterAuthUserProvider');
            $definition->addTag('security.user_provider');
        }

        // Log that BetterAuth Security integration is active
        $container->setParameter('better_auth.security_enabled', true);
    }
}
