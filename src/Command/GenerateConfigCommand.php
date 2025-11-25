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
    name: 'better-auth:generate-config',
    description: 'Generate BetterAuth configuration with presets (minimal, standard, enterprise)'
)]
class GenerateConfigCommand extends Command
{
    private const PRESETS = [
        'minimal' => [
            'name' => 'Minimal',
            'description' => 'Basic API mode setup - just authentication, no extras',
            'config' => [
                'better_auth' => [
                    'mode' => 'api',
                    'secret' => '%env(BETTER_AUTH_SECRET)%',
                    'token' => [
                        'lifetime' => 3600,
                        'refresh_lifetime' => 2592000,
                    ],
                ],
            ],
            'env' => [
                'BETTER_AUTH_SECRET' => 'change_me_in_production_use_32_chars_min',
            ],
        ],
        'standard' => [
            'name' => 'Standard',
            'description' => 'API mode with OAuth (Google, GitHub) and 2FA support',
            'config' => [
                'better_auth' => [
                    'mode' => 'api',
                    'secret' => '%env(BETTER_AUTH_SECRET)%',
                    'token' => [
                        'lifetime' => 3600,
                        'refresh_lifetime' => 2592000,
                    ],
                    'oauth' => [
                        'providers' => [
                            'google' => [
                                'enabled' => true,
                                'client_id' => '%env(GOOGLE_CLIENT_ID)%',
                                'client_secret' => '%env(GOOGLE_CLIENT_SECRET)%',
                                'redirect_uri' => '%env(APP_URL)%/auth/oauth/google/callback',
                            ],
                            'github' => [
                                'enabled' => true,
                                'client_id' => '%env(GITHUB_CLIENT_ID)%',
                                'client_secret' => '%env(GITHUB_CLIENT_SECRET)%',
                                'redirect_uri' => '%env(APP_URL)%/auth/oauth/github/callback',
                            ],
                        ],
                    ],
                    'two_factor' => [
                        'enabled' => true,
                        'issuer' => 'MyApp',
                        'backup_codes_count' => 10,
                    ],
                ],
            ],
            'env' => [
                'BETTER_AUTH_SECRET' => 'change_me_in_production_use_32_chars_min',
                'APP_URL' => 'https://myapp.com',
                'GOOGLE_CLIENT_ID' => 'your-google-client-id',
                'GOOGLE_CLIENT_SECRET' => 'your-google-client-secret',
                'GITHUB_CLIENT_ID' => 'your-github-client-id',
                'GITHUB_CLIENT_SECRET' => 'your-github-client-secret',
            ],
        ],
        'enterprise' => [
            'name' => 'Enterprise',
            'description' => 'Full-featured: Hybrid mode, all OAuth providers, 2FA, multi-tenant',
            'config' => [
                'better_auth' => [
                    'mode' => 'hybrid',
                    'secret' => '%env(BETTER_AUTH_SECRET)%',
                    'session' => [
                        'lifetime' => 604800,
                        'cookie_name' => 'better_auth_session',
                    ],
                    'token' => [
                        'lifetime' => 3600,
                        'refresh_lifetime' => 2592000,
                    ],
                    'oauth' => [
                        'providers' => [
                            'google' => [
                                'enabled' => true,
                                'client_id' => '%env(GOOGLE_CLIENT_ID)%',
                                'client_secret' => '%env(GOOGLE_CLIENT_SECRET)%',
                                'redirect_uri' => '%env(APP_URL)%/auth/oauth/google/callback',
                            ],
                            'github' => [
                                'enabled' => true,
                                'client_id' => '%env(GITHUB_CLIENT_ID)%',
                                'client_secret' => '%env(GITHUB_CLIENT_SECRET)%',
                                'redirect_uri' => '%env(APP_URL)%/auth/oauth/github/callback',
                            ],
                            'microsoft' => [
                                'enabled' => true,
                                'client_id' => '%env(MICROSOFT_CLIENT_ID)%',
                                'client_secret' => '%env(MICROSOFT_CLIENT_SECRET)%',
                                'redirect_uri' => '%env(APP_URL)%/auth/oauth/microsoft/callback',
                            ],
                            'facebook' => [
                                'enabled' => false,
                                'client_id' => '%env(FACEBOOK_CLIENT_ID)%',
                                'client_secret' => '%env(FACEBOOK_CLIENT_SECRET)%',
                                'redirect_uri' => '%env(APP_URL)%/auth/oauth/facebook/callback',
                            ],
                            'discord' => [
                                'enabled' => false,
                                'client_id' => '%env(DISCORD_CLIENT_ID)%',
                                'client_secret' => '%env(DISCORD_CLIENT_SECRET)%',
                                'redirect_uri' => '%env(APP_URL)%/auth/oauth/discord/callback',
                            ],
                        ],
                    ],
                    'multi_tenant' => [
                        'enabled' => true,
                        'default_role' => 'member',
                    ],
                    'two_factor' => [
                        'enabled' => true,
                        'issuer' => 'MyApp',
                        'backup_codes_count' => 10,
                    ],
                ],
            ],
            'env' => [
                'BETTER_AUTH_SECRET' => 'change_me_in_production_use_32_chars_min',
                'APP_URL' => 'https://myapp.com',
                'GOOGLE_CLIENT_ID' => 'your-google-client-id',
                'GOOGLE_CLIENT_SECRET' => 'your-google-client-secret',
                'GITHUB_CLIENT_ID' => 'your-github-client-id',
                'GITHUB_CLIENT_SECRET' => 'your-github-client-secret',
                'MICROSOFT_CLIENT_ID' => 'your-microsoft-client-id',
                'MICROSOFT_CLIENT_SECRET' => 'your-microsoft-client-secret',
                'FACEBOOK_CLIENT_ID' => 'your-facebook-client-id',
                'FACEBOOK_CLIENT_SECRET' => 'your-facebook-client-secret',
                'DISCORD_CLIENT_ID' => 'your-discord-client-id',
                'DISCORD_CLIENT_SECRET' => 'your-discord-client-secret',
            ],
        ],
    ];

