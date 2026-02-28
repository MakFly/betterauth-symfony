<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Installer;

use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Handles Doctrine migration generation and execution, and displays the final installation summary.
 */
class MigrationHandler
{
    /**
     * Prompt the user and optionally generate and run Doctrine migrations.
     */
    public function handleMigrations(SymfonyStyle $io, string $projectDir): void
    {
        $io->section('💾 Database Migrations');

        if (!$io->confirm('Generate and run migrations now?', true)) {
            $io->info([
                'To generate migrations later:',
                '  php bin/console doctrine:migrations:diff',
                '  php bin/console doctrine:migrations:migrate',
            ]);

            return;
        }

        $consolePath = $projectDir . '/bin/console';
        if (!file_exists($consolePath)) {
            $io->error('Console not found. Please run migrations manually.');

            return;
        }

        // Generate migration
        $io->writeln('  Generating migration...');
        exec("php $consolePath doctrine:migrations:diff --no-interaction 2>&1", $output, $returnVar);

        if ($returnVar === 0) {
            $io->writeln('  <fg=green>✓</> Migration generated');

            if ($io->confirm('  Execute migration now?', true)) {
                $io->writeln('  Running migration...');
                exec("php $consolePath doctrine:migrations:migrate --no-interaction 2>&1", $output, $returnVar);

                if ($returnVar === 0) {
                    $io->writeln('  <fg=green>✓</> Migration executed successfully');
                } else {
                    $io->error('Migration failed. Please run manually.');
                }
            }
        } else {
            $io->warning('No migration needed or failed to generate.');
        }
    }

    /**
     * Display the final installation summary to the user.
     *
     * @param string[] $generatedEntities
     * @param string[] $providers
     */
    public function displayFinalSummary(
        SymfonyStyle $io,
        string $idStrategy,
        array $generatedEntities,
        string $mode,
        array $providers,
        string $appName
    ): void {
        $io->success('🎉 Installation Complete!');

        $io->writeln([
            '<comment>Configuration:</comment>',
            sprintf('  • ID Strategy: <info>%s</info>', strtoupper($idStrategy)),
            sprintf('  • Mode: <info>%s</info>', $mode),
            sprintf('  • App Name: <info>%s</info> (for 2FA authenticator apps)', $appName),
            sprintf('  • OAuth Providers: <info>%s</info>', empty($providers) ? 'None' : implode(', ', $providers)),
            '',
            '<comment>Generated Files:</comment>',
            sprintf('  • Entities: <info>%s</info>', empty($generatedEntities) ? 'None (skipped)' : implode(', ', $generatedEntities)),
            '  • Controllers: <info>src/Controller/*.php</info>',
            '  • Config: <info>config/packages/better_auth.yaml</info>',
            '',
        ]);

        if (!empty($providers)) {
            $io->writeln(['<comment>OAuth Configuration:</comment>']);
            $io->writeln(['  OAuth environment variables have been added to .env:']);
            foreach ($providers as $provider) {
                $upperProvider = strtoupper($provider);
                $io->writeln([
                    sprintf('    %s_CLIENT_ID=', $upperProvider),
                    sprintf('    %s_CLIENT_SECRET=', $upperProvider),
                ]);
            }
            $io->writeln(['', '  <info>Please fill in your OAuth credentials in .env</info>']);
            $io->newLine();
        }

        $io->writeln([
            '<comment>Next Steps:</comment>',
            '  1. Review configuration in config/packages/better_auth.yaml',
            '  2. Test authentication endpoints:',
            '     POST /auth/register - Create new user',
            '     POST /auth/login    - Login user',
            '     GET  /auth/me       - Get current user',
            '     POST /auth/refresh  - Refresh token',
            '',
            '  3. View API documentation at /api/docs (if API Platform installed)',
            '',
            '<info>Your BetterAuth installation is ready! 🚀</info>',
        ]);
    }
}
