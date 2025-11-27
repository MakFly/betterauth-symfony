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

#[AsCommand(
    name: 'better-auth:install',
    description: 'Install and configure BetterAuth automatically with complete setup'
)]
class InstallCommand extends Command
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
        'MagicLinkToken' => [
            'uuid' => 'magic_link_token.uuid.php.tpl',
            'int' => null, // Only UUID version available
        ],
        'EmailVerificationToken' => [
            'uuid' => 'email_verification_token.uuid.php.tpl',
            'int' => null, // Only UUID version available
        ],
        'PasswordResetToken' => [
            'uuid' => 'password_reset_token.uuid.php.tpl',
            'int' => null, // Only UUID version available
        ],
        'TotpData' => [
            'uuid' => 'totp_data.uuid.php.tpl',
            'int' => null, // Only UUID version available
        ],
    ];

    /**
     * OAuth providers with their stability status.
     * - stable: Fully tested and production-ready
     * - draft: Implemented but not fully tested
     * - not_implemented: Listed in docs but not yet available
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

    protected function configure(): void
    {
        $this
            ->addOption('id-strategy', null, InputOption::VALUE_REQUIRED, 'ID strategy (uuid or int)', null)
            ->addOption('mode', null, InputOption::VALUE_REQUIRED, 'BetterAuth mode (api, session, or hybrid)', null)
            ->addOption('app-name', null, InputOption::VALUE_REQUIRED, 'Application name (shown in 2FA authenticator apps)', null)
            ->addOption('exclude-fields', null, InputOption::VALUE_REQUIRED, 'Comma-separated list of optional User fields to exclude (name, avatar)', null)
            ->addOption('minimal', null, InputOption::VALUE_NONE, 'Generate minimal User entity without optional fields (name, avatar)')
            ->addOption('skip-migrations', null, InputOption::VALUE_NONE, 'Skip migration generation/execution')
            ->addOption('skip-controller', null, InputOption::VALUE_NONE, 'Skip AuthController generation')
            ->addOption('skip-config', null, InputOption::VALUE_NONE, 'Skip configuration file generation')
            ->setHelp($this->getDetailedHelp())
        ;
    }

    private function getDetailedHelp(): string
    {
        return <<<'HELP'
<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>
<fg=cyan;options=bold>                    BetterAuth Installation Wizard - Complete Guide</>
<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>

The <info>better-auth:install</info> command sets up a complete authentication system for your
Symfony application with modern security features, OAuth support, and production-ready code.

<fg=yellow;options=bold>📦 WHAT IT INSTALLS</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  ✓ User Entity (with UUID v7 or auto-increment INT)
  ✓ Session Management Entity
  ✓ Refresh Token Entity
  ✓ AuthController with 8 ready-to-use endpoints
  ✓ Configuration file (better_auth.yaml)
  ✓ Database migrations
  ✓ Environment variables (.env)
  ✓ OAuth providers (Google, GitHub, Facebook) - Optional

<fg=yellow;options=bold>🎯 AUTHENTICATION MODES</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <info>API Mode</info> (Recommended for SPAs, Mobile Apps, Microservices)
    • Stateless authentication with Paseto V4 tokens
    • Access tokens (1h lifetime) + Refresh tokens (30 days)
    • Perfect for React, Vue, Angular, Flutter, React Native
    • No cookies, pure JWT-like tokens

  <info>Session Mode</info> (Traditional Web Applications)
    • Stateful authentication with secure cookies
    • Server-side session storage
    • Perfect for Twig templates, server-rendered apps

  <info>Hybrid Mode</info> (Best of Both Worlds)
    • Supports both tokens AND sessions
    • Use API for mobile, sessions for web
    • Maximum flexibility

<fg=yellow;options=bold>🔑 ID STRATEGIES</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <info>UUID v7</info> (Recommended) - Time-ordered UUIDs
    ✓ Non-guessable IDs for security
    ✓ Better database performance than UUID v4
    ✓ Chronologically sortable
    ✓ Works with distributed systems
    ✓ Example: 019ab13e-40f1-7b21-a672-f403d5277ec7

  <info>INT</info> (Classic) - Auto-increment integers
    ✓ Smaller storage (4-8 bytes vs 36 bytes)
    ✓ Human-readable (1, 2, 3...)
    ✓ Standard Symfony approach

<fg=yellow;options=bold>🚀 USAGE EXAMPLES</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <comment># Interactive installation (recommended for first-time setup)</comment>
  <info>php bin/console better-auth:install</info>

  The wizard will ask you:
    1. ID strategy (UUID v7 or INT)
    2. Authentication mode (API, Session, or Hybrid)
    3. OAuth providers to enable (Google, GitHub, Facebook)
    4. Confirm configuration and proceed

  <comment># Non-interactive installation for CI/CD or automation</comment>
  <info>php bin/console better-auth:install \
    --id-strategy=uuid \
    --mode=api \
    --app-name="My Application" \
    --no-interaction</info>

  <comment># API mode with UUID v7 (recommended for modern apps)</comment>
  <info>php bin/console better-auth:install \
    --id-strategy=uuid \
    --mode=api \
    --app-name="My SaaS" \
    --no-interaction</info>

  <comment># Session mode with auto-increment IDs (classic web apps)</comment>
  <info>php bin/console better-auth:install \
    --id-strategy=int \
    --mode=session \
    --no-interaction</info>

  <comment># Skip migrations if you want to run them manually later</comment>
  <info>php bin/console better-auth:install \
    --id-strategy=uuid \
    --mode=api \
    --skip-migrations</info>

  <comment># Minimal User entity (without name and avatar fields)</comment>
  <info>php bin/console better-auth:install \
    --id-strategy=uuid \
    --mode=api \
    --minimal</info>

  <comment># Exclude only specific fields</comment>
  <info>php bin/console better-auth:install \
    --id-strategy=uuid \
    --mode=api \
    --exclude-fields=avatar</info>

<fg=yellow;options=bold>📝 OPTIONS</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <info>--id-strategy=uuid|int</info>
    Choose between UUID v7 (time-ordered, secure) or INT (auto-increment).
    Default: Interactive prompt

  <info>--mode=api|session|hybrid</info>
    Choose authentication mode:
      • api: Stateless tokens (Paseto V4)
      • session: Stateful cookies
      • hybrid: Both tokens and sessions
    Default: Interactive prompt

  <info>--app-name="Your App Name"</info>
    Application name shown in 2FA authenticator apps (Google Authenticator, Authy).
    This helps users identify your app when scanning QR codes.
    Default: Interactive prompt (or "My App" in non-interactive mode)

  <info>--skip-migrations</info>
    Skip automatic migration generation and execution.
    Use this if you want to review migrations before running them.

  <info>--skip-controller</info>
    Skip AuthController generation.
    Useful if you want to create a custom controller.

  <info>--skip-config</info>
    Skip configuration file generation.
    Only use if you have a custom config setup.

  <info>--exclude-fields=name,avatar</info>
    Comma-separated list of optional User fields to exclude.
    Available fields: name, avatar
    Example: --exclude-fields=avatar (keeps name, excludes avatar)

  <info>--minimal</info>
    Generate minimal User entity without optional fields (name, avatar).
    Equivalent to --exclude-fields=name,avatar

  <info>--no-interaction</info>
    Run without prompts (requires --id-strategy and --mode).

<fg=yellow;options=bold>🌐 OAUTH PROVIDERS</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

During interactive installation, you can enable OAuth providers:
  • Google OAuth
  • GitHub OAuth
  • Facebook OAuth

The wizard will:
  1. Generate OAuth configuration in better_auth.yaml
  2. Add CLIENT_ID and CLIENT_SECRET to .env
  3. Display instructions for obtaining OAuth credentials

<fg=yellow;options=bold>📁 GENERATED FILES</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <info>src/Entity/User.php</info>
    Base user entity with email, password, name, avatar, etc.
    Extends BetterAuth\Symfony\Model\User (for UUID) or standalone (for INT)

  <info>src/Entity/Session.php</info>
    Manages user sessions with device info, IP, user-agent

  <info>src/Entity/RefreshToken.php</info>
    Stores refresh tokens for token rotation

  <info>src/Controller/AuthController.php</info>
    8 ready-to-use endpoints:
      POST   /auth/register          - Create new user
      POST   /auth/login             - Authenticate user
      GET    /auth/me                - Get current user
      POST   /auth/refresh           - Refresh access token
      POST   /auth/logout            - Logout user
      POST   /auth/revoke-all        - Logout from all devices
      GET    /auth/oauth/{provider}  - OAuth redirect
      GET    /auth/oauth/{provider}/callback - OAuth callback

  <info>config/packages/better_auth.yaml</info>
    Complete configuration with:
      • Mode (api/session/hybrid)
      • Token lifetimes
      • Session settings
      • OAuth providers
      • Multi-tenant settings

  <info>.env</info>
    Adds:
      BETTER_AUTH_SECRET=<auto-generated>
      APP_URL=http://localhost:8000
      GOOGLE_CLIENT_ID= (if OAuth enabled)
      GOOGLE_CLIENT_SECRET= (if OAuth enabled)

  <info>migrations/*.php</info>
    Doctrine migrations for all database tables

<fg=yellow;options=bold>🧪 TESTING THE INSTALLATION</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <comment># Start development server</comment>
  <info>php -S localhost:8000 -t public</info>

  <comment># Test registration</comment>
  <info>curl -X POST http://localhost:8000/auth/register \
    -H "Content-Type: application/json" \
    -d '{"email":"user@example.com","password":"SecurePassword123","name":"John"}'</info>

  Response (API mode):
  {
    "user": {
      "id": "019ab13e-40f1-7b21-a672-f403d5277ec7",
      "email": "user@example.com",
      "name": "John",
      "emailVerified": false
    }
  }

  <comment># Test login</comment>
  <info>curl -X POST http://localhost:8000/auth/login \
    -H "Content-Type: application/json" \
    -d '{"email":"user@example.com","password":"SecurePassword123"}'</info>

  Response (API mode):
  {
    "user": {...},
    "access_token": "v4.local.xxxxx",
    "refresh_token": "xxxxx",
    "token_type": "Bearer",
    "expires_in": 3600
  }

  <comment># Test authenticated request</comment>
  <info>curl -X GET http://localhost:8000/auth/me \
    -H "Authorization: Bearer <access_token>"</info>

<fg=yellow;options=bold>🔒 SECURITY FEATURES</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  ✓ Paseto V4 tokens (encrypted + authenticated, better than JWT)
  ✓ Argon2id password hashing (memory-hard, resistant to GPU attacks)
  ✓ Refresh token rotation (prevents token theft)
  ✓ Device fingerprinting (IP + User-Agent tracking)
  ✓ Multi-device session management
  ✓ Rate limiting support
  ✓ OAuth 2.0 with PKCE

<fg=yellow;options=bold>⚙️  NEXT STEPS</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  1. Review generated configuration in <info>config/packages/better_auth.yaml</info>

  2. If OAuth enabled, fill in credentials in <info>.env</info>:
     <comment>GOOGLE_CLIENT_ID=your_client_id_here</comment>
     <comment>GOOGLE_CLIENT_SECRET=your_secret_here</comment>

  3. Customize User entity if needed (<info>src/Entity/User.php</info>)

  4. Run migrations if skipped:
     <info>php bin/console doctrine:migrations:migrate</info>

  5. Test endpoints (see examples above)

  6. Check API documentation at <info>http://localhost:8000/api/docs</info>

<fg=yellow;options=bold>📚 DOCUMENTATION</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  Complete documentation: <info>better-auth-php/docs/</info>
    • installation.mdx - Full installation guide
    • configuration.mdx - Configuration options
    • api-reference.mdx - API endpoints documentation
    • uuid-vs-int.mdx - Choosing ID strategy

<fg=yellow;options=bold>💡 TROUBLESHOOTING</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <comment>Q: Migrations fail?</comment>
  A: Make sure database exists:
     <info>php bin/console doctrine:database:create</info>

  <comment>Q: OAuth not working?</comment>
  A: Check credentials in .env and redirect URIs in provider settings

  <comment>Q: Tokens not working?</comment>
  A: Verify BETTER_AUTH_SECRET is set in .env

  <comment>Q: Want to change UUID to INT?</comment>
  A: Delete entities and re-run:
     <info>rm -rf src/Entity/*.php migrations/*.php</info>
     <info>php bin/console better-auth:install --id-strategy=int</info>

<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>
<fg=cyan;options=bold>                           Ready to install BetterAuth? 🚀</>
<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>

HELP;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();
        $projectDir = $this->getProjectDir();

        $io->title('🔐 BetterAuth Installation Wizard');

        // Detect current state
        $state = $this->detectCurrentState($projectDir, $filesystem);
        $this->displayCurrentState($io, $state);

        // Step 1: Choose ID strategy
        $idStrategy = $this->chooseIdStrategy($input, $io, $state);

        // Step 2: Choose mode
        $mode = $this->chooseMode($input, $io, $state);

        // Step 3: Choose OAuth providers
        $providers = $this->chooseOAuthProviders($io);

        // Step 4: Choose app name
        $appName = $this->chooseAppName($input, $io);

        // Step 5: Choose fields to exclude
        $excludedFields = $this->chooseExcludedFields($input, $io);

        // Display configuration summary
        $io->section('📋 Configuration Summary');
        $io->writeln([
            sprintf('  • ID Strategy: <info>%s</info>', strtoupper($idStrategy)),
            sprintf('  • Mode: <info>%s</info>', $mode),
            sprintf('  • App Name: <info>%s</info>', $appName),
            sprintf('  • OAuth Providers: <info>%s</info>', empty($providers) ? 'None' : implode(', ', $providers)),
            sprintf('  • Excluded Fields: <info>%s</info>', empty($excludedFields) ? 'None (full profile)' : implode(', ', $excludedFields)),
        ]);
        $io->newLine();

        if (!$io->confirm('Proceed with installation?', true)) {
            $io->warning('Installation cancelled.');

            return Command::SUCCESS;
        }

        // Execute installation steps
        $generatedEntities = $this->generateEntities($io, $filesystem, $projectDir, $idStrategy, $state, $excludedFields);
        $this->registerBundle($io, $filesystem, $projectDir);
        $this->generateConfiguration($io, $filesystem, $projectDir, $mode, $providers, $state);
        $this->generateController($io, $filesystem, $projectDir, $state, $input);
        $this->configureServices($io, $filesystem, $projectDir, $idStrategy);
        $this->updateEnvFile($io, $filesystem, $projectDir, $providers, $appName);

        // Migrations
        if (!$input->getOption('skip-migrations')) {
            $this->handleMigrations($io, $projectDir);
        }

        // Final summary
        $this->displayFinalSummary($io, $idStrategy, $generatedEntities, $mode, $providers, $appName);

        return Command::SUCCESS;
    }

    private function detectCurrentState(string $projectDir, Filesystem $filesystem): array
    {
        return [
            'entities' => [
                'User' => $filesystem->exists($projectDir . '/src/Entity/User.php'),
                'Session' => $filesystem->exists($projectDir . '/src/Entity/Session.php'),
                'RefreshToken' => $filesystem->exists($projectDir . '/src/Entity/RefreshToken.php'),
            ],
            'controller' => $filesystem->exists($projectDir . '/src/Controller/AuthController.php'),
            'config' => $filesystem->exists($projectDir . '/config/packages/better_auth.yaml'),
            'bundle_registered' => $this->isBundleRegistered($projectDir, $filesystem),
            'env_has_secret' => $this->envHasSecret($projectDir),
            'migrations_dir' => $filesystem->exists($projectDir . '/migrations'),
        ];
    }

    private function displayCurrentState(SymfonyStyle $io, array $state): void
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

    private function chooseIdStrategy(InputInterface $input, SymfonyStyle $io, array $state): string
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

    private function chooseMode(InputInterface $input, SymfonyStyle $io, array $state): string
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

    private function chooseOAuthProviders(SymfonyStyle $io): array
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

        $availableProviders = [];
        foreach (self::OAUTH_PROVIDERS as $key => $config) {
            $availableProviders[$key] = $config['name'];
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

    private function chooseAppName(InputInterface $input, SymfonyStyle $io): string
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
     * Choose which optional User fields to exclude.
     *
     * @return string[] List of field names to exclude
     */
    private function chooseExcludedFields(InputInterface $input, SymfonyStyle $io): array
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

            return $validFields;
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

    private function generateEntities(SymfonyStyle $io, Filesystem $filesystem, string $projectDir, string $idStrategy, array $state, array $excludedFields = []): array
    {
        $io->section('📦 Step 1/5: Generating Entities');

        $entitiesDir = $projectDir . '/src/Entity';
        if (!$filesystem->exists($entitiesDir)) {
            $filesystem->mkdir($entitiesDir);
        }

        $templatesDir = dirname(__DIR__) . '/Resources/templates/entities';
        $generatedFiles = [];

        foreach (self::ENTITY_TEMPLATES as $entityName => $templates) {
            // Skip if template not available for this ID strategy
            if ($templates[$idStrategy] === null) {
                continue;
            }

            $templateFile = $templatesDir . '/' . $templates[$idStrategy];
            $targetFile = $entitiesDir . '/' . $entityName . '.php';

            if ($filesystem->exists($targetFile)) {
                if (!$io->confirm(sprintf('  Entity %s already exists. Overwrite?', $entityName), false)) {
                    $io->writeln(sprintf('  <fg=yellow>⊘</> Skipped %s', $entityName));
                    continue;
                }
            }

            $content = file_get_contents($templateFile);

            // Process User entity template with field exclusions
            if ($entityName === 'User') {
                $content = $this->processUserTemplate($content, $excludedFields);
            }

            $filesystem->dumpFile($targetFile, $content);
            $generatedFiles[] = $entityName;

            // Show additional info for User entity
            if ($entityName === 'User' && !empty($excludedFields)) {
                $io->writeln(sprintf('  <fg=green>✓</> Generated %s.php <fg=gray>(excluded: %s)</>', $entityName, implode(', ', $excludedFields)));
            } else {
                $io->writeln(sprintf('  <fg=green>✓</> Generated %s.php', $entityName));
            }
        }

        return $generatedFiles;
    }

    /**
     * Process User template to handle field exclusions.
     */
    private function processUserTemplate(string $content, array $excludedFields): string
    {
        // Check if we should include the profile trait (all optional fields)
        $allOptionalFieldsExcluded = count(array_intersect($excludedFields, self::OPTIONAL_USER_FIELDS)) === count(self::OPTIONAL_USER_FIELDS);
        $someFieldsExcluded = !empty($excludedFields) && !$allOptionalFieldsExcluded;

        if ($allOptionalFieldsExcluded) {
            // Minimal mode: no trait, no optional fields
            $content = str_replace("{{USE_PROFILE_TRAIT}}\n", '', $content);
            $content = str_replace("{{PROFILE_TRAIT}}\n", '', $content);
            $content = str_replace("{{CUSTOM_FIELDS}}\n", '', $content);
        } elseif ($someFieldsExcluded) {
            // Partial mode: generate individual fields instead of trait
            $content = str_replace("{{USE_PROFILE_TRAIT}}\n", "use Doctrine\\DBAL\\Types\\Types;\n", $content);
            $content = str_replace("{{PROFILE_TRAIT}}\n", '', $content);

            // Generate only the fields that are NOT excluded
            $customFields = $this->generateCustomFields($excludedFields);
            $content = str_replace("{{CUSTOM_FIELDS}}\n", $customFields, $content);
        } else {
            // Full mode: use the profile trait
            $content = str_replace("{{USE_PROFILE_TRAIT}}\n", "use BetterAuth\\Symfony\\Model\\UserProfileTrait;\n", $content);
            $content = str_replace("{{PROFILE_TRAIT}}", "    use UserProfileTrait;\n", $content);
            $content = str_replace("{{CUSTOM_FIELDS}}\n", '', $content);
        }

        return $content;
    }

    /**
     * Generate custom field definitions for partial field inclusion.
     */
    private function generateCustomFields(array $excludedFields): string
    {
        $fields = [];

        if (!in_array('name', $excludedFields, true)) {
            $fields[] = <<<'PHP'

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $name = null;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }
PHP;
        }

        if (!in_array('avatar', $excludedFields, true)) {
            $fields[] = <<<'PHP'

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    protected ?string $avatar = null;

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }
PHP;
        }

        return empty($fields) ? '' : implode("\n", $fields) . "\n";
    }

    private function registerBundle(SymfonyStyle $io, Filesystem $filesystem, string $projectDir): void
    {
        $io->section('🔌 Step 2/5: Registering Bundle');

        $bundlesFile = $projectDir . '/config/bundles.php';
        if (!$filesystem->exists($bundlesFile)) {
            $io->writeln('  <fg=yellow>⊘</> bundles.php not found, skipping...');

            return;
        }

        $bundlesContent = file_get_contents($bundlesFile);
        if (str_contains($bundlesContent, 'BetterAuthBundle')) {
            $io->writeln('  <fg=green>✓</> Bundle already registered');

            return;
        }

        $bundlesContent = str_replace(
            '];',
            "    BetterAuth\\Symfony\\BetterAuthBundle::class => ['all' => true],\n];",
            $bundlesContent
        );
        $filesystem->dumpFile($bundlesFile, $bundlesContent);
        $io->writeln('  <fg=green>✓</> Bundle registered in bundles.php');
    }

    private function generateConfiguration(SymfonyStyle $io, Filesystem $filesystem, string $projectDir, string $mode, array $providers, array $state): void
    {
        $io->section('⚙️  Step 3/6: Generating Configuration');

        $configDir = $projectDir . '/config/packages';
        $configFile = $configDir . '/better_auth.yaml';

        if ($filesystem->exists($configFile)) {
            if (!$io->confirm('  Configuration file already exists. Overwrite?', false)) {
                $io->writeln('  <fg=yellow>⊘</> Skipped configuration');

                return;
            }
        }

        $templateFile = dirname(__DIR__) . '/Resources/templates/config/better_auth.yaml.tpl';
        $content = file_get_contents($templateFile);

        // Replace placeholders
        $content = str_replace('{{MODE}}', $mode, $content);

        // Generate OAuth providers config
        $oauthConfig = $this->generateOAuthConfig($providers);
        $content = str_replace('{{OAUTH_PROVIDERS}}', $oauthConfig, $content);

        if (!$filesystem->exists($configDir)) {
            $filesystem->mkdir($configDir);
        }

        $filesystem->dumpFile($configFile, $content);
        $io->writeln('  <fg=green>✓</> Created config/packages/better_auth.yaml');
    }

    private function generateController(SymfonyStyle $io, Filesystem $filesystem, string $projectDir, array $state, InputInterface $input): void
    {
        if ($input->getOption('skip-controller')) {
            $io->section('🎮 Step 4/6: Controllers (Skipped)');

            return;
        }

        $io->section('🎮 Step 4/6: Generating Controllers');

        $templatesDir = dirname(__DIR__) . '/Resources/templates/controller';
        $controllerDir = $projectDir . '/src/Controller/Api';
        $traitDir = $controllerDir . '/Trait';

        // Create directories
        if (!$filesystem->exists($controllerDir)) {
            $filesystem->mkdir($controllerDir);
        }
        if (!$filesystem->exists($traitDir)) {
            $filesystem->mkdir($traitDir);
        }

        // Core controllers (always installed)
        $coreControllers = [
            'Trait/ApiResponseTrait' => 'ApiResponseTrait.php.tpl',
            'AuthController' => 'AuthController.php.tpl',
            'PasswordController' => 'PasswordController.php.tpl',
            'SessionsController' => 'SessionsController.php.tpl',
        ];

        // Optional controllers (ask user)
        $optionalControllers = [
            'oauth' => ['OAuthController' => 'OAuthController.php.tpl'],
            'email-verification' => ['EmailVerificationController' => 'EmailVerificationController.php.tpl'],
            'magic-link' => ['MagicLinkController' => 'MagicLinkController.php.tpl'],
            'guest' => ['GuestSessionController' => 'GuestSessionController.php.tpl'],
            'account-link' => ['AccountLinkController' => 'AccountLinkController.php.tpl'],
            'devices' => ['DeviceController' => 'DeviceController.php.tpl'],
        ];

        // Ask for optional controllers
        $io->writeln('');
        $io->writeln('  <fg=cyan>Optional Controllers:</fg>');
        $io->writeln('  You can add more controllers later with: <info>better-auth:add-controller</info>');
        $io->writeln('');

        $selectedOptional = [];
        if ($io->confirm('Do you want to install additional controllers?', false)) {
            foreach ($optionalControllers as $key => $config) {
                $name = array_key_first($config);
                $descriptions = [
                    'oauth' => 'OAuth (Google, GitHub, Facebook, etc.)',
                    'email-verification' => 'Email verification flow',
                    'magic-link' => 'Passwordless authentication',
                    'guest' => 'Guest/anonymous sessions',
                    'account-link' => 'Link third-party accounts',
                    'devices' => 'Device management & tracking',
                ];
                if ($io->confirm(sprintf('  Add %s?', $descriptions[$key]), false)) {
                    $selectedOptional = array_merge($selectedOptional, $config);
                }
            }
        }

        $controllers = array_merge($coreControllers, $selectedOptional);

        $generatedCount = 0;
        foreach ($controllers as $name => $template) {
            $targetFile = $controllerDir . '/' . $name . '.php';
            $templateFile = $templatesDir . '/' . $template;

            if (!$filesystem->exists($templateFile)) {
                $io->writeln(sprintf('  <fg=yellow>⊘</> Template not found: %s', $template));
                continue;
            }

            if ($filesystem->exists($targetFile)) {
                if (!$io->confirm(sprintf('  %s already exists. Overwrite?', $name), false)) {
                    $io->writeln(sprintf('  <fg=yellow>⊘</> Skipped %s', $name));
                    continue;
                }
            }

            $content = file_get_contents($templateFile);
            $filesystem->dumpFile($targetFile, $content);
            $io->writeln(sprintf('  <fg=green>✓</> Generated %s.php', $name));
            $generatedCount++;
        }

        if ($generatedCount > 0) {
            $io->writeln('  <fg=green>✓</> Routes automatically configured via attributes');
            $io->writeln('');
            $io->writeln('  <fg=cyan>Generated structure:</>');
            $io->writeln('    src/Controller/Api/');
            $io->writeln('    ├── Trait/ApiResponseTrait.php');
            $io->writeln('    ├── AuthController.php');
            $io->writeln('    ├── PasswordController.php');
            $io->writeln('    └── SessionsController.php');
        }
    }

    private function configureServices(SymfonyStyle $io, Filesystem $filesystem, string $projectDir, string $idStrategy): void
    {
        $io->section('⚙️  Step 5/6: Configuring Services');

        $servicesFile = $projectDir . '/config/services.yaml';
        if (!$filesystem->exists($servicesFile)) {
            $io->writeln('  <fg=yellow>⊘</> services.yaml not found, skipping...');
            return;
        }

        $servicesContent = file_get_contents($servicesFile);

        // Check if BetterAuth services are already configured
        if (str_contains($servicesContent, 'DoctrineUserRepository')) {
            $io->writeln('  <fg=green>✓</> BetterAuth repositories already configured');
            return;
        }

        // Only configure for UUID strategy (extends base entities)
        if ($idStrategy === 'uuid') {
            $repositoryConfig = <<<'YAML'

    # BetterAuth - Configure repositories to use App entities
    BetterAuth\Symfony\Storage\Doctrine\DoctrineUserRepository:
        arguments:
            $userClass: 'App\Entity\User'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineSessionRepository:
        arguments:
            $sessionClass: 'App\Entity\Session'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineRefreshTokenRepository:
        arguments:
            $refreshTokenClass: 'App\Entity\RefreshToken'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineMagicLinkRepository:
        arguments:
            $tokenClass: 'App\Entity\MagicLinkToken'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineEmailVerificationRepository:
        arguments:
            $tokenClass: 'App\Entity\EmailVerificationToken'

    BetterAuth\Symfony\Storage\Doctrine\DoctrinePasswordResetRepository:
        arguments:
            $tokenClass: 'App\Entity\PasswordResetToken'

    BetterAuth\Symfony\Storage\Doctrine\DoctrineTotpRepository:
        arguments:
            $totpClass: 'App\Entity\TotpData'

YAML;

            $servicesContent .= $repositoryConfig;
            $filesystem->dumpFile($servicesFile, $servicesContent);
            $io->writeln('  <fg=green>✓</> Configured repository services in services.yaml');
        } else {
            $io->writeln('  <fg=yellow>⊘</> INT strategy: services configuration not needed');
        }
    }

    private function updateEnvFile(SymfonyStyle $io, Filesystem $filesystem, string $projectDir, array $providers = [], string $appName = 'My App'): void
    {
        $io->section('🔑 Step 6/6: Environment Configuration');

        $envFile = $projectDir . '/.env';
        if (!$filesystem->exists($envFile)) {
            $io->writeln('  <fg=yellow>⊘</> .env file not found, skipping...');

            return;
        }

        $envContent = file_get_contents($envFile);
        $modified = false;

        // Check and add BETTER_AUTH_SECRET if not present
        if (str_contains($envContent, 'BETTER_AUTH_SECRET=')) {
            $io->writeln('  <fg=green>✓</> BETTER_AUTH_SECRET already exists in .env');
        } else {
            // Generate a secure random secret
            $secret = bin2hex(random_bytes(32));

            $envContent .= "\n# BetterAuth Configuration\n";
            $envContent .= "BETTER_AUTH_SECRET=$secret\n";
            $envContent .= "APP_URL=http://localhost:8000\n";

            $io->writeln('  <fg=green>✓</> Added BETTER_AUTH_SECRET to .env');
            $io->writeln('  <fg=green>✓</> Added APP_URL to .env');
            $modified = true;
        }

        // Check and add APP_NAME if not present
        if (str_contains($envContent, 'APP_NAME=')) {
            $io->writeln('  <fg=green>✓</> APP_NAME already exists in .env');
        } else {
            // Escape quotes in app name for .env file
            $escapedAppName = str_contains($appName, ' ') ? "\"$appName\"" : $appName;
            $envContent .= "APP_NAME=$escapedAppName\n";

            $io->writeln(sprintf('  <fg=green>✓</> Added APP_NAME="%s" to .env', $appName));
            $modified = true;
        }

        // Check and add OAuth provider variables if any
        if (!empty($providers)) {
            $oauthAdded = false;

            foreach ($providers as $provider) {
                $upperProvider = strtoupper($provider);
                $clientIdVar = "{$upperProvider}_CLIENT_ID";
                $clientSecretVar = "{$upperProvider}_CLIENT_SECRET";

                // Check if OAuth variables already exist
                if (str_contains($envContent, "{$clientIdVar}=")) {
                    $io->writeln(sprintf('  <fg=green>✓</> %s already exists in .env', $clientIdVar));
                } else {
                    if (!$oauthAdded) {
                        $envContent .= "\n# OAuth Configuration\n";
                        $oauthAdded = true;
                    }
                    $envContent .= "{$clientIdVar}=\n";
                    $envContent .= "{$clientSecretVar}=\n";

                    $io->writeln(sprintf('  <fg=green>✓</> Added %s and %s to .env', $clientIdVar, $clientSecretVar));
                    $modified = true;
                }
            }
        }

        // Write file only if modified
        if ($modified) {
            $filesystem->dumpFile($envFile, $envContent);
        }
    }

    private function handleMigrations(SymfonyStyle $io, string $projectDir): void
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

    private function displayFinalSummary(SymfonyStyle $io, string $idStrategy, array $generatedEntities, string $mode, array $providers, string $appName): void
    {
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
            '  • Controller: <info>src/Controller/AuthController.php</info>',
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

    private function generateOAuthConfig(array $providers): string
    {
        if (empty($providers)) {
            return '            # No OAuth providers enabled';
        }

        $config = [];
        foreach ($providers as $provider) {
            $upperProvider = strtoupper($provider);
            $config[] = sprintf("            %s:\n", $provider);
            $config[] = sprintf("                enabled: true\n");
            $config[] = sprintf("                client_id: '%%env(%s_CLIENT_ID)%%'\n", $upperProvider);
            $config[] = sprintf("                client_secret: '%%env(%s_CLIENT_SECRET)%%'\n", $upperProvider);
            $config[] = sprintf("                redirect_uri: '%%env(APP_URL)%%/auth/oauth/%s/callback'\n", $provider);
        }

        return rtrim(implode('', $config));
    }

    private function isBundleRegistered(string $projectDir, Filesystem $filesystem): bool
    {
        $bundlesFile = $projectDir . '/config/bundles.php';
        if (!$filesystem->exists($bundlesFile)) {
            return false;
        }

        $content = file_get_contents($bundlesFile);

        return str_contains($content, 'BetterAuthBundle');
    }

    private function envHasSecret(string $projectDir): bool
    {
        $envFile = $projectDir . '/.env';
        if (!file_exists($envFile)) {
            return false;
        }

        $content = file_get_contents($envFile);

        return str_contains($content, 'BETTER_AUTH_SECRET=');
    }

    private function formatStatus(array $items): string
    {
        if (empty($items)) {
            return '<fg=red>None</>';
        }

        return '<fg=green>' . implode(', ', array_keys($items)) . '</>';
    }

    private function formatBool(bool $value): string
    {
        return $value ? '<fg=green>✓ Yes</>' : '<fg=red>✗ No</>';
    }

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