    public function __construct(
        private readonly string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('preset', 'p', InputOption::VALUE_REQUIRED, 'Configuration preset: minimal, standard, enterprise')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: config/packages/better_auth.yaml)')
            ->addOption('with-comments', null, InputOption::VALUE_NONE, 'Include explanatory comments in the output')
            ->addOption('env-file', null, InputOption::VALUE_NONE, 'Also generate/update .env variables')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration without confirmation')
            ->setHelp($this->getDetailedHelp());
    }

    private function getDetailedHelp(): string
    {
        return <<<'HELP'
<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>
<fg=cyan;options=bold>                    BetterAuth Configuration Generator</>
<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>

The <info>better-auth:generate-config</info> command generates a complete BetterAuth configuration
file based on predefined presets or custom options.

<fg=yellow;options=bold>PRESETS</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <info>minimal</info>
    Basic API mode setup. Perfect for simple applications.
    • API mode (stateless tokens)
    • No OAuth providers
    • No 2FA
    • No multi-tenant

  <info>standard</info>
    Most common setup for modern applications.
    • API mode
    • Google & GitHub OAuth
    • 2FA enabled
    • No multi-tenant

  <info>enterprise</info>
    Full-featured configuration for complex applications.
    • Hybrid mode (tokens + sessions)
    • All OAuth providers configured
    • 2FA enabled
    • Multi-tenant enabled

<fg=yellow;options=bold>USAGE EXAMPLES</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <comment># Interactive mode - choose preset</comment>
  <info>php bin/console better-auth:generate-config</info>

  <comment># Generate minimal configuration</comment>
  <info>php bin/console better-auth:generate-config --preset=minimal</info>

  <comment># Generate standard config with comments</comment>
  <info>php bin/console better-auth:generate-config --preset=standard --with-comments</info>

  <comment># Generate enterprise config and update .env</comment>
  <info>php bin/console better-auth:generate-config --preset=enterprise --env-file</info>

  <comment># Output to custom location</comment>
  <info>php bin/console better-auth:generate-config --preset=standard --output=config/auth.yaml</info>

  <comment># Force overwrite existing config</comment>
  <info>php bin/console better-auth:generate-config --preset=minimal --force</info>

HELP;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        $io->title('BetterAuth Configuration Generator');

        // Get preset
        $preset = $input->getOption('preset');

        if (!$preset) {
            $io->section('Available Presets');

            foreach (self::PRESETS as $key => $info) {
                $io->writeln(sprintf('  <info>%s</info> - %s', $key, $info['name']));
                $io->writeln(sprintf('    %s', $info['description']));
                $io->newLine();
            }

            $preset = $io->choice(
                'Select a configuration preset',
                array_keys(self::PRESETS),
                'standard'
            );
        }

        if (!isset(self::PRESETS[$preset])) {
            $io->error(sprintf('Invalid preset "%s". Valid presets are: minimal, standard, enterprise', $preset));
            return Command::FAILURE;
        }

        $presetConfig = self::PRESETS[$preset];

        // Determine output path
        $outputPath = $input->getOption('output') ?? $this->projectDir . '/config/packages/better_auth.yaml';
        $withComments = $input->getOption('with-comments');
        $updateEnv = $input->getOption('env-file');
        $force = $input->getOption('force');

        // Check if file exists
        if ($filesystem->exists($outputPath) && !$force) {
            if (!$io->confirm(sprintf('Configuration file already exists at %s. Overwrite?', $outputPath), false)) {
                $io->note('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Generate YAML
        $io->section('Generating Configuration');

        if ($withComments) {
            $yaml = $this->generateYamlWithComments($presetConfig['config'], $preset);
        } else {
            $yaml = Yaml::dump($presetConfig['config'], 6, 2);
        }

        // Write configuration file
        $filesystem->dumpFile($outputPath, $yaml);
        $io->writeln(sprintf('<info>✓</info> Configuration written to: %s', $outputPath));

        // Update .env if requested
        if ($updateEnv) {
            $this->updateEnvFile($io, $filesystem, $presetConfig['env']);
        }

        // Show summary
        $io->section('Configuration Summary');
        $io->definitionList(
            ['Preset' => $presetConfig['name']],
            ['Mode' => $presetConfig['config']['better_auth']['mode']],
            ['Output' => $outputPath]
        );

        // Show features
        $features = [];
        $config = $presetConfig['config']['better_auth'];

        if (isset($config['oauth']['providers'])) {
            /** @var array<string, array{enabled: bool, client_id: string, client_secret: string, redirect_uri: string}> $providers */
            $providers = $config['oauth']['providers'];
            $enabledProviders = array_filter(
                $providers,
                static fn(array $p): bool => $p['enabled']
            );
            if (count($enabledProviders) > 0) {
                $features[] = 'OAuth: ' . implode(', ', array_keys($enabledProviders));
            }
        }

        if (($config['two_factor']['enabled'] ?? false)) {
            $features[] = '2FA: Enabled';
        }

        if (($config['multi_tenant']['enabled'] ?? false)) {
            $features[] = 'Multi-tenant: Enabled';
        }

        if (!empty($features)) {
            $io->listing($features);
        }

        $io->success(sprintf('BetterAuth configuration generated successfully using "%s" preset!', $preset));

        $io->note([
            'Remember to update your .env file with actual credentials.',
            'Run "php bin/console cache:clear" to apply changes.',
        ]);

        return Command::SUCCESS;
    }

    private function generateYamlWithComments(array $config, string $preset): string
    {
        $lines = [];
        $lines[] = '# BetterAuth Configuration';
        $lines[] = '# Generated with preset: ' . $preset;
        $lines[] = '# Documentation: https://github.com/your-org/betterauth-symfony';
        $lines[] = '';

        $lines[] = 'better_auth:';
        $lines[] = '    # Authentication mode: "api", "session", or "hybrid"';
        $lines[] = '    # - api: Stateless Paseto V4 tokens (SPAs, mobile apps)';
        $lines[] = '    # - session: Cookie-based sessions (traditional web apps)';
        $lines[] = '    # - hybrid: Both tokens and sessions (maximum flexibility)';
        $lines[] = sprintf('    mode: %s', $config['better_auth']['mode']);
        $lines[] = '';

        $lines[] = '    # Secret key for token encryption (MUST be changed in production)';
        $lines[] = '    # Generate with: php -r "echo bin2hex(random_bytes(32));"';
        $lines[] = sprintf('    secret: %s', $config['better_auth']['secret']);
        $lines[] = '';

        if (isset($config['better_auth']['session'])) {
            $lines[] = '    # Session configuration (for session/hybrid modes)';
            $lines[] = '    session:';
            $lines[] = '        # Session lifetime in seconds (default: 7 days)';
            $lines[] = sprintf('        lifetime: %d', $config['better_auth']['session']['lifetime']);
            $lines[] = '        # Cookie name for session storage';
            $lines[] = sprintf('        cookie_name: %s', $config['better_auth']['session']['cookie_name']);
            $lines[] = '';
        }

        if (isset($config['better_auth']['token'])) {
            $lines[] = '    # Token configuration (for api/hybrid modes)';
            $lines[] = '    token:';
            $lines[] = '        # Access token lifetime in seconds (default: 1 hour)';
            $lines[] = sprintf('        lifetime: %d', $config['better_auth']['token']['lifetime']);
            $lines[] = '        # Refresh token lifetime in seconds (default: 30 days)';
            $lines[] = sprintf('        refresh_lifetime: %d', $config['better_auth']['token']['refresh_lifetime']);
            $lines[] = '';
        }

        if (isset($config['better_auth']['oauth'])) {
            $lines[] = '    # OAuth provider configuration';
            $lines[] = '    # Get credentials from provider developer consoles';
            $lines[] = '    oauth:';
            $lines[] = '        providers:';

            foreach ($config['better_auth']['oauth']['providers'] as $provider => $providerConfig) {
                $lines[] = sprintf('            %s:', $provider);
                $lines[] = sprintf('                enabled: %s', $providerConfig['enabled'] ? 'true' : 'false');
                $lines[] = sprintf('                client_id: %s', $providerConfig['client_id']);
                $lines[] = sprintf('                client_secret: %s', $providerConfig['client_secret']);
                $lines[] = sprintf('                redirect_uri: %s', $providerConfig['redirect_uri']);
            }
            $lines[] = '';
        }

        if (isset($config['better_auth']['multi_tenant'])) {
            $lines[] = '    # Multi-tenant (organizations/teams) configuration';
            $lines[] = '    multi_tenant:';
            $lines[] = sprintf('        enabled: %s', $config['better_auth']['multi_tenant']['enabled'] ? 'true' : 'false');
            $lines[] = sprintf('        default_role: %s', $config['better_auth']['multi_tenant']['default_role']);
            $lines[] = '';
        }

        if (isset($config['better_auth']['two_factor'])) {
            $lines[] = '    # Two-factor authentication (TOTP)';
            $lines[] = '    two_factor:';
            $lines[] = sprintf('        enabled: %s', $config['better_auth']['two_factor']['enabled'] ? 'true' : 'false');
            $lines[] = '        # Name shown in authenticator apps (Google Authenticator, Authy, etc.)';
            $lines[] = sprintf('        issuer: %s', $config['better_auth']['two_factor']['issuer']);
            $lines[] = '        # Number of backup codes to generate';
            $lines[] = sprintf('        backup_codes_count: %d', $config['better_auth']['two_factor']['backup_codes_count']);
        }

        return implode("\n", $lines) . "\n";
    }

    private function updateEnvFile(SymfonyStyle $io, Filesystem $filesystem, array $envVars): void
    {
        $envPath = $this->projectDir . '/.env';
        $envLocalPath = $this->projectDir . '/.env.local';

        // Prefer .env.local for local overrides
        $targetPath = $filesystem->exists($envLocalPath) ? $envLocalPath : $envPath;

        $io->section('Updating Environment Variables');

        $existingContent = $filesystem->exists($targetPath) ? file_get_contents($targetPath) : '';
        $newVars = [];

        foreach ($envVars as $key => $value) {
            // Check if variable already exists
            if (preg_match('/^' . preg_quote($key, '/') . '=/m', $existingContent)) {
                $io->writeln(sprintf('<comment>⚠</comment> %s already exists (skipped)', $key));
            } else {
                $newVars[] = sprintf('%s=%s', $key, $value);
                $io->writeln(sprintf('<info>✓</info> %s added', $key));
            }
        }

        if (!empty($newVars)) {
            $addition = "\n# BetterAuth Configuration\n" . implode("\n", $newVars) . "\n";
            $filesystem->appendToFile($targetPath, $addition);
            $io->writeln(sprintf('<info>✓</info> Environment variables written to: %s', $targetPath));
        } else {
            $io->note('No new environment variables to add.');
        }
    }
}
