<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Installer;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Collects installation configuration from interactive user prompts or CLI options.
 */
class InputCollector
{
    use InstallerOutputTrait;

    /**
     * OAuth providers with their stability status.
     */
    private const OAUTH_PROVIDERS = [
        'google' => ['name' => 'Google OAuth', 'status' => 'stable'],
        'github' => ['name' => 'GitHub OAuth', 'status' => 'draft'],
        'facebook' => ['name' => 'Facebook OAuth', 'status' => 'draft'],
        'microsoft' => ['name' => 'Microsoft OAuth', 'status' => 'draft'],
        'discord' => ['name' => 'Discord OAuth', 'status' => 'draft'],
    ];

    /**
     * List of optional User fields that can be excluded.
     */
    private const OPTIONAL_USER_FIELDS = ['name', 'avatar'];

    /**
     * Resolve the ID strategy (uuid or int) from CLI option or interactive prompt.
     *
     * @param array<string, mixed> $state
     */
    public function chooseIdStrategy(InputInterface $input, SymfonyStyle $io, array $state): string
    {
        $option = $input->getOption('id-strategy');
        if ($option && in_array($option, ['uuid', 'int'])) {
            return $option;
        }

        // Try to detect from existing User entity
        if ($state['entities']['User']) {
            $userFile = $this->getProjectDir() . '/src/Entity/User.php';
            $content = file_get_contents($userFile);
            if (preg_match('/private\s+int\s+\$id/', $content)) {
                $detected = 'int';
            } elseif (preg_match('/private\s+string\s+\$id/', $content)) {
                $detected = 'uuid';
            }

            if (isset($detected)) {
                $io->writeln(sprintf('  <info>Detected ID strategy from existing entities: %s</info>', strtoupper($detected)));
                if ($io->confirm('  Use detected strategy?', true)) {
                    return $detected;
                }
            }
        }

        return $io->choice(
            'Which ID strategy do you want to use?',
            [
                'uuid' => 'UUID - Secure, non-guessable, distributed-friendly IDs (recommended)',
                'int' => 'Integer - Standard Symfony approach, auto-increment, smaller size',
            ],
            'uuid'
        );
    }

    /**
     * Resolve the authentication mode (api, session, hybrid) from CLI option or interactive prompt.
     *
     * @param array<string, mixed> $state
     */
    public function chooseMode(InputInterface $input, SymfonyStyle $io, array $state): string
    {
        $option = $input->getOption('mode');
        if ($option && in_array($option, ['api', 'session', 'hybrid'])) {
            return $option;
        }

        // Try to detect from existing config
        if ($state['config']) {
            $configFile = $this->getProjectDir() . '/config/packages/better_auth.yaml';
            $content = file_get_contents($configFile);
            if (preg_match('/mode:\s*[\'"](\w+)[\'"]/', $content, $matches)) {
                $detected = $matches[1];
                $io->writeln(sprintf('  <info>Detected mode from existing config: %s</info>', $detected));
                if ($io->confirm('  Use detected mode?', true)) {
                    return $detected;
                }
            }
        }

        return $io->choice(
            'Which authentication mode?',
            [
                'api' => 'API Mode - Stateless tokens (Paseto V4) for REST APIs',
                'session' => 'Session Mode - Stateful cookies for traditional web apps',
                'hybrid' => 'Hybrid Mode - Support both tokens and sessions',
            ],
            'api'
        );
    }

    /**
     * Ask the user which OAuth providers to enable.
     *
     * @return string[]
     */
    public function chooseOAuthProviders(SymfonyStyle $io): array
    {
        $io->writeln([
            '',
            '<fg=yellow>🌐 OAuth Providers Configuration</>',
            '<fg=yellow>────────────────────────────────────────────────────────────────────────────────</>',
            'OAuth allows users to login with their existing accounts (Google, GitHub, etc.)',
            'You can always enable/disable providers later in better_auth.yaml',
            '',
        ]);

        if (!$io->confirm('Do you want to enable OAuth providers?', true)) {
            $io->writeln('  <fg=gray>⊘ OAuth providers skipped. You can enable them later.</>' . "\n");
            return [];
        }

        $io->writeln([
            '',
            '<info>Available OAuth Providers:</info>',
        ]);
        foreach (self::OAUTH_PROVIDERS as $key => $config) {
            /** @var 'stable'|'draft' $status */
            $status = $config['status'];
            $statusBadge = match ($status) {
                'stable' => '<fg=green>[STABLE]</>',
                'draft' => '<fg=yellow>[DRAFT]</>',
            };
            $io->writeln("  • {$config['name']} $statusBadge");
        }
        $io->newLine();

        $choices = [];
        foreach (self::OAUTH_PROVIDERS as $key => $config) {
            $statusHint = $config['status'] === 'draft' ? ' <fg=yellow>(draft)</>' : '';
            if ($io->confirm("  Enable {$config['name']}?$statusHint", $key === 'google')) {
                $choices[] = $key;
            }
        }

        if (empty($choices)) {
            $io->writeln(['', '  <fg=gray>No OAuth providers selected</>']);
        } else {
            $io->writeln(['', '  <fg=green>✓</> Selected: ' . implode(', ', array_map('ucfirst', $choices))]);
        }

        return $choices;
    }

