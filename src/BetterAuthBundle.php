<?php

declare(strict_types=1);

namespace BetterAuth\Symfony;

use BetterAuth\Symfony\DependencyInjection\BetterAuthExtension;
use BetterAuth\Symfony\DependencyInjection\Security\BetterAuthFactory;
use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class BetterAuthBundle extends Bundle
{
    public function getContainerExtension(): BetterAuthExtension
    {
        return new BetterAuthExtension();
    }

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        if ($container->hasExtension('security')) {
            $extension = $container->getExtension('security');
            if ($extension instanceof SecurityExtension) {
                $extension->addAuthenticatorFactory(new BetterAuthFactory());
            }
        }
    }
}
