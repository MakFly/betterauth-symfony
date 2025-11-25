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
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'better-auth:switch-mode',
    description: 'Switch BetterAuth authentication mode (api, session, hybrid)'
)]
class SwitchModeCommand extends Command
{
    private const MODES = [
        'api' => [
            'name' => 'API Mode (Stateless)',
            'description' => 'Paseto V4 tokens, no cookies - ideal for SPAs, mobile apps, microservices',
            'features' => [
                'Stateless authentication with Paseto V4 tokens',
                'Access tokens (configurable lifetime, default 1h)',
                'Refresh tokens (configurable lifetime, default 30 days)',
                'Bearer token in Authorization header',
                'No server-side session storage needed',
            ],
        ],
        'session' => [
            'name' => 'Session Mode (Stateful)',
            'description' => 'Cookie-based sessions - ideal for traditional web apps, Twig templates',
            'features' => [
                'Stateful authentication with secure HTTP-only cookies',
                'Server-side session storage in database',
                'CSRF protection built-in',
                'Session tracking (devices, IP, location)',
                'Automatic session expiration',
            ],
        ],
        'hybrid' => [
            'name' => 'Hybrid Mode (Both)',
            'description' => 'Supports both tokens AND sessions - maximum flexibility',
            'features' => [
                'Use API tokens for mobile/SPA clients',
                'Use sessions for web frontend',
                'Single backend, multiple client types',
                'Automatic detection based on request type',
                'Best for apps with mixed client needs',
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
            ->addArgument('mode', InputArgument::OPTIONAL, 'Target mode: api, session, or hybrid')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show changes without applying them')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Skip confirmation prompt')
            ->setHelp($this->getDetailedHelp());
    }

    private function getDetailedHelp(): string
    {
        return <<<'HELP'
<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>
<fg=cyan;options=bold>                    BetterAuth Mode Switcher</>
<fg=cyan>═══════════════════════════════════════════════════════════════════════════════</>

The <info>better-auth:switch-mode</info> command allows you to quickly change the authentication
mode of your BetterAuth installation.

<fg=yellow;options=bold>USAGE</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <comment># Show current mode and available options</comment>
  <info>php bin/console better-auth:switch-mode</info>

  <comment># Switch to API mode</comment>
  <info>php bin/console better-auth:switch-mode api</info>

  <comment># Switch to session mode</comment>
  <info>php bin/console better-auth:switch-mode session</info>

  <comment># Switch to hybrid mode</comment>
  <info>php bin/console better-auth:switch-mode hybrid</info>

  <comment># Preview changes without applying</comment>
  <info>php bin/console better-auth:switch-mode api --dry-run</info>

  <comment># Skip confirmation prompt</comment>
  <info>php bin/console better-auth:switch-mode api --force</info>

<fg=yellow;options=bold>MODES EXPLAINED</>
<fg=yellow>─────────────────────────────────────────────────────────────────────────────────</>

  <info>API Mode</info>
    Best for: SPAs (React, Vue, Angular), Mobile apps, Microservices
    Auth method: Bearer tokens in Authorization header
    Storage: Stateless (no server session)

  <info>Session Mode</info>
    Best for: Traditional web apps, Twig templates, Server-rendered pages
    Auth method: HTTP-only secure cookies
    Storage: Database session table

  <info>Hybrid Mode</info>
    Best for: Apps with both web and mobile clients
    Auth method: Both tokens AND cookies
    Storage: Supports both methods

HELP;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();

        $configPath = $this->projectDir . '/config/packages/better_auth.yaml';

        if (!$filesystem->exists($configPath)) {
            $io->error('BetterAuth configuration file not found at: ' . $configPath);
            $io->note('Run "php bin/console better-auth:install" first to set up BetterAuth.');
            return Command::FAILURE;
        }

        // Read current config
        $config = Yaml::parseFile($configPath);
        $currentMode = $config['better_auth']['mode'] ?? 'hybrid';

        $targetMode = $input->getArgument('mode');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        // If no mode specified, show current status and let user choose
        if (!$targetMode) {
            $io->title('BetterAuth Mode Switcher');

            $io->section('Current Configuration');
            $io->definitionList(
                ['Current Mode' => sprintf('<info>%s</info> - %s', $currentMode, self::MODES[$currentMode]['name'])],
                ['Config File' => $configPath]
            );

            $io->section('Available Modes');
            foreach (self::MODES as $mode => $info) {
                $isCurrent = $mode === $currentMode;
                $prefix = $isCurrent ? '<fg=green>► </>' : '  ';
                $suffix = $isCurrent ? ' <fg=green>(current)</>' : '';

                $io->writeln(sprintf('%s<info>%s</info>%s', $prefix, $info['name'], $suffix));
                $io->writeln(sprintf('    %s', $info['description']));
                $io->newLine();
            }

            $targetMode = $io->choice(
                'Select the mode you want to switch to',
                array_keys(self::MODES),
                $currentMode
            );
        }

        // Validate mode
        if (!isset(self::MODES[$targetMode])) {
            $io->error(sprintf('Invalid mode "%s". Valid modes are: api, session, hybrid', $targetMode));
            return Command::FAILURE;
        }

        // Check if already in target mode
        if ($targetMode === $currentMode) {
            $io->success(sprintf('Already in %s mode. No changes needed.', self::MODES[$targetMode]['name']));
            return Command::SUCCESS;
        }

        // Show what will change
        $io->section('Mode Change Summary');

        $io->definitionList(
            ['From' => sprintf('<comment>%s</comment> (%s)', $currentMode, self::MODES[$currentMode]['name'])],
            ['To' => sprintf('<info>%s</info> (%s)', $targetMode, self::MODES[$targetMode]['name'])]
        );

        $io->section(sprintf('Features of %s', self::MODES[$targetMode]['name']));
        $io->listing(self::MODES[$targetMode]['features']);

        // Show config changes
        $io->section('Configuration Changes');
        $io->writeln(sprintf('<comment>File:</comment> %s', $configPath));
        $io->newLine();
        $io->writeln('<fg=red>- mode: ' . $currentMode . '</>');
        $io->writeln('<fg=green>+ mode: ' . $targetMode . '</>');

        if ($dryRun) {
            $io->warning('Dry-run mode: No changes were made.');
            return Command::SUCCESS;
        }

        // Confirm unless --force
        if (!$force) {
            if (!$io->confirm('Apply these changes?', true)) {
                $io->note('Operation cancelled.');
                return Command::SUCCESS;
            }
        }

        // Apply changes
        $config['better_auth']['mode'] = $targetMode;

        // Add mode-specific defaults if switching to a specific mode
        if ($targetMode === 'api') {
            // Ensure token config exists for API mode
            if (!isset($config['better_auth']['token'])) {
                $config['better_auth']['token'] = [
                    'lifetime' => 3600,
                    'refresh_lifetime' => 2592000,
                ];
            }
        } elseif ($targetMode === 'session') {
            // Ensure session config exists for session mode
            if (!isset($config['better_auth']['session'])) {
                $config['better_auth']['session'] = [
                    'lifetime' => 604800,
                    'cookie_name' => 'better_auth_session',
                ];
            }
        }

        $yaml = Yaml::dump($config, 4, 2);
        $filesystem->dumpFile($configPath, $yaml);

        $io->success(sprintf(
            'Successfully switched from %s to %s mode!',
            self::MODES[$currentMode]['name'],
            self::MODES[$targetMode]['name']
        ));

        $io->note([
            'Remember to clear the cache: php bin/console cache:clear',
            'Review your security.yaml if you have custom firewall settings.',
        ]);

        return Command::SUCCESS;
    }
}
