<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Adds individual BetterAuth controllers to your project.
 *
 * Use this command to add specific authentication features after the initial installation.
 */
#[AsCommand(
    name: 'better-auth:add-controller',
    description: 'Add a specific BetterAuth controller to your project'
)]
class AddControllerCommand extends Command
{
    /**
     * Available controllers with their templates and descriptions.
     */
    private const CONTROLLERS = [
        'auth' => [
            'template' => 'AuthController.php.tpl',
            'target' => 'AuthController.php',
            'description' => 'Core authentication (register, login, logout, me, refresh, 2FA)',
            'endpoints' => [
                'POST /auth/register',
                'POST /auth/login',
                'POST /auth/login/2fa',
                'GET  /auth/me',
                'POST /auth/refresh',
                'POST /auth/logout',
                'POST /auth/revoke-all',
                'POST /auth/2fa/setup',
                'POST /auth/2fa/verify',
                'POST /auth/2fa/disable',
                'GET  /auth/2fa/status',
            ],
            'dependencies' => ['trait'],
        ],
        'password' => [
            'template' => 'PasswordController.php.tpl',
            'target' => 'PasswordController.php',
            'description' => 'Password reset flow (forgot, reset, verify-token)',
            'endpoints' => [
                'POST /auth/password/forgot',
                'POST /auth/password/reset',
                'POST /auth/password/verify-token',
            ],
            'dependencies' => ['trait'],
        ],
        'sessions' => [
            'template' => 'SessionsController.php.tpl',
            'target' => 'SessionsController.php',
            'description' => 'Session management (list, revoke sessions)',
            'endpoints' => [
                'GET    /auth/sessions',
                'DELETE /auth/sessions/{sessionId}',
            ],
            'dependencies' => ['trait'],
        ],
        'oauth' => [
            'template' => 'OAuthController.php.tpl',
            'target' => 'OAuthController.php',
            'description' => 'OAuth authentication (Google, GitHub, Facebook, etc.)',
            'endpoints' => [
                'GET  /auth/oauth',
                'GET  /auth/oauth/{provider}',
                'GET  /auth/oauth/{provider}/callback',
            ],
            'dependencies' => ['trait'],
        ],
        'email-verification' => [
            'template' => 'EmailVerificationController.php.tpl',
            'target' => 'EmailVerificationController.php',
            'description' => 'Email verification flow (send, verify, resend)',
            'endpoints' => [
                'POST /auth/email/send-verification',
                'POST /auth/email/verify',
                'POST /auth/email/resend',
                'GET  /auth/email/status',
            ],
            'dependencies' => ['trait'],
        ],
        'magic-link' => [
            'template' => 'MagicLinkController.php.tpl',
            'target' => 'MagicLinkController.php',
            'description' => 'Passwordless authentication via magic links',
            'endpoints' => [
                'POST /auth/magic-link/request',
                'POST /auth/magic-link/verify',
                'POST /auth/magic-link/check',
            ],
            'dependencies' => ['trait'],
        ],
        'guest' => [
            'template' => 'GuestSessionController.php.tpl',
            'target' => 'GuestSessionController.php',
            'description' => 'Guest/anonymous sessions with conversion to registered users',
            'endpoints' => [
                'POST  /auth/guest/create',
                'POST  /auth/guest/convert',
                'GET   /auth/guest/{token}',
                'PATCH /auth/guest/{token}',
            ],
            'dependencies' => ['trait'],
        ],
        'account-link' => [
            'template' => 'AccountLinkController.php.tpl',
            'target' => 'AccountLinkController.php',
            'description' => 'Link/unlink third-party accounts (OAuth) to user accounts',
            'endpoints' => [
                'GET    /auth/account/links',
                'GET    /auth/account/link/{provider}',
                'GET    /auth/account/link/{provider}/callback',
                'DELETE /auth/account/link/{provider}',
            ],
            'dependencies' => ['trait'],
        ],
        'devices' => [
            'template' => 'DeviceController.php.tpl',
            'target' => 'DeviceController.php',
            'description' => 'Device management (list, revoke, trust devices)',
            'endpoints' => [
                'GET    /auth/devices',
                'GET    /auth/devices/{deviceId}',
                'DELETE /auth/devices/{deviceId}',
                'POST   /auth/devices/{deviceId}/trust',
                'DELETE /auth/devices/{deviceId}/trust',
                'POST   /auth/devices/revoke-all',
            ],
            'dependencies' => ['trait'],
        ],
        'trait' => [
            'template' => 'ApiResponseTrait.php.tpl',
            'target' => 'Trait/ApiResponseTrait.php',
            'description' => 'Shared API response formatting trait (auto-installed with other controllers)',
            'endpoints' => [],
            'dependencies' => [],
        ],
    ];

