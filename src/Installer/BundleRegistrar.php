<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Installer;

use Composer\Script\Event;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Auto-registers the BetterAuthBundle in config/bundles.php
 *
 * This script runs automatically after `composer require betterauth/symfony-bundle`
 */
class BundleRegistrar
{
    public static function register(Event $event): void
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $projectDir = dirname($vendorDir);
        $filesystem = new Filesystem();

        $bundlesFile = $projectDir . '/config/bundles.php';

        if (!$filesystem->exists($bundlesFile)) {
            // Not a Symfony project, skip
            return;
        }

        $content = file_get_contents($bundlesFile);
        $bundleClass = 'BetterAuth\\Symfony\\BetterAuthBundle::class';

        // Check if already registered
        if (strpos($content, $bundleClass) !== false) {
            return;
        }

        // Register the bundle
        $lines = explode("\n", $content);
        $newLines = [];
        $registered = false;

        foreach ($lines as $line) {
            if (!$registered && strpos($line, '];') !== false) {
                // Insert before the closing bracket
                $newLines[] = "    BetterAuth\\Symfony\\BetterAuthBundle::class => ['all' => true],";
            }
            $newLines[] = $line;
            if (strpos($line, '];') !== false) {
                $registered = true;
            }
        }

        if ($registered) {
            $filesystem->dumpFile($bundlesFile, implode("\n", $newLines));
            $event->getIO()->write('  <fg=green>✓</> BetterAuth bundle auto-registered in config/bundles.php');
            $event->getIO()->write('  <fg=cyan>→</> Run <info>php bin/console better-auth:install</info> to complete setup');
        }
    }
}
