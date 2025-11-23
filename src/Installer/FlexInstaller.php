<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Installer;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Symfony Flex installer for BetterAuth.
 *
 * Handles automatic setup when running `composer require betterauth/symfony-bundle`.
 * - Asks for ID strategy (UUID vs INT)
 * - Generates entities automatically
 * - Optionally runs migrations
 */
class FlexInstaller
{
    private const ENTITY_TEMPLATES = [
        'User' => [
            'uuid' => 'user.uuid.php.tpl',
            'int' => 'user.int.php.tpl',
        ],
        'Session' => [
            'uuid' => 'session.uuid.php.tpl',
            'int' => 'session.int.php.tpl',
        ],
        'RefreshToken' => [
            'uuid' => 'refresh_token.uuid.php.tpl',
            'int' => 'refresh_token.int.php.tpl',
        ],
    ];

    public static function postInstall(Event $event): void
    {
        $io = $event->getIO();

        $io->write([
            '',
            '<info>╔════════════════════════════════════════════════════════════╗</info>',
            '<info>║                                                            ║</info>',
            '<info>║          🔐 BetterAuth Configuration Wizard 🔐             ║</info>',
            '<info>║                                                            ║</info>',
            '<info>╚════════════════════════════════════════════════════════════╝</info>',
            '',
        ]);

        $projectDir = self::getProjectDir();
        $filesystem = new Filesystem();

        // Step 0: Auto-register bundle
        self::registerBundle($io, $projectDir, $filesystem);

        // Step 1: Ask for ID strategy
        $io->write('<comment>Step 1/3:</comment> Choose your ID strategy');
        $io->write('');
        $io->write('  <fg=cyan>UUID (recommended):</>');
        $io->write('    ✓ Secure (non-guessable IDs)');
        $io->write('    ✓ Distributed systems friendly');
        $io->write('    ✓ No ID leakage');
        $io->write('    ✗ Larger index size');
        $io->write('');
        $io->write('  <fg=cyan>INT (classic):</>');
        $io->write('    ✓ Better performance');
        $io->write('    ✓ Smaller database size');
        $io->write('    ✓ Sequential ordering');
        $io->write('    ✗ Predictable IDs');
        $io->write('');

        $idStrategy = 'uuid'; // Default
        if ($io->isInteractive()) {
            $answer = $io->ask('Which ID strategy do you want to use? [uuid/int] (default: uuid): ', 'uuid');
            $idStrategy = strtolower(trim($answer)) === 'int' ? 'int' : 'uuid';
        }

        // Step 2: Generate entities
        $io->write(['', '<comment>Step 2/3:</comment> Generating entities...']);

        $entitiesDir = $projectDir . '/src/Entity';
        if (!$filesystem->exists($entitiesDir)) {
            $filesystem->mkdir($entitiesDir);
        }

        $templatesDir = dirname(__DIR__) . '/Resources/templates/entities';
        $generatedFiles = [];

        foreach (self::ENTITY_TEMPLATES as $entityName => $templates) {
            $templateFile = $templatesDir . '/' . $templates[$idStrategy];
            $targetFile = $entitiesDir . '/' . $entityName . '.php';

            if ($filesystem->exists($targetFile)) {
                if ($io->isInteractive()) {
                    $overwrite = $io->ask(sprintf('  Entity <info>%s</info> already exists. Overwrite? [y/N] ', $entityName), 'n');
                    if (strtolower(trim($overwrite)) !== 'y') {
                        $io->write(sprintf('  <fg=yellow>⊘</> Skipped %s', $entityName));

                        continue;
                    }
                } else {
                    $io->write(sprintf('  <fg=yellow>⊘</> Skipped %s (already exists)', $entityName));

                    continue;
                }
            }

            $content = file_get_contents($templateFile);
            $filesystem->dumpFile($targetFile, $content);
            $generatedFiles[] = $entityName;
            $io->write(sprintf('  <fg=green>✓</> Generated %s.php', $entityName));
        }

        // Step 3: Ask about migrations
        $io->write(['', '<comment>Step 3/3:</comment> Database setup']);

        $runMigrations = true; // Default
        if ($io->isInteractive()) {
            $answer = $io->ask('  Do you want to generate and run migrations now? [Y/n] ', 'y');
            $runMigrations = strtolower(trim($answer)) !== 'n';
        }

        if ($runMigrations) {
            self::runMigrations($io, $projectDir);
        } else {
            $io->write([
                '',
                '<info>To generate migrations later, run:</info>',
                '  <comment>php bin/console doctrine:migrations:diff</comment>',
                '  <comment>php bin/console doctrine:migrations:migrate</comment>',
            ]);
        }

        // Final summary
        $io->write([
            '',
            '<info>╔════════════════════════════════════════════════════════════╗</info>',
            '<info>║          ✨ Installation Complete! ✨                      ║</info>',
            '<info>╚════════════════════════════════════════════════════════════╝</info>',
            '',
            sprintf('  <fg=green>✓</> ID Strategy: <comment>%s</comment>', strtoupper($idStrategy)),
            sprintf('  <fg=green>✓</> Entities: <comment>%s</comment>', implode(', ', $generatedFiles)),
            sprintf('  <fg=green>✓</> Config: <comment>config/packages/better_auth.yaml</comment>'),
            '',
            '<comment>Next steps:</comment>',
            '  1. Review your configuration in <info>config/packages/better_auth.yaml</info>',
            '  2. Add authentication routes to your controllers',
            '  3. Customize entities in <info>src/Entity/</info> if needed',
            '',
            '<comment>Example usage:</comment>',
            '  <info>use BetterAuth\Providers\AuthManager\AuthManager;</info>',
            '',
            '  <info>#[Route(\'/auth/register\', methods: [\'POST\'])]</info>',
            '  <info>public function register(AuthManager $auth): JsonResponse</info>',
            '  <info>{</info>',
            '  <info>    $user = $auth->register([\'email\' => ..., \'password\' => ...]);</info>',
            '  <info>    return $this->json([\'user\' => $user]);</info>',
            '  <info>}</info>',
            '',
            '📚 Documentation: <comment>https://github.com/your-org/better-auth-php</comment>',
            '',
        ]);

        // Save ID strategy to config file for future reference
        $configFile = $projectDir . '/config/packages/better_auth.yaml';
        if ($filesystem->exists($configFile)) {
            $config = file_get_contents($configFile);
            $config = str_replace(
                'better_auth:',
                sprintf("better_auth:\n    # Generated with ID strategy: %s", strtoupper($idStrategy)),
                $config
            );
            $filesystem->dumpFile($configFile, $config);
        }
    }

