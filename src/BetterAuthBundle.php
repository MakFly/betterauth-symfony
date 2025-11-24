<?php

declare(strict_types=1);

namespace BetterAuth\Symfony;

use BetterAuth\Symfony\DependencyInjection\BetterAuthExtension;
use BetterAuth\Symfony\DependencyInjection\BetterAuthSecurityPass;
use BetterAuth\Symfony\DependencyInjection\Compiler\PluginPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * BetterAuth Symfony Bundle
 *
 * Provides seamless integration of BetterAuth authentication
 * into Symfony applications, similar to LexikJWTAuthenticationBundle.
 *
 * Features:
 * - Automatic Symfony Security integration
 * - Paseto V4 token authentication
 * - OAuth providers support (Google, GitHub, etc.)
 * - Multi-tenant & SSO support
 * - Stateless API authentication
 */
class BetterAuthBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }

    public function getContainerExtension(): BetterAuthExtension
    {
        return new BetterAuthExtension();
    }

    /**
     * Register compiler passes for auto-configuration
     */
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Add compiler pass for Symfony Security integration
        $container->addCompilerPass(new BetterAuthSecurityPass());

        // Add compiler pass for Plugin System auto-registration
        $container->addCompilerPass(new PluginPass());
    }
}
