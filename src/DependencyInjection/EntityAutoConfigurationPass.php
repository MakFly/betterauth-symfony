<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Auto-configures BetterAuth repositories to use App\Entity classes.
 *
 * This pass automatically detects if App\Entity\* classes exist and configures
 * the Doctrine repositories to use them instead of the default MappedSuperclass.
 */
class EntityAutoConfigurationPass implements CompilerPassInterface
{
    /**
     * Mapping of repository service IDs to their entity class parameter and App\Entity class.
     */
    private const REPOSITORY_ENTITY_MAP = [
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineUserRepository' => [
            'argument' => '$userClass',
            'appClass' => 'App\Entity\User',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineSessionRepository' => [
            'argument' => '$sessionClass',
            'appClass' => 'App\Entity\Session',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineRefreshTokenRepository' => [
            'argument' => '$refreshTokenClass',
            'appClass' => 'App\Entity\RefreshToken',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineMagicLinkRepository' => [
            'argument' => '$tokenClass',
            'appClass' => 'App\Entity\MagicLinkToken',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineEmailVerificationRepository' => [
            'argument' => '$tokenClass',
            'appClass' => 'App\Entity\EmailVerificationToken',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrinePasswordResetRepository' => [
            'argument' => '$tokenClass',
            'appClass' => 'App\Entity\PasswordResetToken',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineTotpRepository' => [
            'argument' => '$totpClass',
            'appClass' => 'App\Entity\TotpData',
        ],
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::REPOSITORY_ENTITY_MAP as $serviceId => $config) {
            if (!$container->hasDefinition($serviceId)) {
                continue;
            }

            // Check if App\Entity class exists
            if (!class_exists($config['appClass'])) {
                continue;
            }

            $definition = $container->getDefinition($serviceId);
            
            // Only set if not already configured by user
            $arguments = $definition->getArguments();
            if (!isset($arguments[$config['argument']])) {
                $definition->setArgument($config['argument'], $config['appClass']);
            }
        }
    }
}