    private static function runMigrations($io, string $projectDir): void
    {
        $consolePath = $projectDir . '/bin/console';

        if (!file_exists($consolePath)) {
            $io->writeError('  <error>Console not found. Please run migrations manually.</error>');

            return;
        }

        // Generate migration
        $io->write('  Generating migration...');
        $output = [];
        $returnVar = 0;
        exec("php $consolePath doctrine:migrations:diff --no-interaction 2>&1", $output, $returnVar);

        if ($returnVar === 0) {
            $io->write('  <fg=green>✓</> Migration generated');

            // Ask to execute
            $executeMigration = true;
            if ($io->isInteractive()) {
                $answer = $io->ask('  Execute migration now? [Y/n] ', 'y');
                $executeMigration = strtolower(trim($answer)) !== 'n';
            }

            if ($executeMigration) {
                $io->write('  Running migration...');
                exec("php $consolePath doctrine:migrations:migrate --no-interaction 2>&1", $output, $returnVar);

                if ($returnVar === 0) {
                    $io->write('  <fg=green>✓</> Migration executed successfully');
                } else {
                    $io->writeError('  <error>Migration failed. Please run manually.</error>');
                    foreach ($output as $line) {
                        $io->write('    ' . $line);
                    }
                }
            }
        } else {
            $io->writeError('  <error>Failed to generate migration.</error>');
            foreach ($output as $line) {
                $io->write('    ' . $line);
            }
        }
    }

    private static function getProjectDir(): string
    {
        // Try to find project root
        $dir = getcwd();

        // Look for composer.json in parent directories
        while ($dir !== dirname($dir)) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        return getcwd();
    }

    /**
     * Auto-register the bundle in config/bundles.php
     */
    private static function registerBundle($io, string $projectDir, Filesystem $filesystem): void
    {
        $bundlesFile = $projectDir . '/config/bundles.php';

        if (!$filesystem->exists($bundlesFile)) {
            $io->write('  <fg=yellow>⊘</> config/bundles.php not found, skipping auto-registration');

            return;
        }

        $content = file_get_contents($bundlesFile);
        $bundleClass = 'BetterAuth\\Symfony\\BetterAuthBundle::class';

        // Check if already registered
        if (strpos($content, $bundleClass) !== false) {
            $io->write('  <fg=green>✓</> Bundle already registered in config/bundles.php');

            return;
        }

        // Register the bundle
        $lines = explode("\n", $content);
        $newLines = [];
        $registered = false;

        foreach ($lines as $line) {
            $newLines[] = $line;

            // Insert before the closing array bracket
            if (!$registered && strpos($line, 'return [') !== false) {
                // Find next line after 'return ['
                continue;
            }

            if (!$registered && strpos($line, '];') !== false) {
                // Insert before the closing bracket
                array_pop($newLines); // Remove the ];
                $newLines[] = "    BetterAuth\\Symfony\\BetterAuthBundle::class => ['all' => true],";
                $newLines[] = $line; // Re-add ];
                $registered = true;
            }
        }

        if ($registered) {
            $filesystem->dumpFile($bundlesFile, implode("\n", $newLines));
            $io->write('  <fg=green>✓</> Bundle auto-registered in config/bundles.php');
        } else {
            $io->write('  <fg=yellow>⊘</> Could not auto-register bundle, please add manually:');
            $io->write("      <comment>BetterAuth\\Symfony\\BetterAuthBundle::class => ['all' => true],</comment>");
        }
    }
}