    /**
     * Resolve the application name from CLI option or interactive prompt.
     */
    public function chooseAppName(InputInterface $input, SymfonyStyle $io): string
    {
        $option = $input->getOption('app-name');
        if ($option) {
            return $option;
        }

        $io->writeln([
            '',
            '<fg=yellow>📱 Application Name</>',
            '<fg=yellow>────────────────────────────────────────────────────────────────────────────────</>',
            'This name will be displayed in authenticator apps (Google Authenticator, Authy, etc.)',
            'when users set up Two-Factor Authentication.',
            '',
        ]);

        return $io->ask(
            'What is your application name?',
            'My App',
            function (string $value): string {
                $value = trim($value);
                if (empty($value)) {
                    throw new \RuntimeException('Application name cannot be empty.');
                }
                return $value;
            }
        );
    }

    /**
     * Resolve which optional User fields to exclude from CLI options or interactive prompt.
     *
     * @return string[] List of field names to exclude
     */
    public function chooseExcludedFields(InputInterface $input, SymfonyStyle $io): array
    {
        // Check for --minimal flag (excludes all optional fields)
        if ($input->getOption('minimal')) {
            $io->writeln([
                '',
                '  <fg=cyan>Minimal mode:</> Excluding all optional fields (name, avatar)',
            ]);
            return self::OPTIONAL_USER_FIELDS;
        }

        // Check for --exclude-fields option
        $excludeOption = $input->getOption('exclude-fields');
        if ($excludeOption !== null) {
            $fields = array_map('trim', explode(',', $excludeOption));
            $validFields = array_intersect($fields, self::OPTIONAL_USER_FIELDS);
            $invalidFields = array_diff($fields, self::OPTIONAL_USER_FIELDS);

            if (!empty($invalidFields)) {
                $io->warning(sprintf(
                    'Invalid fields ignored: %s. Valid options are: %s',
                    implode(', ', $invalidFields),
                    implode(', ', self::OPTIONAL_USER_FIELDS)
                ));
            }

            return array_values($validFields);
        }

        // Non-interactive mode: include all fields by default
        if (!$input->isInteractive()) {
            return [];
        }

        // Interactive mode
        $io->writeln([
            '',
            '<fg=yellow>👤 User Entity Fields Configuration</>',
            '<fg=yellow>────────────────────────────────────────────────────────────────────────────────</>',
            'The User entity includes optional profile fields that you can exclude.',
            'This is useful if you only need email/password authentication without user profiles.',
            '',
            '<info>Optional fields:</info>',
            '  • <fg=cyan>name</> - User display name (VARCHAR 255)',
            '  • <fg=cyan>avatar</> - User avatar URL (VARCHAR 500)',
            '',
            '<comment>Note: You can always add these fields later by editing src/Entity/User.php</comment>',
            '<comment>      or using: php bin/console better-auth:user-fields add name,avatar</comment>',
            '',
        ]);

        if (!$io->confirm('Do you want to customize User fields?', false)) {
            return [];
        }

        $choices = $io->choice(
            'Which fields do you want to EXCLUDE?',
            [
                'none' => 'Include all fields (name, avatar)',
                'name' => 'Exclude only "name" field',
                'avatar' => 'Exclude only "avatar" field',
                'all' => 'Exclude all optional fields (minimal User)',
            ],
            'none'
        );

        return match ($choices) {
            'name' => ['name'],
            'avatar' => ['avatar'],
            'all' => self::OPTIONAL_USER_FIELDS,
            default => [],
        };
    }
}
