<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Installer;

/**
 * Shared UI helper methods for installer services.
 */
trait InstallerOutputTrait
{
    /**
     * Format an array of items as a colored status string.
     *
     * @param array<string, mixed> $items
     */
    private function formatStatus(array $items): string
    {
        if (empty($items)) {
            return '<fg=red>None</>';
        }

        return '<fg=green>' . implode(', ', array_keys($items)) . '</>';
    }

    /**
     * Format a boolean as a colored yes/no string.
     */
    private function formatBool(bool $value): string
    {
        return $value ? '<fg=green>✓ Yes</>' : '<fg=red>✗ No</>';
    }

    /**
     * Resolve the Symfony project root directory by searching for composer.json.
     */
    private function getProjectDir(): string
    {
        $dir = getcwd();

        while ($dir !== dirname($dir)) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        return getcwd();
    }
}
