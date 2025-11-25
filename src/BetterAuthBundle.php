<?php

declare(strict_types=1);

namespace BetterAuth\Symfony;

use BetterAuth\Symfony\DependencyInjection\BetterAuthExtension;
use BetterAuth\Symfony\DependencyInjection\BetterAuthSecurityPass;
use BetterAuth\Symfony\DependencyInjection\EntityAutoConfigurationPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * BetterAuth Symfony Bundle
 *
 * Features:
 * - Automatic Symfony Security integration
 * - Paseto V4 token authentication
 * - OAuth providers support (Google, GitHub, etc.)
 * - Multi-tenant & SSO support
 * - TOTP 2FA
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

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Security integration (user provider + authenticator tags)
        $container->addCompilerPass(new BetterAuthSecurityPass());

        // Auto-configure repositories to use App\Entity classes
        $container->addCompilerPass(new EntityAutoConfigurationPass());
    }
}
