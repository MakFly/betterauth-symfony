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
    name: 'better-auth:configure',
    description: 'Interactive configuration wizard for BetterAuth'
)]
class ConfigureCommand extends Command
{
    private const OAUTH_PROVIDERS = [
        'google' => [
            'name' => 'Google',
            'env_prefix' => 'GOOGLE',
            'docs' => 'https://console.cloud.google.com/apis/credentials',
        ],
        'github' => [
            'name' => 'GitHub',
            'env_prefix' => 'GITHUB',
            'docs' => 'https://github.com/settings/developers',
        ],
        'microsoft' => [
            'name' => 'Microsoft/Azure AD',
            'env_prefix' => 'MICROSOFT',
            'docs' => 'https://portal.azure.com/#blade/Microsoft_AAD_RegisteredApps',
        ],
        'facebook' => [
            'name' => 'Facebook',
            'env_prefix' => 'FACEBOOK',
            'docs' => 'https://developers.facebook.com/apps/',
        ],
        'discord' => [
            'name' => 'Discord',
            'env_prefix' => 'DISCORD',
            'docs' => 'https://discord.com/developers/applications',
        ],
        'twitter' => [
            'name' => 'Twitter/X',
            'env_prefix' => 'TWITTER',
            'docs' => 'https://developer.twitter.com/en/portal/projects-and-apps',
        ],
        'apple' => [
            'name' => 'Apple',
            'env_prefix' => 'APPLE',
            'docs' => 'https://developer.apple.com/account/resources/identifiers/list',
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
            ->addOption('section', 's', InputOption::VALUE_REQUIRED, 'Configure specific section: mode, tokens, session, oauth, 2fa, multi-tenant')
            ->setHelp($this->getDetailedHelp());
    }

    private function getDetailedHelp(): string
    {
        return <<<'HELP'
<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>
<fg=cyan;options=bold>                    BetterAuth Interactive Configuration Wizard</>
<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>

The <info>better-auth:configure</info> command provides an interactive wizard to configure
your BetterAuth installation step by step.

<fg=yellow;options=bold>USAGE</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <comment># Full interactive wizard</comment>
  <info>php bin/console better-auth:configure</info>

  <comment># Configure only authentication mode</comment>
  <info>php bin/console better-auth:configure --section=mode</info>

  <comment># Configure only OAuth providers</comment>
  <info>php bin/console better-auth:configure --section=oauth</info>

  <comment># Configure only 2FA settings</comment>
  <info>php bin/console better-auth:configure --section=2fa</info>

<fg=yellow;options=bold>AVAILABLE SECTIONS</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <info>mode</info>        - Authentication mode (api, session, hybrid)
  <info>tokens</info>      - Token lifetimes (access, refresh)
  <info>session</info>     - Session configuration (lifetime, cookie name)
  <info>oauth</info>       - OAuth provider configuration
  <info>2fa</info>         - Two-factor authentication settings
  <info>multi-tenant</info> - Organization/team features

HELP;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        $configPath = $this->projectDir . '/config/packages/better_auth.yaml';

        // Load existing config or create default
        if ($filesystem->exists($configPath)) {
            $config = Yaml::parseFile($configPath);
        } else {
            $config = [
                'better_auth' => [
                    'mode' => 'api',
                    'secret' => '%env(BETTER_AUTH_SECRET)%',
                ],
            ];
        }

        $io->title('BetterAuth Configuration Wizard');

        $section = $input->getOption('section');

        if ($section) {
            // Configure specific section
            $config = $this->configureSection($io, $config, $section);
        } else {
            // Full wizard
            $config = $this->runFullWizard($io, $config);
        }

        // Save configuration
        $yaml = Yaml::dump($config, 6, 2);
        $filesystem->dumpFile($configPath, $yaml);

        $io->success('Configuration saved successfully!');
        $io->note([
            'Configuration saved to: ' . $configPath,
            'Run "php bin/console cache:clear" to apply changes.',
        ]);

        return Command::SUCCESS;
    }

    private function configureSection(SymfonyStyle $io, array $config, string $section): array
    {
        switch ($section) {
            case 'mode':
                return $this->configureMode($io, $config);
            case 'tokens':
                return $this->configureTokens($io, $config);
            case 'session':
                return $this->configureSession($io, $config);
            case 'oauth':
                return $this->configureOAuth($io, $config);
            case '2fa':
                return $this->configure2FA($io, $config);
            case 'multi-tenant':
                return $this->configureMultiTenant($io, $config);
            default:
                $io->error(sprintf('Unknown section: %s', $section));
                return $config;
        }
    }

    private function runFullWizard(SymfonyStyle $io, array $config): array
    {
        $io->section('Step 1/6: Authentication Mode');
        $config = $this->configureMode($io, $config);

        $io->section('Step 2/6: Token Configuration');
        $config = $this->configureTokens($io, $config);

        if (in_array($config['better_auth']['mode'], ['session', 'hybrid'])) {
            $io->section('Step 3/6: Session Configuration');
            $config = $this->configureSession($io, $config);
        } else {
            $io->section('Step 3/6: Session Configuration');
            $io->note('Skipped - not required for API mode');
        }

        $io->section('Step 4/6: OAuth Providers');
        if ($io->confirm('Do you want to configure OAuth providers (Google, GitHub, etc.)?', false)) {
            $config = $this->configureOAuth($io, $config);
        }

        $io->section('Step 5/6: Two-Factor Authentication');
        $config = $this->configure2FA($io, $config);

        $io->section('Step 6/6: Multi-Tenant (Organizations)');
        $config = $this->configureMultiTenant($io, $config);

        // Show summary
        $this->showSummary($io, $config);

        return $config;
    }

    private function configureMode(SymfonyStyle $io, array $config): array
    {
        $currentMode = $config['better_auth']['mode'] ?? 'api';

        $io->writeln('<info>Authentication Mode</info> determines how users authenticate with your application.');
        $io->newLine();

        $io->writeln('  <comment>api</comment>     - Stateless tokens (Paseto V4) - Best for SPAs, mobile apps');
        $io->writeln('  <comment>session</comment> - Cookie-based sessions - Best for traditional web apps');
        $io->writeln('  <comment>hybrid</comment>  - Both tokens and sessions - Maximum flexibility');
        $io->newLine();

        $mode = $io->choice(
            'Select authentication mode',
            ['api', 'session', 'hybrid'],
            $currentMode
        );

        $config['better_auth']['mode'] = $mode;

        return $config;
    }

    private function configureTokens(SymfonyStyle $io, array $config): array
    {
        $currentAccessLifetime = $config['better_auth']['token']['lifetime'] ?? 3600;
        $currentRefreshLifetime = $config['better_auth']['token']['refresh_lifetime'] ?? 2592000;

        $io->writeln('<info>Token Lifetimes</info> control how long tokens remain valid.');
        $io->newLine();

        $accessLifetime = $io->choice(
            'Access token lifetime',
            [
                '900' => '15 minutes (high security)',
                '1800' => '30 minutes',
                '3600' => '1 hour (recommended)',
                '7200' => '2 hours',
                '86400' => '24 hours (convenience)',
            ],
            (string) $currentAccessLifetime
        );

        $refreshLifetime = $io->choice(
            'Refresh token lifetime',
            [
                '604800' => '7 days',
                '1209600' => '14 days',
                '2592000' => '30 days (recommended)',
                '7776000' => '90 days',
                '31536000' => '1 year (convenience)',
            ],
            (string) $currentRefreshLifetime
        );

        $config['better_auth']['token'] = [
            'lifetime' => (int) $accessLifetime,
            'refresh_lifetime' => (int) $refreshLifetime,
        ];

        return $config;
    }

    private function configureSession(SymfonyStyle $io, array $config): array
    {
        $currentLifetime = $config['better_auth']['session']['lifetime'] ?? 604800;
        $currentCookieName = $config['better_auth']['session']['cookie_name'] ?? 'better_auth_session';

        $io->writeln('<info>Session Configuration</info> for cookie-based authentication.');
        $io->newLine();

        $lifetime = $io->choice(
            'Session lifetime',
            [
                '86400' => '1 day',
                '259200' => '3 days',
                '604800' => '7 days (recommended)',
                '1209600' => '14 days',
                '2592000' => '30 days',
            ],
            (string) $currentLifetime
        );

        $cookieName = $io->ask('Session cookie name', $currentCookieName);

        $config['better_auth']['session'] = [
            'lifetime' => (int) $lifetime,
            'cookie_name' => $cookieName,
        ];

        return $config;
    }

    private function configureOAuth(SymfonyStyle $io, array $config): array
    {
        $io->writeln('<info>OAuth Providers</info> allow users to sign in with their existing accounts.');
        $io->newLine();

        $currentProviders = $config['better_auth']['oauth']['providers'] ?? [];

        $selectedProviders = $io->choice(
            'Select OAuth providers to enable',
            array_merge(['none'], array_keys(self::OAUTH_PROVIDERS)),
            null,
            true
        );

        if (in_array('none', $selectedProviders)) {
            $selectedProviders = [];
        }

        $providers = [];

        foreach ($selectedProviders as $providerKey) {
            if (!isset(self::OAUTH_PROVIDERS[$providerKey])) {
                continue;
            }

            $provider = self::OAUTH_PROVIDERS[$providerKey];
            $io->newLine();
            $io->writeln(sprintf('<info>Configuring %s OAuth</info>', $provider['name']));
            $io->writeln(sprintf('Get credentials at: <comment>%s</comment>', $provider['docs']));

            $envPrefix = $provider['env_prefix'];
            $currentConfig = $currentProviders[$providerKey] ?? [];

            $providers[$providerKey] = [
                'enabled' => true,
                'client_id' => sprintf('%%env(%s_CLIENT_ID)%%', $envPrefix),
                'client_secret' => sprintf('%%env(%s_CLIENT_SECRET)%%', $envPrefix),
                'redirect_uri' => sprintf('%%env(APP_URL)%%/auth/oauth/%s/callback', $providerKey),
            ];

            $io->note(sprintf(
                'Add to your .env: %s_CLIENT_ID=xxx and %s_CLIENT_SECRET=xxx',
                $envPrefix,
                $envPrefix
            ));
        }

        if (!empty($providers)) {
            $config['better_auth']['oauth']['providers'] = $providers;
        }

        return $config;
    }

    private function configure2FA(SymfonyStyle $io, array $config): array
    {
        $current2FA = $config['better_auth']['two_factor'] ?? [];
        $currentEnabled = $current2FA['enabled'] ?? true;
        $currentIssuer = $current2FA['issuer'] ?? 'BetterAuth';
        $currentBackupCount = $current2FA['backup_codes_count'] ?? 10;

        $io->writeln('<info>Two-Factor Authentication (TOTP)</info> adds an extra layer of security.');
        $io->newLine();

        $enabled = $io->confirm('Enable two-factor authentication?', $currentEnabled);

        if (!$enabled) {
            $config['better_auth']['two_factor'] = ['enabled' => false];
            return $config;
        }

        $issuer = $io->ask(
            'Issuer name (shown in authenticator apps)',
            $currentIssuer
        );

        $backupCount = $io->choice(
            'Number of backup codes to generate',
            ['5', '8', '10', '12', '15'],
            (string) $currentBackupCount
        );

        $config['better_auth']['two_factor'] = [
            'enabled' => true,
            'issuer' => $issuer,
            'backup_codes_count' => (int) $backupCount,
        ];

        return $config;
    }

    private function configureMultiTenant(SymfonyStyle $io, array $config): array
    {
        $currentMultiTenant = $config['better_auth']['multi_tenant'] ?? [];
        $currentEnabled = $currentMultiTenant['enabled'] ?? false;
        $currentDefaultRole = $currentMultiTenant['default_role'] ?? 'member';

        $io->writeln('<info>Multi-Tenant</info> enables organizations/teams functionality.');
        $io->newLine();

        $enabled = $io->confirm('Enable multi-tenant (organizations/teams)?', $currentEnabled);

        if (!$enabled) {
            $config['better_auth']['multi_tenant'] = ['enabled' => false];
            return $config;
        }

        $defaultRole = $io->choice(
            'Default role for new team members',
            ['member', 'viewer', 'contributor', 'admin'],
            $currentDefaultRole
        );

        $config['better_auth']['multi_tenant'] = [
            'enabled' => true,
            'default_role' => $defaultRole,
        ];

        return $config;
    }

    private function showSummary(SymfonyStyle $io, array $config): void
    {
        $io->section('Configuration Summary');

        $auth = $config['better_auth'];

        $io->definitionList(
            ['Mode' => $auth['mode']],
            ['Access Token Lifetime' => sprintf('%d seconds (%s)', $auth['token']['lifetime'] ?? 3600, $this->formatDuration($auth['token']['lifetime'] ?? 3600))],
            ['Refresh Token Lifetime' => sprintf('%d seconds (%s)', $auth['token']['refresh_lifetime'] ?? 2592000, $this->formatDuration($auth['token']['refresh_lifetime'] ?? 2592000))]
        );

        if (isset($auth['session'])) {
            $io->writeln(sprintf('Session Lifetime: %s', $this->formatDuration($auth['session']['lifetime'])));
            $io->writeln(sprintf('Cookie Name: %s', $auth['session']['cookie_name']));
        }

        if (isset($auth['oauth']['providers']) && !empty($auth['oauth']['providers'])) {
            $enabledProviders = array_filter($auth['oauth']['providers'], fn($p) => $p['enabled'] ?? false);
            $io->writeln(sprintf('OAuth Providers: %s', implode(', ', array_keys($enabledProviders))));
        }

        $io->writeln(sprintf('2FA: %s', ($auth['two_factor']['enabled'] ?? false) ? 'Enabled' : 'Disabled'));
        $io->writeln(sprintf('Multi-Tenant: %s', ($auth['multi_tenant']['enabled'] ?? false) ? 'Enabled' : 'Disabled'));
    }

    private function formatDuration(int $seconds): string
    {
        if ($seconds < 60) {
            return sprintf('%d seconds', $seconds);
        }
        if ($seconds < 3600) {
            return sprintf('%d minutes', $seconds / 60);
        }
        if ($seconds < 86400) {
            return sprintf('%d hours', $seconds / 3600);
        }
        return sprintf('%d days', $seconds / 86400);
    }
}