    protected function configure(): void
    {
        $this
            ->addArgument(
                'controller',
                InputArgument::OPTIONAL,
                'Controller to add (auth, password, sessions, oauth, email-verification, magic-link, guest, account-link, devices)'
            )
            ->addOption('all', 'a', InputOption::VALUE_NONE, 'Install all available controllers')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing files without asking')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List all available controllers')
            ->setHelp($this->getDetailedHelp())
        ;
    }

    private function getDetailedHelp(): string
    {
        $controllersList = '';
        foreach (self::CONTROLLERS as $name => $config) {
            if ($name === 'trait') {
                continue;
            }
            $controllersList .= sprintf("  <info>%s</info>\n    %s\n", $name, $config['description']);
        }

        return <<<HELP
Adds individual BetterAuth controllers to your project.

<fg=yellow>Available Controllers:</fg>
$controllersList
<fg=yellow>Usage Examples:</fg>

  <comment># List all available controllers</comment>
  <info>php bin/console better-auth:add-controller --list</info>

  <comment># Add a specific controller (interactive)</comment>
  <info>php bin/console better-auth:add-controller oauth</info>

  <comment># Add all controllers at once</comment>
  <info>php bin/console better-auth:add-controller --all</info>

  <comment># Force overwrite existing files</comment>
  <info>php bin/console better-auth:add-controller oauth --force</info>

<fg=yellow>Generated Files:</fg>

  Controllers are generated in: <info>src/Controller/Api/</info>
  Trait is generated in: <info>src/Controller/Api/Trait/</info>

<fg=yellow>Dependencies:</fg>

  All controllers depend on the ApiResponseTrait.
  It will be automatically generated if missing.

HELP;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();
        $projectDir = $this->getProjectDir();

        // List mode
        if ($input->getOption('list')) {
            return $this->listControllers($io);
        }

        $controllerName = $input->getArgument('controller');
        $installAll = $input->getOption('all');
        $force = $input->getOption('force');

        // Interactive selection if no controller specified
        if (!$controllerName && !$installAll) {
            return $this->interactiveSelection($io, $filesystem, $projectDir, $force);
        }

        // Install all controllers
        if ($installAll) {
            return $this->installAllControllers($io, $filesystem, $projectDir, $force);
        }

        // Install single controller
        if (!isset(self::CONTROLLERS[$controllerName])) {
            $io->error(sprintf('Unknown controller: %s', $controllerName));
            $io->writeln('Available controllers: ' . implode(', ', array_keys(self::CONTROLLERS)));
            return Command::FAILURE;
        }

        return $this->installController($io, $filesystem, $projectDir, $controllerName, $force);
    }

    private function listControllers(SymfonyStyle $io): int
    {
        $io->title('📋 Available BetterAuth Controllers');

        $rows = [];
        foreach (self::CONTROLLERS as $name => $config) {
            if ($name === 'trait') {
                continue;
            }
            $rows[] = [
                $name,
                $config['description'],
                count($config['endpoints']),
            ];
        }

        $io->table(['Controller', 'Description', 'Endpoints'], $rows);

        $io->section('📍 Endpoints by Controller');
        foreach (self::CONTROLLERS as $name => $config) {
            if ($name === 'trait' || empty($config['endpoints'])) {
                continue;
            }
            $io->writeln(sprintf('<fg=cyan>%s</> (%s):', $name, $config['description']));
            foreach ($config['endpoints'] as $endpoint) {
                $io->writeln('  • ' . $endpoint);
            }
            $io->newLine();
        }

        return Command::SUCCESS;
    }

    private function interactiveSelection(SymfonyStyle $io, Filesystem $filesystem, string $projectDir, bool $force): int
    {
        $io->title('🎮 BetterAuth Controller Selection');

        $controllerDir = $projectDir . '/src/Controller/Api';

        // Show current state
        $io->section('Current State');
        foreach (self::CONTROLLERS as $name => $config) {
            $targetPath = $controllerDir . '/' . $config['target'];
            $status = $filesystem->exists($targetPath) ? '<fg=green>✓ Installed</>' : '<fg=gray>○ Not installed</>';
            $io->writeln(sprintf('  %s: %s', $name, $status));
        }
        $io->newLine();

        // Ask which controllers to install
        $choices = [];
        foreach (self::CONTROLLERS as $name => $config) {
            if ($name === 'trait') {
                continue;
            }
            $choices[$name] = sprintf('%s - %s (%d endpoints)', $name, $config['description'], count($config['endpoints']));
        }

        $selected = $io->choice(
            'Which controller do you want to add?',
            array_merge(['all' => 'Install all controllers'], $choices),
            'auth'
        );

        if ($selected === 'all') {
            return $this->installAllControllers($io, $filesystem, $projectDir, $force);
        }

        return $this->installController($io, $filesystem, $projectDir, $selected, $force);
    }

    private function installAllControllers(SymfonyStyle $io, Filesystem $filesystem, string $projectDir, bool $force): int
    {
        $io->title('🚀 Installing All BetterAuth Controllers');

        $installed = 0;
        $skipped = 0;

        // Install trait first
        $result = $this->installSingleController($io, $filesystem, $projectDir, 'trait', $force);
        if ($result === 'installed') {
            $installed++;
        } elseif ($result === 'skipped') {
            $skipped++;
        }

        // Install all other controllers
        foreach (self::CONTROLLERS as $name => $config) {
            if ($name === 'trait') {
                continue;
            }
            $result = $this->installSingleController($io, $filesystem, $projectDir, $name, $force);
            if ($result === 'installed') {
                $installed++;
            } elseif ($result === 'skipped') {
                $skipped++;
            }
        }

        $io->newLine();
        $io->success(sprintf('Installation complete! Installed: %d, Skipped: %d', $installed, $skipped));

        $this->displaySummary($io);

        return Command::SUCCESS;
    }

    private function installController(SymfonyStyle $io, Filesystem $filesystem, string $projectDir, string $name, bool $force): int
    {
        $io->title(sprintf('🎮 Installing %s Controller', ucfirst($name)));

        $config = self::CONTROLLERS[$name];

        // Install dependencies first
        foreach ($config['dependencies'] as $dependency) {
            $this->installSingleController($io, $filesystem, $projectDir, $dependency, $force);
        }

        // Install the controller
        $result = $this->installSingleController($io, $filesystem, $projectDir, $name, $force);

        if ($result === 'installed') {
            $io->success(sprintf('%s controller installed successfully!', ucfirst($name)));

            if (!empty($config['endpoints'])) {
                $io->section('Available Endpoints');
                foreach ($config['endpoints'] as $endpoint) {
                    $io->writeln('  • ' . $endpoint);
                }
            }
        } elseif ($result === 'skipped') {
            $io->info(sprintf('%s controller already exists (use --force to overwrite)', ucfirst($name)));
        }

        return Command::SUCCESS;
    }

    private function installSingleController(SymfonyStyle $io, Filesystem $filesystem, string $projectDir, string $name, bool $force): string
    {
        $config = self::CONTROLLERS[$name];
        $templatesDir = dirname(__DIR__) . '/Resources/templates/controller';
        $controllerDir = $projectDir . '/src/Controller/Api';

        // Ensure directories exist
        $traitDir = $controllerDir . '/Trait';
        if (!$filesystem->exists($controllerDir)) {
            $filesystem->mkdir($controllerDir);
        }
        if (!$filesystem->exists($traitDir)) {
            $filesystem->mkdir($traitDir);
        }

        $templatePath = $templatesDir . '/' . $config['template'];
        $targetPath = $controllerDir . '/' . $config['target'];

        // Check if template exists
        if (!$filesystem->exists($templatePath)) {
            $io->error(sprintf('Template not found: %s', $config['template']));
            return 'error';
        }

        // Check if target exists
        if ($filesystem->exists($targetPath) && !$force) {
            if (!$io->isInteractive() || !$io->confirm(sprintf('%s already exists. Overwrite?', $config['target']), false)) {
                $io->writeln(sprintf('  <fg=yellow>⊘</> Skipped %s', $config['target']));
                return 'skipped';
            }
        }

        // Copy template
        $content = file_get_contents($templatePath);
        $filesystem->dumpFile($targetPath, $content);

        $io->writeln(sprintf('  <fg=green>✓</> Generated %s', $config['target']));
        return 'installed';
    }

    private function displaySummary(SymfonyStyle $io): void
    {
        $io->section('📁 Generated Structure');
        $io->writeln([
            '  src/Controller/Api/',
            '  ├── Trait/',
            '  │   └── ApiResponseTrait.php',
            '  ├── AuthController.php',
            '  ├── PasswordController.php',
            '  ├── SessionsController.php',
            '  ├── OAuthController.php',
            '  ├── EmailVerificationController.php',
            '  ├── MagicLinkController.php',
            '  ├── GuestSessionController.php',
            '  ├── AccountLinkController.php',
            '  └── DeviceController.php',
        ]);

        $io->section('🛣️ All Available Endpoints');
        $totalEndpoints = 0;
        foreach (self::CONTROLLERS as $name => $config) {
            if ($name === 'trait') {
                continue;
            }
            $totalEndpoints += count($config['endpoints']);
        }
        $io->writeln(sprintf('  Total endpoints: <info>%d</info>', $totalEndpoints));
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

