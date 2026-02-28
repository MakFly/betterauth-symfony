<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Command;

use BetterAuth\Symfony\Installer\ConfigGenerator;
use BetterAuth\Symfony\Installer\ControllerGenerator;
use BetterAuth\Symfony\Installer\EntityGenerator;
use BetterAuth\Symfony\Installer\InputCollector;
use BetterAuth\Symfony\Installer\MigrationHandler;
use BetterAuth\Symfony\Installer\StateDetector;
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
    private readonly StateDetector $stateDetector;
    private readonly InputCollector $inputCollector;
    private readonly EntityGenerator $entityGenerator;
    private readonly ControllerGenerator $controllerGenerator;
    private readonly ConfigGenerator $configGenerator;
    private readonly MigrationHandler $migrationHandler;

    public function __construct()
    {
        parent::__construct();

        $filesystem = new Filesystem();
        $this->stateDetector = new StateDetector($filesystem);
        $this->inputCollector = new InputCollector();
        $this->entityGenerator = new EntityGenerator($filesystem);
        $this->controllerGenerator = new ControllerGenerator($filesystem);
        $this->configGenerator = new ConfigGenerator($filesystem);
        $this->migrationHandler = new MigrationHandler();
    }

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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectDir = $this->getProjectDir();

        $io->title('🔐 BetterAuth Installation Wizard');

        // Detect current state
        $state = $this->stateDetector->detectCurrentState($projectDir);
        $this->stateDetector->displayCurrentState($io, $state);

        // Step 1: Collect installation preferences
        $idStrategy = $this->inputCollector->chooseIdStrategy($input, $io, $state);
        $mode = $this->inputCollector->chooseMode($input, $io, $state);
        $providers = $this->inputCollector->chooseOAuthProviders($io);
        $appName = $this->inputCollector->chooseAppName($input, $io);
        $excludedFields = $this->inputCollector->chooseExcludedFields($input, $io);

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
        $generatedEntities = $this->entityGenerator->generateEntities($io, $projectDir, $idStrategy, $state, $excludedFields);
        $this->configGenerator->registerBundle($io, $projectDir);
        $this->configGenerator->generateConfiguration($io, $projectDir, $mode, $providers, $state);
        $this->controllerGenerator->generateController($io, $projectDir, $state, $input);
        $this->configGenerator->configureServices($io, $projectDir, $idStrategy);
        $this->configGenerator->updateEnvFile($io, $projectDir, $providers, $appName);

        // Migrations
        if (!$input->getOption('skip-migrations')) {
            $this->migrationHandler->handleMigrations($io, $projectDir);
        }

        // Final summary
        $this->migrationHandler->displayFinalSummary($io, $idStrategy, $generatedEntities, $mode, $providers, $appName);

        return Command::SUCCESS;
    }

    /**
     * Resolve the Symfony project root directory by searching for composer.json.
     */
    private function getProjectDir(): string
    {
        $dir = (string) getcwd();

        while ($dir !== dirname($dir)) {
            if (file_exists($dir . '/composer.json')) {
                return $dir;
            }
            $dir = dirname($dir);
        }

        return (string) getcwd();
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
}
