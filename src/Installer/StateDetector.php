<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Installer;

use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Detects the current BetterAuth installation state in a Symfony project.
 */
class StateDetector
{
    use InstallerOutputTrait;

    public function __construct(private readonly Filesystem $filesystem)
    {
    }

    /**
     * Detect which BetterAuth components are already installed.
     *
     * @return array<string, mixed>
     */
    public function detectCurrentState(string $projectDir): array
    {
        return [
            'entities' => [
                'User' => $this->filesystem->exists($projectDir . '/src/Entity/User.php'),
                'Session' => $this->filesystem->exists($projectDir . '/src/Entity/Session.php'),
                'RefreshToken' => $this->filesystem->exists($projectDir . '/src/Entity/RefreshToken.php'),
                'AccountLink' => $this->filesystem->exists($projectDir . '/src/Entity/AccountLink.php'),
                'DeviceInfo' => $this->filesystem->exists($projectDir . '/src/Entity/DeviceInfo.php'),
                'SecurityEvent' => $this->filesystem->exists($projectDir . '/src/Entity/SecurityEvent.php'),
                'SuspiciousActivity' => $this->filesystem->exists($projectDir . '/src/Entity/SuspiciousActivity.php'),
                'SessionActivity' => $this->filesystem->exists($projectDir . '/src/Entity/SessionActivity.php'),
            ],
            'controller' => $this->filesystem->exists($projectDir . '/src/Controller/AuthController.php'),
            'config' => $this->filesystem->exists($projectDir . '/config/packages/better_auth.yaml'),
            'bundle_registered' => $this->isBundleRegistered($projectDir),
            'env_has_secret' => $this->envHasSecret($projectDir),
            'migrations_dir' => $this->filesystem->exists($projectDir . '/migrations'),
        ];
    }

    /**
     * Display a summary of the current installation state to the user.
     *
     * @param array<string, mixed> $state
     */
    public function displayCurrentState(SymfonyStyle $io, array $state): void
    {
        $hasExistingSetup = $state['entities']['User'] || $state['controller'] || $state['config'];

        if ($hasExistingSetup) {
            $io->section('📊 Current Installation State');
            $io->writeln([
                sprintf('  Entities: %s', $this->formatStatus(array_filter($state['entities']))),
                sprintf('  Controller: %s', $this->formatBool($state['controller'])),
                sprintf('  Configuration: %s', $this->formatBool($state['config'])),
                sprintf('  Bundle: %s', $this->formatBool($state['bundle_registered'])),
            ]);
            $io->newLine();
            $io->note('Existing files will be detected. You\'ll be asked before overwriting.');
            $io->newLine();
        }
    }

    /**
     * Check whether BetterAuthBundle is registered in config/bundles.php.
     */
    public function isBundleRegistered(string $projectDir): bool
    {
        $bundlesFile = $projectDir . '/config/bundles.php';
        if (!$this->filesystem->exists($bundlesFile)) {
            return false;
        }

        $content = file_get_contents($bundlesFile);

        return str_contains($content, 'BetterAuthBundle');
    }

    /**
     * Check whether BETTER_AUTH_SECRET is already defined in the .env file.
     */
    public function envHasSecret(string $projectDir): bool
    {
        $envFile = $projectDir . '/.env';
        if (!file_exists($envFile)) {
            return false;
        }

        $content = file_get_contents($envFile);

        return str_contains($content, 'BETTER_AUTH_SECRET=');
    }
}
