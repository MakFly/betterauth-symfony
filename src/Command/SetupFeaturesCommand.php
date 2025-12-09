<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'better-auth:setup-features',
    description: 'Enable/disable features with auto entity & migration generation (e.g. --enable=magic_link --migrate)'
)]
class SetupFeaturesCommand extends Command
{
    private const FEATURES = [
        'email_password' => [
            'name' => 'Email/Password Authentication',
            'description' => 'Classic email and password login',
            'default' => true,
            'required' => true,
            'entities' => [],
            'controllers' => ['auth'],
        ],
        'oauth' => [
            'name' => 'OAuth 2.0 Providers',
            'description' => 'Login with Google, GitHub, Facebook, etc.',
            'default' => false,
            'required' => false,
            'providers' => ['google', 'github', 'facebook', 'microsoft', 'discord', 'apple', 'twitter'],
            'entities' => [],
            'controllers' => ['oauth', 'account-link'],
        ],
        'two_factor' => [
            'name' => 'Two-Factor Authentication (2FA)',
            'description' => 'TOTP codes with Google Authenticator',
            'default' => false,
            'required' => false,
            'entities' => ['TotpData'],
            'controllers' => ['auth'], // 2FA endpoints in auth controller
        ],
        'magic_link' => [
            'name' => 'Magic Link (Passwordless)',
            'description' => 'Login via email link without password',
            'default' => false,
            'required' => false,
            'entities' => ['MagicLinkToken'],
            'controllers' => ['magic-link'],
        ],
        'email_verification' => [
            'name' => 'Email Verification',
            'description' => 'Verify user email addresses',
            'default' => true,
            'required' => false,
            'entities' => ['EmailVerificationToken'],
            'controllers' => ['email-verification'],
        ],
        'password_reset' => [
            'name' => 'Password Reset',
            'description' => 'Forgot password functionality',
            'default' => true,
            'required' => false,
            'entities' => ['PasswordResetToken'],
            'controllers' => ['password'],
        ],
        'session_management' => [
            'name' => 'Session Management',
            'description' => 'View and revoke active sessions',
            'default' => true,
            'required' => false,
            'entities' => [],
            'controllers' => ['sessions'],
        ],
        'device_tracking' => [
            'name' => 'Device Tracking',
            'description' => 'Track user devices and locations',
            'default' => false,
            'required' => false,
            'entities' => ['Device'],
            'controllers' => ['devices'],
        ],
        'security_monitoring' => [
            'name' => 'Security Monitoring',
            'description' => 'Detect suspicious activities and threats',
            'default' => false,
            'required' => false,
            'entities' => ['SecurityEvent'],
            'controllers' => [],
        ],
        'guest_sessions' => [
            'name' => 'Guest Sessions',
            'description' => 'Anonymous sessions that can be converted to users',
            'default' => false,
            'required' => false,
            'entities' => ['GuestSession'],
            'controllers' => ['guest'],
        ],
        'multi_tenant' => [
            'name' => 'Multi-Tenant (Organizations)',
            'description' => 'Organizations, teams, and member management',
            'default' => false,
            'required' => false,
            'entities' => ['Organization', 'OrganizationMember'],
            'controllers' => ['organizations'],
        ],
    ];

