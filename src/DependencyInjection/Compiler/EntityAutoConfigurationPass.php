<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * Auto-configures BetterAuth repositories to use App entities.
 *
 * This automatically discovers entities in App\Entity namespace and maps them
 * to BetterAuth repositories, eliminating the need for manual configuration.
 */
final class EntityAutoConfigurationPass implements CompilerPassInterface
{
    /**
     * Mapping of BetterAuth repository classes to their entity class parameter names
     * and the expected entity name pattern.
     */
    private const REPOSITORY_MAPPING = [
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineUserRepository' => [
            'param' => 'userClass',
            'entity' => 'User',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineSessionRepository' => [
            'param' => 'sessionClass',
            'entity' => 'Session',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineRefreshTokenRepository' => [
            'param' => 'refreshTokenClass',
            'entity' => 'RefreshToken',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineMagicLinkRepository' => [
            'param' => 'tokenClass',
            'entity' => 'MagicLinkToken',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineEmailVerificationRepository' => [
            'param' => 'tokenClass',
            'entity' => 'EmailVerificationToken',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrinePasswordResetRepository' => [
            'param' => 'tokenClass',
            'entity' => 'PasswordResetToken',
        ],
        'BetterAuth\Symfony\Storage\Doctrine\DoctrineTotpRepository' => [
            'param' => 'totpClass',
            'entity' => 'TotpData',
        ],
    ];

    public function process(ContainerBuilder $container): void
    {
        // Discover App entities by scanning files
        $appEntities = $this->discoverAppEntities($container);

        // Auto-configure each repository
        foreach (self::REPOSITORY_MAPPING as $repositoryClass => $config) {
            if (!$container->hasDefinition($repositoryClass)) {
                continue;
            }

            $entityName = $config['entity'];
            $entityClass = 'App\Entity\\' . $entityName;

            // Check if entity exists
            if (!isset($appEntities[$entityName])) {
                continue;
            }

            $repositoryDefinition = $container->getDefinition($repositoryClass);

            // Set the entity class argument using Symfony's argument format
            $repositoryDefinition->setArgument('$' . $config['param'], $entityClass);
        }
    }

    /**
     * Discover all App\Entity classes.
     */
    private function discoverAppEntities(ContainerBuilder $container): array
    {
        $entities = [];
        $projectDir = $container->getParameter('kernel.project_dir');
        $entityDir = $projectDir . '/src/Entity';

        if (!is_dir($entityDir)) {
            return $entities;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($entityDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $className = $this->getClassNameFromFile($file->getPathname());
            if ($className === null) {
                continue;
            }

            // Extract entity name (e.g., "User" from "App\Entity\User")
            $parts = explode('\\', $className);
            $entityName = end($parts);
            $entities[$entityName] = $className;
        }

        return $entities;
    }

    /**
     * Extract class name from PHP file.
     */
    private function getClassNameFromFile(string $filePath): ?string
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return null;
        }

        // Simple regex to extract namespace and class name
        $namespace = null;
        $class = null;

        if (preg_match('/namespace\s+([^;]+);/', $content, $matches)) {
            $namespace = $matches[1];
        }

        if (preg_match('/class\s+(\w+)/', $content, $matches)) {
            $class = $matches[1];
        }

        if ($namespace && $class) {
            return $namespace . '\\' . $class;
        }

        return null;
    }
}
