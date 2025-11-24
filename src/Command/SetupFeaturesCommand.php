<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'better-auth:setup-features',
    description: 'Interactively enable or disable BetterAuth features'
)]
class SetupFeaturesCommand extends Command
{
    private const FEATURES = [
        'email_password' => [
            'name' => 'Email/Password Authentication',
            'description' => 'Classic email and password login',
            'default' => true,
            'required' => true,
        ],
        'oauth' => [
            'name' => 'OAuth 2.0 Providers',
            'description' => 'Login with Google, GitHub, Facebook, etc.',
            'default' => false,
            'required' => false,
            'providers' => ['google', 'github', 'facebook', 'microsoft', 'discord', 'apple', 'twitter'],
        ],
        'two_factor' => [
            'name' => 'Two-Factor Authentication (2FA)',
            'description' => 'TOTP codes with Google Authenticator',
            'default' => false,
            'required' => false,
        ],
        'magic_link' => [
            'name' => 'Magic Link (Passwordless)',
            'description' => 'Login via email link without password',
            'default' => false,
            'required' => false,
        ],
        'email_verification' => [
            'name' => 'Email Verification',
            'description' => 'Verify user email addresses',
            'default' => true,
            'required' => false,
        ],
        'password_reset' => [
            'name' => 'Password Reset',
            'description' => 'Forgot password functionality',
            'default' => true,
            'required' => false,
        ],
        'session_management' => [
            'name' => 'Session Management',
            'description' => 'View and revoke active sessions',
            'default' => true,
            'required' => false,
        ],
        'device_tracking' => [
            'name' => 'Device Tracking',
            'description' => 'Track user devices and locations',
            'default' => false,
            'required' => false,
        ],
        'security_monitoring' => [
            'name' => 'Security Monitoring',
            'description' => 'Detect suspicious activities and threats',
            'default' => false,
            'required' => false,
        ],
        'guest_sessions' => [
            'name' => 'Guest Sessions',
            'description' => 'Anonymous sessions that can be converted to users',
            'default' => false,
            'required' => false,
        ],
        'passkeys' => [
            'name' => 'Passkeys (WebAuthn)',
            'description' => 'Passwordless login with biometrics or security keys',
            'default' => false,
            'required' => false,
        ],
        'multi_tenant' => [
            'name' => 'Multi-Tenant (Organizations)',
            'description' => 'Organizations, teams, and member management',
            'default' => false,
            'required' => false,
        ],
    ];