    /**
     * Entity templates mapping: EntityName => template file
     */
    private const ENTITY_TEMPLATES = [
        'MagicLinkToken' => 'magic_link_token.uuid.php.tpl',
        'EmailVerificationToken' => 'email_verification_token.uuid.php.tpl',
        'PasswordResetToken' => 'password_reset_token.uuid.php.tpl',
        'TotpData' => 'totp_data.uuid.php.tpl',
        'Device' => 'device.uuid.php.tpl',
        'SecurityEvent' => 'security_event.uuid.php.tpl',
        'GuestSession' => 'guest_session.uuid.php.tpl',
        'Organization' => 'organization.uuid.php.tpl',
        'OrganizationMember' => 'organization_member.uuid.php.tpl',
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
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List all available features and their status')
            ->addOption('enable', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Enable specific features')
            ->addOption('disable', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Disable specific features')
            ->addOption('preset', 'p', InputOption::VALUE_REQUIRED, 'Use a preset (minimal, standard, full)')
            ->addOption('with-controllers', null, InputOption::VALUE_NONE, 'Also generate required controllers')
            ->addOption('with-migrations', null, InputOption::VALUE_NONE, 'Automatically run doctrine:migrations:diff')
            ->addOption('migrate', null, InputOption::VALUE_NONE, 'Also run doctrine:migrations:migrate (implies --with-migrations)')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing entities without asking')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be done without making changes')
            ->setHelp($this->getDetailedHelp());
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $io->title('🔧 BetterAuth Feature Setup');

        // List mode
        if ($input->getOption('list')) {
            return $this->listFeatures($io);
        }

        // Preset mode
        $preset = $input->getOption('preset');
        if ($preset) {
            return $this->applyPreset($io, $input, $preset);
        }

        // Enable/Disable mode
        $enableFeatures = $input->getOption('enable');
        $disableFeatures = $input->getOption('disable');
        if (!empty($enableFeatures) || !empty($disableFeatures)) {
            return $this->toggleFeatures($io, $input, $enableFeatures, $disableFeatures);
        }

        // Interactive mode
        return $this->interactiveSetup($io, $input);
    }

    private function listFeatures(SymfonyStyle $io): int
    {
        $io->section('📋 Available Features');

        $currentConfig = $this->loadCurrentConfig();
        $rows = [];

        foreach (self::FEATURES as $key => $feature) {
            $isEnabled = $this->isFeatureEnabled($key, $currentConfig);
            $entities = $feature['entities'];
            $missingEntities = $this->getMissingEntities($entities);

            $statusIcon = $feature['required'] ? '🔒' : ($isEnabled ? '✅' : '⬚');
            $entityStatus = empty($entities) ? '-' : (empty($missingEntities) ? '✅' : '⚠️ ' . implode(', ', $missingEntities));

            $rows[] = [
                $statusIcon . ' ' . $key,
                $feature['name'],
                $entityStatus,
                implode(', ', $feature['controllers']) ?: '-',
            ];
        }

        $io->table(['Feature', 'Description', 'Entities', 'Controllers'], $rows);

        $io->section('📦 Presets');
        $io->listing([
            '<info>minimal</info>  - Email/Password only',
            '<info>standard</info> - + Email Verification + Password Reset + Sessions',
            '<info>full</info>     - All features enabled',
        ]);

        $io->section('🚀 Quick Commands');
        $io->text([
            '<comment># Enable magic link with auto-generation</comment>',
            '<info>php bin/console better-auth:setup-features --enable=magic_link --with-controllers --with-migrations</info>',
            '',
            '<comment># Enable multiple features at once</comment>',
            '<info>php bin/console better-auth:setup-features --enable=magic_link --enable=two_factor --migrate</info>',
            '',
            '<comment># Apply full preset with everything</comment>',
            '<info>php bin/console better-auth:setup-features --preset=full --with-controllers --migrate</info>',
        ]);

        return Command::SUCCESS;
    }

    private function interactiveSetup(SymfonyStyle $io, InputInterface $input): int
    {
        $io->section('🎮 Interactive Feature Selection');

        $currentConfig = $this->loadCurrentConfig();
        $selectedFeatures = [];

        // Group features by category
        $categories = [
            'Authentication' => ['email_password', 'oauth', 'magic_link'],
            'Security' => ['two_factor', 'security_monitoring', 'device_tracking'],
            'User Management' => ['email_verification', 'password_reset', 'session_management'],
            'Advanced' => ['guest_sessions', 'multi_tenant'],
        ];

        foreach ($categories as $category => $featureKeys) {
            $io->text("\n<fg=yellow;options=bold>$category</>");
            $io->text(str_repeat('─', 50));

            foreach ($featureKeys as $key) {
                $feature = self::FEATURES[$key];
                $isCurrentlyEnabled = $this->isFeatureEnabled($key, $currentConfig);
                $missingEntities = $this->getMissingEntities($feature['entities']);

                if ($feature['required']) {
                    $selectedFeatures[$key] = true;
                    $io->text("  <fg=green>✓</> {$feature['name']} <fg=gray>(required)</>");
                    continue;
                }

                $extraInfo = '';
                if (!empty($missingEntities)) {
                    $extraInfo = " <fg=yellow>[missing: " . implode(', ', $missingEntities) . "]</>";
                }

                $enabled = $io->confirm(
                    "  Enable {$feature['name']}?{$extraInfo}",
                    $isCurrentlyEnabled
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
        $this->showFeatureSummary($io, $selectedFeatures);

        if (!$io->confirm("\nApply this configuration?", true)) {
            $io->warning('Setup cancelled.');
            return Command::SUCCESS;
        }

        // Ask about controllers
        $withControllers = $input->getOption('with-controllers');
        if (!$withControllers) {
            $withControllers = $io->confirm('Generate required controllers?', true);
        }

        // Ask about migrations
        $withMigrations = $input->getOption('with-migrations') || $input->getOption('migrate');
        if (!$withMigrations) {
            $withMigrations = $io->confirm('Generate database migrations?', true);
        }

        $runMigrate = $input->getOption('migrate');
        if ($withMigrations && !$runMigrate) {
            $runMigrate = $io->confirm('Also run migrations now?', false);
        }

        return $this->applyConfiguration($io, $input, $selectedFeatures, $withControllers, $withMigrations, $runMigrate);
    }

    private function applyPreset(SymfonyStyle $io, InputInterface $input, string $preset): int
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

        $io->text("📦 Applying <info>$preset</info> preset...\n");

        $withControllers = $input->getOption('with-controllers');
        $withMigrations = $input->getOption('with-migrations') || $input->getOption('migrate');
        $runMigrate = $input->getOption('migrate');

        return $this->applyConfiguration($io, $input, $selectedFeatures, $withControllers, $withMigrations, $runMigrate);
    }

    private function toggleFeatures(SymfonyStyle $io, InputInterface $input, array $enable, array $disable): int
    {
        $currentConfig = $this->loadCurrentConfig();
        $selectedFeatures = [];

        // Start with current state
        foreach (self::FEATURES as $key => $feature) {
            $selectedFeatures[$key] = $this->isFeatureEnabled($key, $currentConfig);
        }

        // Apply changes
        foreach ($enable as $feature) {
            if (!isset(self::FEATURES[$feature])) {
                $io->warning("Unknown feature: $feature");
                continue;
            }
            $io->text("<fg=green>✓</> Enabling: " . self::FEATURES[$feature]['name']);
            $selectedFeatures[$feature] = true;
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
            $selectedFeatures[$feature] = false;
        }

        $io->newLine();

        $withControllers = $input->getOption('with-controllers');
        $withMigrations = $input->getOption('with-migrations') || $input->getOption('migrate');
        $runMigrate = $input->getOption('migrate');

        return $this->applyConfiguration($io, $input, $selectedFeatures, $withControllers, $withMigrations, $runMigrate);
    }

    private function applyConfiguration(
        SymfonyStyle $io,
        InputInterface $input,
        array $selectedFeatures,
        bool $withControllers,
        bool $withMigrations,
        bool $runMigrate
    ): int {
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        if ($dryRun) {
            $io->note('DRY RUN - No changes will be made');
        }

        // Step 1: Generate missing entities
        $io->section('📦 Step 1/4: Generating Entities');
        $generatedEntities = $this->generateRequiredEntities($io, $selectedFeatures, $dryRun, $force);

        // Step 2: Update configuration
        $io->section('⚙️ Step 2/4: Updating Configuration');
        $this->updateConfiguration($io, $selectedFeatures, $dryRun);

        // Step 3: Generate controllers
        if ($withControllers) {
            $io->section('🎮 Step 3/4: Generating Controllers');
            $this->generateRequiredControllers($io, $selectedFeatures, $dryRun, $force);
        } else {
            $io->section('🎮 Step 3/4: Skipping Controllers');
            $io->text('  <fg=gray>Use --with-controllers to generate them</>');
        }

        // Step 4: Migrations
        if ($withMigrations && !empty($generatedEntities)) {
            $io->section('🗃️ Step 4/4: Database Migrations');

            if ($dryRun) {
                $io->text('  Would run: <info>doctrine:migrations:diff</info>');
                if ($runMigrate) {
                    $io->text('  Would run: <info>doctrine:migrations:migrate</info>');
                }
            } else {
                $this->runDoctrineMigrationsDiff($io, $input->getOption('no-interaction'));

                if ($runMigrate) {
                    $this->runDoctrineMigrationsMigrate($io, $input->getOption('no-interaction'));
                }
            }
        } else {
            $io->section('🗃️ Step 4/4: Skipping Migrations');
            if (empty($generatedEntities)) {
                $io->text('  <fg=gray>No new entities generated</>');
            } else {
                $io->text('  <fg=gray>Use --with-migrations to generate them</>');
            }
        }

        // Final summary
        $io->newLine();
        if ($dryRun) {
            $io->success('Dry run complete! Use without --dry-run to apply changes.');
        } else {
            $io->success('Features configured successfully!');

            if (!$withMigrations && !empty($generatedEntities)) {
                $io->section('📝 Next Steps');
                $io->listing([
                    'Run <info>php bin/console doctrine:migrations:diff</info> to generate migrations',
                    'Run <info>php bin/console doctrine:migrations:migrate</info> to apply migrations',
                ]);
            }
        }

        return Command::SUCCESS;
    }

    private function generateRequiredEntities(SymfonyStyle $io, array $selectedFeatures, bool $dryRun, bool $force): array
    {
        $requiredEntities = $this->getRequiredEntities($selectedFeatures);
        $missingEntities = $this->getMissingEntities($requiredEntities);

        if (empty($missingEntities)) {
            $io->text('  <fg=green>✓</> All required entities already exist');
            return [];
        }

        $generatedEntities = [];
        // Find bundle path using ReflectionClass
        $reflection = new \ReflectionClass(self::class);
        $bundlePath = dirname($reflection->getFileName(), 3); // Go up from Command/ to bundle root
        $templatesDir = $bundlePath . '/Resources/templates/entities';
        $entitiesDir = $this->projectDir . '/src/Entity';

        if (!$this->filesystem->exists($entitiesDir) && !$dryRun) {
            $this->filesystem->mkdir($entitiesDir);
        }

        foreach ($missingEntities as $entityName) {
            $templateFile = self::ENTITY_TEMPLATES[$entityName] ?? null;

            if (!$templateFile) {
                $io->text("  <fg=yellow>⚠</> No template for $entityName (skipped)");
                continue;
            }

            $templatePath = $templatesDir . '/' . $templateFile;
            $targetPath = $entitiesDir . '/' . $entityName . '.php';

            if (!$this->filesystem->exists($templatePath)) {
                $io->text("  <fg=yellow>⚠</> Template not found: $templateFile");
                continue;
            }

            if ($dryRun) {
                $io->text("  <fg=cyan>○</> Would generate: $entityName.php");
            } else {
                // Check if file exists and handle accordingly
                if ($this->filesystem->exists($targetPath) && !$force) {
                    $io->text("  <fg=yellow>⊘</> $entityName.php already exists (use --force to overwrite)");
                    continue;
                }

                $content = file_get_contents($templatePath);
                $this->filesystem->dumpFile($targetPath, $content);
                $io->text("  <fg=green>✓</> Generated: $entityName.php");
            }

            $generatedEntities[] = $entityName;
        }

        return $generatedEntities;
    }

    private function updateConfiguration(SymfonyStyle $io, array $selectedFeatures, bool $dryRun): void
    {
        $configFile = $this->projectDir . '/config/packages/better_auth.yaml';

        // Load existing config or create new one
        $config = [];
        if ($this->filesystem->exists($configFile)) {
            $config = Yaml::parseFile($configFile) ?? [];
        }

        if (!isset($config['better_auth'])) {
            $config['better_auth'] = [
                'mode' => 'api',
                'secret' => '%env(BETTER_AUTH_SECRET)%',
            ];
        }

        // Apply feature configurations
        foreach ($selectedFeatures as $feature => $enabled) {
            if ($feature === 'oauth_providers') {
                continue;
            }
            if ($enabled) {
                $config = $this->enableFeatureInConfig($config, $feature);
            } else {
                $config = $this->disableFeatureInConfig($config, $feature);
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

        if ($dryRun) {
            $io->text("  <fg=cyan>○</> Would update: config/packages/better_auth.yaml");
        } else {
            $this->filesystem->dumpFile($configFile, Yaml::dump($config, 4));
            $io->text("  <fg=green>✓</> Updated: config/packages/better_auth.yaml");
        }

        // Update .env if needed
        $this->updateEnvFile($io, $selectedFeatures, $dryRun);
    }

    private function generateRequiredControllers(SymfonyStyle $io, array $selectedFeatures, bool $dryRun, bool $force): void
    {
        // Determine which controllers are needed for NEW features only
        $requiredControllers = [];
        $coreControllers = ['auth', 'password', 'sessions']; // Core controllers linked to required features

        foreach ($selectedFeatures as $feature => $enabled) {
            if ($enabled && isset(self::FEATURES[$feature]['controllers'])) {
                foreach (self::FEATURES[$feature]['controllers'] as $controller) {
                    // Skip core controllers - they should be installed via better-auth:install
                    if (!in_array($controller, $coreControllers, true)) {
                        $requiredControllers[$controller] = $feature;
                    }
                }
            }
        }

        if (empty($requiredControllers)) {
            $io->text('  <fg=gray>No additional controllers required for selected features</>');
            $io->text('  <fg=gray>Core controllers (auth, password, sessions) should be installed via better-auth:install</>');
            return;
        }

        $controllersDir = $this->projectDir . '/src/Controller';
        $traitDir = $controllersDir . '/Trait';
        // Find bundle path using ReflectionClass
        $reflection = new \ReflectionClass(self::class);
        $bundlePath = dirname($reflection->getFileName(), 3); // Go up from Command/ to bundle root
        $templatesDir = $bundlePath . '/Resources/templates/controller';

        // Controller name mapping
        $controllerMapping = [
            'oauth' => ['OAuthController.php.tpl', 'OAuthController.php', 'OAuth (Google, GitHub, Facebook, etc.)'],
            'email-verification' => ['EmailVerificationController.php.tpl', 'EmailVerificationController.php', 'Email verification flow'],
            'magic-link' => ['MagicLinkController.php.tpl', 'MagicLinkController.php', 'Magic link (passwordless)'],
            'guest' => ['GuestSessionController.php.tpl', 'GuestSessionController.php', 'Guest/anonymous sessions'],
            'account-link' => ['AccountLinkController.php.tpl', 'AccountLinkController.php', 'Account linking'],
            'devices' => ['DeviceController.php.tpl', 'DeviceController.php', 'Device management'],
            'trait' => ['ApiResponseTrait.php.tpl', 'Trait/ApiResponseTrait.php', 'API response formatting'],
        ];

        // Detect existing controllers in both locations
        $existingControllers = $this->detectExistingControllers();

        // Check if trait is needed and exists
        $needTrait = true;
        if (isset($existingControllers['ApiResponseTrait'])) {
            $needTrait = false;
        }

        if (!$dryRun) {
            if (!$this->filesystem->exists($controllersDir)) {
                $this->filesystem->mkdir($controllersDir);
            }
            if (!$this->filesystem->exists($traitDir)) {
                $this->filesystem->mkdir($traitDir);
            }
        }

        // Generate trait first if needed
        if ($needTrait) {
            $this->generateSingleController($io, $templatesDir, $controllersDir, 'trait', $controllerMapping, $existingControllers, $dryRun, $force);
        }

        // Ask for each controller individually
        foreach ($requiredControllers as $controller => $forFeature) {
            if (!isset($controllerMapping[$controller])) {
                continue;
            }

            [$templateFile, $targetFile, $description] = $controllerMapping[$controller];
            $controllerName = basename($targetFile, '.php');

            // Check if already exists
            if (isset($existingControllers[$controllerName])) {
                $io->text(sprintf('  <fg=yellow>⊘</> %s already exists at %s', $controllerName, $existingControllers[$controllerName]));
                continue;
            }

            // Ask user if they want to generate this controller
            if (!$dryRun && !$force) {
                if (!$io->confirm(sprintf('  Generate %s for feature "%s"?', $controllerName, $forFeature), true)) {
                    $io->text(sprintf('  <fg=yellow>⊘</> Skipped %s', $controllerName));
                    continue;
                }
            }

            $this->generateSingleController($io, $templatesDir, $controllersDir, $controller, $controllerMapping, $existingControllers, $dryRun, $force);
        }
    }

    /**
     * Generate a single controller file.
     */
    private function generateSingleController(
        SymfonyStyle $io,
        string $templatesDir,
        string $controllersDir,
        string $controller,
        array $controllerMapping,
        array $existingControllers,
        bool $dryRun,
        bool $force
    ): void {
        if (!isset($controllerMapping[$controller])) {
            return;
        }

        [$templateFile, $targetFile] = $controllerMapping[$controller];
        $templatePath = $templatesDir . '/' . $templateFile;
        $targetPath = $controllersDir . '/' . $targetFile;
        $controllerName = basename($targetFile, '.php');

        if (!$this->filesystem->exists($templatePath)) {
            $io->text("  <fg=yellow>⚠</> Template not found: $templateFile");
            return;
        }

        // Check both locations
        $existsInStandard = $this->filesystem->exists($targetPath);
        $existsInLegacy = $this->filesystem->exists($this->projectDir . '/src/Controller/Api/' . $targetFile);

        if ($dryRun) {
            if ($existsInStandard || $existsInLegacy) {
                $location = $existsInStandard ? 'src/Controller/' : 'src/Controller/Api/';
                $io->text("  <fg=yellow>○</> Would skip (exists at $location): $targetFile");
            } else {
                $io->text("  <fg=cyan>○</> Would generate: $targetFile");
            }
        } else {
            if ($existsInStandard && !$force) {
                $io->text("  <fg=yellow>⊘</> Skipped (exists): $targetFile");
                return;
            }

            if ($existsInLegacy && !$force) {
                $io->text("  <fg=yellow>⊘</> Skipped (exists in legacy location): $targetFile");
                return;
            }

            $content = file_get_contents($templatePath);
            $this->filesystem->dumpFile($targetPath, $content);
            $io->text("  <fg=green>✓</> Generated: $targetFile");
        }
    }

    /**
     * Detect existing controllers in both standard and legacy locations.
     */
    private function detectExistingControllers(): array
    {
        $controllers = [
            'AuthController',
            'PasswordController',
            'SessionsController',
            'OAuthController',
            'MagicLinkController',
            'EmailVerificationController',
            'GuestSessionController',
            'AccountLinkController',
            'DeviceController',
            'ApiResponseTrait',
        ];

        $found = [];
        foreach ($controllers as $controller) {
            // Check for trait in Trait subfolder
            if ($controller === 'ApiResponseTrait') {
                $standardPath = $this->projectDir . '/src/Controller/Trait/' . $controller . '.php';
                if ($this->filesystem->exists($standardPath)) {
                    $found[$controller] = 'src/Controller/Trait/' . $controller . '.php';
                    continue;
                }
                $legacyPath = $this->projectDir . '/src/Controller/Api/Trait/' . $controller . '.php';
                if ($this->filesystem->exists($legacyPath)) {
                    $found[$controller] = 'src/Controller/Api/Trait/' . $controller . '.php';
                }
                continue;
            }

            // Check src/Controller/
            $standardPath = $this->projectDir . '/src/Controller/' . $controller . '.php';
            if ($this->filesystem->exists($standardPath)) {
                $found[$controller] = 'src/Controller/' . $controller . '.php';
                continue;
            }

            // Check src/Controller/Api/
            $legacyPath = $this->projectDir . '/src/Controller/Api/' . $controller . '.php';
            if ($this->filesystem->exists($legacyPath)) {
                $found[$controller] = 'src/Controller/Api/' . $controller . '.php';
            }
        }

        return $found;
    }

    private function runDoctrineMigrationsDiff(SymfonyStyle $io, bool $noInteraction): void
    {
        $io->text('  Running <info>doctrine:migrations:diff</info>...');

        try {
            $application = $this->getApplication();
            if (!$application) {
                $io->warning('Cannot run migrations:diff - no application context');
                return;
            }

            $command = $application->find('doctrine:migrations:diff');
            $arguments = new ArrayInput([]);
            if ($noInteraction) {
                $arguments->setInteractive(false);
            }

            $returnCode = $command->run($arguments, $io);

            if ($returnCode === Command::SUCCESS) {
                $io->text('  <fg=green>✓</> Migration file generated');
            }
        } catch (\Exception $e) {
            $io->warning('Could not run migrations:diff automatically: ' . $e->getMessage());
            $io->text('  Run manually: <info>php bin/console doctrine:migrations:diff</info>');
        }
    }

    private function runDoctrineMigrationsMigrate(SymfonyStyle $io, bool $noInteraction): void
    {
        $io->text('  Running <info>doctrine:migrations:migrate</info>...');

        try {
            $application = $this->getApplication();
            if (!$application) {
                $io->warning('Cannot run migrations:migrate - no application context');
                return;
            }

            $command = $application->find('doctrine:migrations:migrate');
            $arguments = new ArrayInput(['--no-interaction' => true]);

            $returnCode = $command->run($arguments, $io);

            if ($returnCode === Command::SUCCESS) {
                $io->text('  <fg=green>✓</> Migrations applied');
            }
        } catch (\Exception $e) {
            $io->warning('Could not run migrations:migrate automatically: ' . $e->getMessage());
            $io->text('  Run manually: <info>php bin/console doctrine:migrations:migrate</info>');
        }
    }

    private function getRequiredEntities(array $selectedFeatures): array
    {
        $entities = [];

        foreach ($selectedFeatures as $feature => $enabled) {
            if ($enabled && isset(self::FEATURES[$feature]['entities'])) {
                foreach (self::FEATURES[$feature]['entities'] as $entity) {
                    $entities[$entity] = true;
                }
            }
        }

        return array_keys($entities);
    }

    private function getMissingEntities(array $entities): array
    {
        $missing = [];
        $entitiesDir = $this->projectDir . '/src/Entity';

        foreach ($entities as $entity) {
            $entityFile = $entitiesDir . '/' . $entity . '.php';
            if (!$this->filesystem->exists($entityFile)) {
                $missing[] = $entity;
            }
        }

        return $missing;
    }

    private function loadCurrentConfig(): array
    {
        $configFile = $this->projectDir . '/config/packages/better_auth.yaml';

        if (!$this->filesystem->exists($configFile)) {
            return [];
        }

        return Yaml::parseFile($configFile) ?? [];
    }

    private function isFeatureEnabled(string $feature, array $config): bool
    {
        $featureConfig = self::FEATURES[$feature];

        if ($featureConfig['required']) {
            return true;
        }

        // Check config for explicit settings
        $betterAuth = $config['better_auth'] ?? [];

        return match ($feature) {
            'two_factor' => ($betterAuth['two_factor']['enabled'] ?? false) === true,
            'oauth' => !empty($betterAuth['oauth']['providers'] ?? []),
            'multi_tenant' => ($betterAuth['multi_tenant']['enabled'] ?? false) === true,
            'magic_link' => ($betterAuth['magic_link']['enabled'] ?? false) === true,
            'email_verification' => ($betterAuth['email_verification']['enabled'] ?? $featureConfig['default']) === true,
            'password_reset' => ($betterAuth['password_reset']['enabled'] ?? $featureConfig['default']) === true,
            'session_management' => ($betterAuth['session']['enabled'] ?? $featureConfig['default']) !== false,
            'device_tracking' => ($betterAuth['device_tracking']['enabled'] ?? false) === true,
            'security_monitoring' => ($betterAuth['security_monitoring']['enabled'] ?? false) === true,
            'guest_sessions' => ($betterAuth['guest_sessions']['enabled'] ?? false) === true,
            default => $featureConfig['default'],
        };
    }

    private function showFeatureSummary(SymfonyStyle $io, array $selectedFeatures): void
    {
        $io->section('📊 Feature Summary');

        $enabledFeatures = array_filter($selectedFeatures, fn($v) => $v === true);
        $disabledFeatures = array_filter($selectedFeatures, fn($v) => $v === false);

        $io->text('<fg=green>Enabled:</>');
        foreach ($enabledFeatures as $key => $value) {
            if ($key === 'oauth_providers') {
                continue;
            }
            $entities = self::FEATURES[$key]['entities'] ?? [];
            $entityInfo = empty($entities) ? '' : ' <fg=gray>[' . implode(', ', $entities) . ']</>';
            $io->text("  ✓ " . self::FEATURES[$key]['name'] . $entityInfo);
        }

        if (!empty($selectedFeatures['oauth_providers'])) {
            $io->text("    OAuth Providers: " . implode(', ', $selectedFeatures['oauth_providers']));
        }

        $io->text("\n<fg=red>Disabled:</>");
        foreach ($disabledFeatures as $key => $value) {
            $io->text("  ✗ " . self::FEATURES[$key]['name']);
        }
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
                if (!isset($config['better_auth']['oauth'])) {
                    $config['better_auth']['oauth'] = ['providers' => []];
                }
                break;

            case 'multi_tenant':
                $config['better_auth']['multi_tenant'] = [
                    'enabled' => true,
                    'default_role' => 'member',
                ];
                break;

            case 'session_management':
                $config['better_auth']['session'] = [
                    'lifetime' => 604800,
                    'cookie_name' => 'better_auth_session',
                ];
                break;

            case 'magic_link':
                $config['better_auth']['magic_link'] = [
                    'enabled' => true,
                    'lifetime' => 900, // 15 minutes
                ];
                break;

            case 'email_verification':
                $config['better_auth']['email_verification'] = [
                    'enabled' => true,
                    'lifetime' => 86400, // 24 hours
                ];
                break;

            case 'password_reset':
                $config['better_auth']['password_reset'] = [
                    'enabled' => true,
                    'lifetime' => 3600, // 1 hour
                ];
                break;

            case 'device_tracking':
                $config['better_auth']['device_tracking'] = ['enabled' => true];
                break;

            case 'security_monitoring':
                $config['better_auth']['security_monitoring'] = ['enabled' => true];
                break;

            case 'guest_sessions':
                $config['better_auth']['guest_sessions'] = [
                    'enabled' => true,
                    'lifetime' => 86400,
                ];
                break;

        }

        return $config;
    }

    private function disableFeatureInConfig(array $config, string $feature): array
    {
        $featureKeys = [
            'two_factor' => 'two_factor',
            'oauth' => 'oauth',
            'multi_tenant' => 'multi_tenant',
            'magic_link' => 'magic_link',
            'email_verification' => 'email_verification',
            'password_reset' => 'password_reset',
            'session_management' => 'session',
            'device_tracking' => 'device_tracking',
            'security_monitoring' => 'security_monitoring',
            'guest_sessions' => 'guest_sessions',
        ];

        $configKey = $featureKeys[$feature] ?? null;

        if ($configKey && isset($config['better_auth'][$configKey])) {
            if (is_array($config['better_auth'][$configKey])) {
                $config['better_auth'][$configKey]['enabled'] = false;
            } else {
                unset($config['better_auth'][$configKey]);
            }
        }

        return $config;
    }

    private function updateEnvFile(SymfonyStyle $io, array $selectedFeatures, bool $dryRun): void
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
            if ($dryRun) {
                $io->text("  <fg=cyan>○</> Would add to .env: " . implode(', ', array_map(fn($a) => explode('=', $a)[0], $additions)));
            } else {
                $envContent .= "\n###> betterauth/symfony ###\n";
                $envContent .= implode("\n", $additions);
                $envContent .= "\n###< betterauth/symfony ###\n";

                $this->filesystem->dumpFile($envFile, $envContent);
                $io->text("  <fg=green>✓</> Updated: .env");
            }
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

The <info>better-auth:setup-features</info> command enables/disables features with
<fg=green>automatic entity generation</>, <fg=green>controller scaffolding</>, and <fg=green>database migrations</>.

<fg=yellow;options=bold>QUICK START</>

  <comment># Enable magic link with everything auto-generated</comment>
  php bin/console better-auth:setup-features --enable=magic_link --with-controllers --migrate

  <comment># Enable multiple features at once</comment>
  php bin/console better-auth:setup-features --enable=magic_link --enable=two_factor --migrate

  <comment># Full setup with all features</comment>
  php bin/console better-auth:setup-features --preset=full --with-controllers --migrate

<fg=yellow;options=bold>OPTIONS</>

  <info>--enable=FEATURE</info>       Enable a feature (can be used multiple times)
  <info>--disable=FEATURE</info>      Disable a feature
  <info>--preset=PRESET</info>        Use preset: minimal, standard, full
  <info>--with-controllers</info>     Generate required controllers
  <info>--with-migrations</info>      Run doctrine:migrations:diff
  <info>--migrate</info>              Run both diff and migrate
  <info>--force</info>                Overwrite existing files
  <info>--dry-run</info>              Preview changes without applying

<fg=yellow;options=bold>AVAILABLE FEATURES</>

  <info>Authentication</info>
    • email_password     - Classic email/password login (required)
    • oauth              - Google, GitHub, Facebook, etc.
    • magic_link         - Passwordless email links → <fg=cyan>MagicLinkToken</>

  <info>Security</info>
    • two_factor         - TOTP 2FA → <fg=cyan>TotpData</>
    • device_tracking    - Track devices → <fg=cyan>Device</>
    • security_monitoring - Threat detection → <fg=cyan>SecurityEvent</>

  <info>User Management</info>
    • email_verification - Verify emails → <fg=cyan>EmailVerificationToken</>
    • password_reset     - Forgot password → <fg=cyan>PasswordResetToken</>
    • session_management - View/revoke sessions

  <info>Advanced</info>
    • guest_sessions     - Anonymous users → <fg=cyan>GuestSession</>
    • multi_tenant       - Organizations → <fg=cyan>Organization, OrganizationMember</>

<fg=yellow;options=bold>WORKFLOW</>

  1. Detects which entities are missing for enabled features
  2. Generates entity PHP files from templates
  3. Updates <info>config/packages/better_auth.yaml</info>
  4. Optionally generates controllers
  5. Optionally runs <info>doctrine:migrations:diff</info> and <info>migrate</info>

HELP;
    }
}