    private Filesystem $filesystem;
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        parent::__construct();
        $this->filesystem = new Filesystem();
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List all available features')
            ->addOption('enable', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Enable specific features')
            ->addOption('disable', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Disable specific features')
            ->addOption('preset', 'p', InputOption::VALUE_REQUIRED, 'Use a preset (minimal, standard, full)')
            ->setHelp($this->getDetailedHelp());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('BetterAuth Feature Setup');

        // List mode
        if ($input->getOption('list')) {
            return $this->listFeatures($io);
        }

        // Preset mode
        $preset = $input->getOption('preset');
        if ($preset) {
            return $this->applyPreset($io, $preset);
        }

        // Enable/Disable mode
        $enableFeatures = $input->getOption('enable');
        $disableFeatures = $input->getOption('disable');
        if (!empty($enableFeatures) || !empty($disableFeatures)) {
            return $this->toggleFeatures($io, $enableFeatures, $disableFeatures);
        }

        // Interactive mode
        return $this->interactiveSetup($io);
    }

    private function listFeatures(SymfonyStyle $io): int
    {
        $io->section('Available Features');

        $rows = [];
        foreach (self::FEATURES as $key => $feature) {
            $status = $feature['required'] ? '<fg=green>Required</>' : ($feature['default'] ? '<fg=blue>Default</>' : '<fg=gray>Optional</>');
            $rows[] = [
                $key,
                $feature['name'],
                $feature['description'],
                $status,
            ];
        }

        $io->table(['Key', 'Feature', 'Description', 'Status'], $rows);

        $io->section('Available Presets');
        $io->listing([
            '<info>minimal</info> - Email/Password only (fastest setup)',
            '<info>standard</info> - Email/Password + Email Verification + Password Reset + Session Management',
            '<info>full</info> - All features enabled',
        ]);

        $io->section('Usage Examples');
        $io->text([
            '<comment># Interactive setup</comment>',
            'php bin/console better-auth:setup-features',
            '',
            '<comment># Use a preset</comment>',
            'php bin/console better-auth:setup-features --preset=standard',
            '',
            '<comment># Enable specific features</comment>',
            'php bin/console better-auth:setup-features --enable=oauth --enable=two_factor',
            '',
            '<comment># Disable specific features</comment>',
            'php bin/console better-auth:setup-features --disable=device_tracking',
        ]);

        return Command::SUCCESS;
    }

    private function interactiveSetup(SymfonyStyle $io): int
    {
        $io->section('Select Features to Enable');

        $selectedFeatures = [];

        // Group features by category
        $categories = [
            'Authentication' => ['email_password', 'oauth', 'magic_link', 'passkeys'],
            'Security' => ['two_factor', 'security_monitoring', 'device_tracking'],
            'User Management' => ['email_verification', 'password_reset', 'session_management'],
            'Advanced' => ['guest_sessions', 'multi_tenant'],
        ];

        foreach ($categories as $category => $featureKeys) {
            $io->text("\n<fg=yellow;options=bold>$category</>");
            $io->text(str_repeat('─', 50));

            foreach ($featureKeys as $key) {
                $feature = self::FEATURES[$key];

                if ($feature['required']) {
                    $selectedFeatures[$key] = true;
                    $io->text("  <fg=green>✓</> {$feature['name']} <fg=gray>(required)</>");
                    continue;
                }

                $default = $feature['default'] ? 'yes' : 'no';
                $enabled = $io->confirm(
                    "  Enable {$feature['name']}? ({$feature['description']})",
                    $feature['default']
                );
                $selectedFeatures[$key] = $enabled;

                // If OAuth enabled, ask for providers
                if ($key === 'oauth' && $enabled && isset($feature['providers'])) {
                    $providers = $io->choice(
                        '    Select OAuth providers to enable',
                        $feature['providers'],
                        null,
                        true
                    );
                    $selectedFeatures['oauth_providers'] = $providers;
                }
            }
        }

        // Show summary
        $io->section('Feature Summary');
        $enabledFeatures = array_filter($selectedFeatures, fn($v) => $v === true);
        $disabledFeatures = array_filter($selectedFeatures, fn($v) => $v === false);

        $io->text('<fg=green>Enabled:</>');
        foreach ($enabledFeatures as $key => $value) {
            if ($key === 'oauth_providers') {
                continue;
            }
            $io->text("  ✓ " . self::FEATURES[$key]['name']);
        }

        if (!empty($selectedFeatures['oauth_providers'])) {
            $io->text("    OAuth Providers: " . implode(', ', $selectedFeatures['oauth_providers']));
        }

        $io->text("\n<fg=red>Disabled:</>");
        foreach ($disabledFeatures as $key => $value) {
            $io->text("  ✗ " . self::FEATURES[$key]['name']);
        }

        if (!$io->confirm("\nApply this configuration?", true)) {
            $io->warning('Setup cancelled.');
            return Command::SUCCESS;
        }

        return $this->applyConfiguration($io, $selectedFeatures);
    }

    private function applyPreset(SymfonyStyle $io, string $preset): int
    {
        $presets = [
            'minimal' => ['email_password'],
            'standard' => ['email_password', 'email_verification', 'password_reset', 'session_management'],
            'full' => array_keys(self::FEATURES),
        ];

        if (!isset($presets[$preset])) {
            $io->error("Unknown preset: $preset. Available: minimal, standard, full");
            return Command::FAILURE;
        }

        $selectedFeatures = [];
        foreach (self::FEATURES as $key => $feature) {
            $selectedFeatures[$key] = in_array($key, $presets[$preset], true);
        }

        $io->success("Applying '$preset' preset...");
        return $this->applyConfiguration($io, $selectedFeatures);
    }

    private function toggleFeatures(SymfonyStyle $io, array $enable, array $disable): int
    {
        // Load current config
        $configFile = $this->projectDir . '/config/packages/better_auth.yaml';
        $config = [];

        if ($this->filesystem->exists($configFile)) {
            $config = Yaml::parseFile($configFile) ?? [];
        }

        // Apply changes
        foreach ($enable as $feature) {
            if (!isset(self::FEATURES[$feature])) {
                $io->warning("Unknown feature: $feature");
                continue;
            }
            $io->text("<fg=green>✓</> Enabling: " . self::FEATURES[$feature]['name']);
            $config = $this->enableFeatureInConfig($config, $feature);
        }

        foreach ($disable as $feature) {
            if (!isset(self::FEATURES[$feature])) {
                $io->warning("Unknown feature: $feature");
                continue;
            }
            if (self::FEATURES[$feature]['required']) {
                $io->warning("Cannot disable required feature: $feature");
                continue;
            }
            $io->text("<fg=red>✗</> Disabling: " . self::FEATURES[$feature]['name']);
            $config = $this->disableFeatureInConfig($config, $feature);
        }

        // Save config
        $this->filesystem->dumpFile($configFile, Yaml::dump($config, 4));
        $io->success('Configuration updated!');

        return Command::SUCCESS;
    }

    private function applyConfiguration(SymfonyStyle $io, array $selectedFeatures): int
    {
        $configFile = $this->projectDir . '/config/packages/better_auth.yaml';

        // Load or create config
        $config = [
            'better_auth' => [
                'mode' => 'api',
                'secret' => '%env(BETTER_AUTH_SECRET)%',
            ],
        ];

        // Apply feature configurations
        foreach ($selectedFeatures as $feature => $enabled) {
            if ($feature === 'oauth_providers') {
                continue;
            }
            if ($enabled) {
                $config = $this->enableFeatureInConfig($config, $feature);
            }
        }

        // Handle OAuth providers
        if (!empty($selectedFeatures['oauth_providers'])) {
            $config['better_auth']['oauth'] = ['providers' => []];
            foreach ($selectedFeatures['oauth_providers'] as $provider) {
                $config['better_auth']['oauth']['providers'][$provider] = [
                    'enabled' => true,
                    'client_id' => "%env({$this->getEnvVarName($provider, 'CLIENT_ID')})",
                    'client_secret' => "%env({$this->getEnvVarName($provider, 'CLIENT_SECRET')})",
                ];
            }
        }

        // Save configuration
        $this->filesystem->dumpFile($configFile, Yaml::dump($config, 4));

        // Generate services configuration
        $this->generateServicesConfig($io, $selectedFeatures);

        // Update .env
        $this->updateEnvFile($io, $selectedFeatures);

        $io->success('Features configured successfully!');
        $io->section('Next Steps');
        $io->listing([
            'Run <info>php bin/console doctrine:migrations:diff</info> to generate migrations',
            'Run <info>php bin/console doctrine:migrations:migrate</info> to apply migrations',
            'Configure OAuth credentials in <info>.env</info> if OAuth is enabled',
        ]);

        return Command::SUCCESS;
    }

    private function enableFeatureInConfig(array $config, string $feature): array
    {
        switch ($feature) {
            case 'two_factor':
                $config['better_auth']['two_factor'] = [
                    'enabled' => true,
                    'issuer' => '%env(APP_NAME)%',
                    'backup_codes_count' => 10,
                ];
                break;

            case 'oauth':
                $config['better_auth']['oauth'] = ['providers' => []];
                break;

            case 'multi_tenant':
                $config['better_auth']['multi_tenant'] = [
                    'enabled' => true,
                    'default_role' => 'member',
                ];
                break;

            case 'session_management':
                $config['better_auth']['session'] = [
                    'lifetime' => 604800, // 7 days
                    'cookie_name' => 'better_auth_session',
                ];
                break;

            case 'device_tracking':
            case 'security_monitoring':
            case 'guest_sessions':
            case 'passkeys':
            case 'magic_link':
            case 'email_verification':
            case 'password_reset':
                // These features are enabled by default when their services are loaded
                break;
        }

        return $config;
    }

    private function disableFeatureInConfig(array $config, string $feature): array
    {
        switch ($feature) {
            case 'two_factor':
                $config['better_auth']['two_factor']['enabled'] = false;
                break;

            case 'multi_tenant':
                $config['better_auth']['multi_tenant']['enabled'] = false;
                break;

            case 'oauth':
                unset($config['better_auth']['oauth']);
                break;
        }

        return $config;
    }

    private function generateServicesConfig(SymfonyStyle $io, array $selectedFeatures): void
    {
        $servicesFile = $this->projectDir . '/config/services_betterauth.yaml';

        $services = ['services' => []];

        // Add repository configurations based on features
        if ($selectedFeatures['guest_sessions'] ?? false) {
            $services['services']['BetterAuth\\Symfony\\Storage\\Doctrine\\DoctrineGuestSessionRepository'] = '~';
            $services['services']['BetterAuth\\Providers\\GuestSessionProvider\\GuestSessionProvider'] = [
                'arguments' => ['$sessionLifetime' => 86400],
            ];
        }

        if (!empty($services['services'])) {
            $this->filesystem->dumpFile($servicesFile, Yaml::dump($services, 4));
            $io->text("Created: <info>$servicesFile</info>");
        }
    }

    private function updateEnvFile(SymfonyStyle $io, array $selectedFeatures): void
    {
        $envFile = $this->projectDir . '/.env';

        if (!$this->filesystem->exists($envFile)) {
            return;
        }

        $envContent = file_get_contents($envFile);
        $additions = [];

        // Add OAuth env vars
        if (!empty($selectedFeatures['oauth_providers'])) {
            foreach ($selectedFeatures['oauth_providers'] as $provider) {
                $clientIdVar = $this->getEnvVarName($provider, 'CLIENT_ID');
                $clientSecretVar = $this->getEnvVarName($provider, 'CLIENT_SECRET');

                if (!str_contains($envContent, $clientIdVar)) {
                    $additions[] = "$clientIdVar=your_{$provider}_client_id";
                }
                if (!str_contains($envContent, $clientSecretVar)) {
                    $additions[] = "$clientSecretVar=your_{$provider}_client_secret";
                }
            }
        }

        if (!empty($additions)) {
            $envContent .= "\n###> betterauth/symfony ###\n";
            $envContent .= implode("\n", $additions);
            $envContent .= "\n###< betterauth/symfony ###\n";

            $this->filesystem->dumpFile($envFile, $envContent);
            $io->text('Updated: <info>.env</info>');
        }
    }

    private function getEnvVarName(string $provider, string $suffix): string
    {
        return strtoupper($provider) . '_' . $suffix;
    }

    private function getDetailedHelp(): string
    {
        return <<<'HELP'
<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>
<fg=cyan;options=bold>                    BetterAuth Feature Setup</>
<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>

The <info>better-auth:setup-features</info> command allows you to interactively enable
or disable BetterAuth features for your application.

<fg=yellow;options=bold>AVAILABLE FEATURES</>

  <info>Authentication</info>
    • email_password    - Classic email/password login (required)
    • oauth             - Login with Google, GitHub, Facebook, etc.
    • magic_link        - Passwordless login via email link
    • passkeys          - WebAuthn biometric/security key login

  <info>Security</info>
    • two_factor        - TOTP 2FA with Google Authenticator
    • security_monitoring - Detect suspicious activities
    • device_tracking   - Track user devices and locations

  <info>User Management</info>
    • email_verification - Verify user email addresses
    • password_reset    - Forgot password functionality
    • session_management - View and revoke active sessions

  <info>Advanced</info>
    • guest_sessions    - Anonymous sessions (convertible to users)
    • multi_tenant      - Organizations, teams, members

<fg=yellow;options=bold>PRESETS</>

  <info>minimal</info>   - Email/Password only
  <info>standard</info>  - Email/Password + Verification + Reset + Sessions
  <info>full</info>      - All features enabled

<fg=yellow;options=bold>EXAMPLES</>

  <comment># Interactive mode</comment>
  php bin/console better-auth:setup-features

  <comment># Use preset</comment>
  php bin/console better-auth:setup-features --preset=standard

  <comment># Enable specific features</comment>
  php bin/console better-auth:setup-features --enable=oauth --enable=two_factor

  <comment># Disable features</comment>
  php bin/console better-auth:setup-features --disable=device_tracking

  <comment># List all features</comment>
  php bin/console better-auth:setup-features --list

HELP;
    }
}
