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
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'better-auth:config:update',
    description: 'Update BetterAuth configuration files'
)]
class UpdateConfigCommand extends Command
{
    private string $projectDir;

    public function __construct(string $projectDir)
    {
        parent::__construct();
        $this->projectDir = $projectDir;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('config-type', InputArgument::OPTIONAL, 'Config type: all|security|better_auth|monolog|services', 'all')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show changes without applying them')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Overwrite existing configuration')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command updates BetterAuth configuration files.

                    It can update:
                      - <comment>security</comment>:     config/packages/security.yaml
                      - <comment>better_auth</comment>:  config/packages/better_auth.yaml
                      - <comment>monolog</comment>:      config/packages/monolog.yaml
                      - <comment>services</comment>:     config/services.yaml
                      - <comment>all</comment>:          All of the above

                    Usage:
                      <info>php %command.full_name%</info>                    # Update all configs
                      <info>php %command.full_name% security</info>           # Update only security.yaml
                      <info>php %command.full_name% --dry-run</info>          # Preview changes
                      <info>php %command.full_name% --force</info>            # Overwrite existing configs

                    Examples:
                      <info>php %command.full_name%</info>
                      <info>php %command.full_name% better_auth --force</info>
                      <info>php %command.full_name% --dry-run</info>
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $configType = $input->getArgument('config-type');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('BetterAuth Configuration Update');

        if ($dryRun) {
            $io->warning('DRY RUN MODE - No files will be modified');
        }

        $configs = $this->getConfigsToUpdate($configType);
        if (empty($configs)) {
            $io->error("Unknown config type: {$configType}. Valid types: all, security, better_auth, monolog, services");

            return Command::FAILURE;
        }

        $io->section('Configuration Files to Update');
        $io->listing(array_map(fn ($c) => $c['path'], $configs));

        foreach ($configs as $config) {
            $this->updateConfig($io, $config, $dryRun, $force);
        }

        if (!$dryRun) {
            $io->success('Configuration files updated successfully!');

            $io->note([
                'Remember to:',
                '1. Clear cache: php bin/console cache:clear',
                '2. Review the updated configurations',
                '3. Adjust settings for your environment',
            ]);
        } else {
            $io->info('Dry run completed. Use without --dry-run to apply changes.');
        }

        return Command::SUCCESS;
    }

    private function getConfigsToUpdate(string $type): array
    {
        $all = [
            [
                'name' => 'security',
                'path' => 'config/packages/security.yaml',
                'generator' => fn () => $this->generateSecurityConfig(),
            ],
            [
                'name' => 'better_auth',
                'path' => 'config/packages/better_auth.yaml',
                'generator' => fn () => $this->generateBetterAuthConfig(),
            ],
            [
                'name' => 'monolog',
                'path' => 'config/packages/monolog.yaml',
                'generator' => fn () => $this->generateMonologConfig(),
            ],
            [
                'name' => 'services',
                'path' => 'config/services.yaml',
                'generator' => fn () => $this->generateServicesConfig(),
            ],
        ];

        if ($type === 'all') {
            return $all;
        }

        return array_filter($all, fn ($c) => $c['name'] === $type);
    }

    private function updateConfig(SymfonyStyle $io, array $config, bool $dryRun, bool $force): void
    {
        $io->section($config['name'] . '.yaml');

        $fullPath = $this->projectDir . '/' . $config['path'];
        $exists = file_exists($fullPath);

        if ($exists && !$force) {
            $io->note("File exists: {$config['path']} (use --force to overwrite)");

            return;
        }

        $content = ($config['generator'])();
        $yaml = Yaml::dump($content, 6, 2);

        if ($dryRun) {
            $io->writeln('Would write:');
            $io->block($yaml, null, 'fg=cyan', ' ', true);

            return;
        }

        // Create directory if needed
        $dir = dirname($fullPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        file_put_contents($fullPath, $yaml);
        $io->writeln('✅ ' . ($exists ? 'Updated' : 'Created') . ": {$config['path']}");
    }

    private function generateSecurityConfig(): array
    {
        return [
            'security' => [
                'password_hashers' => [
                    'Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface' => 'auto',
                ],
                'providers' => [
                    'better_auth' => [
                        'id' => 'BetterAuth\Symfony\Security\BetterAuthUserProvider',
                    ],
                ],
                'firewalls' => [
                    'dev' => [
                        'pattern' => '^/(_(profiler|wdt)|css|images|js)/',
                        'security' => false,
                    ],
                    'api' => [
                        'pattern' => '^/api',
                        'stateless' => true,
                        'custom_authenticators' => [
                            'BetterAuth\Symfony\Security\BetterAuthAuthenticator',
                        ],
                    ],
                    'main' => [
                        'lazy' => true,
                        'provider' => 'better_auth',
                        'custom_authenticators' => [
                            'BetterAuth\Symfony\Security\BetterAuthAuthenticator',
                        ],
                        'logout' => [
                            'path' => 'app_logout',
                        ],
                    ],
                ],
                'access_control' => [
                    ['path' => '^/api/auth', 'roles' => 'PUBLIC_ACCESS'],
                    ['path' => '^/api', 'roles' => 'ROLE_USER'],
                ],
            ],
        ];
    }

    private function generateBetterAuthConfig(): array
    {
        return [
            'better_auth' => [
                'secret' => '%env(BETTER_AUTH_SECRET)%',
                'session' => [
                    'lifetime' => 604800,
                    'cookie_name' => 'better_auth_session',
                ],
                'api' => [
                    'enabled' => true,
                    'access_token_lifetime' => 3600,
                    'refresh_token_lifetime' => 2592000,
                ],
                'oauth' => [
                    'providers' => [
                        'google' => [
                            'enabled' => '%env(bool:OAUTH_GOOGLE_ENABLED)%',
                            'client_id' => '%env(OAUTH_GOOGLE_CLIENT_ID)%',
                            'client_secret' => '%env(OAUTH_GOOGLE_CLIENT_SECRET)%',
                        ],
                        'github' => [
                            'enabled' => '%env(bool:OAUTH_GITHUB_ENABLED)%',
                            'client_id' => '%env(OAUTH_GITHUB_CLIENT_ID)%',
                            'client_secret' => '%env(OAUTH_GITHUB_CLIENT_SECRET)%',
                        ],
                    ],
                ],
                'multi_tenant' => [
                    'enabled' => true,
                    'default_role' => 'member',
                ],
                'logging' => [
                    'enabled' => true,
                    'channel' => 'betterauth',
                ],
            ],
        ];
    }

    private function generateMonologConfig(): array
    {
        return [
            'monolog' => [
                'channels' => ['betterauth'],
            ],
            'when@dev' => [
                'monolog' => [
                    'handlers' => [
                        'betterauth_file' => [
                            'type' => 'stream',
                            'path' => '%kernel.logs_dir%/betterauth.log',
                            'level' => 'debug',
                            'channels' => ['betterauth'],
                        ],
                        'betterauth_console' => [
                            'type' => 'console',
                            'process_psr_3_messages' => false,
                            'channels' => ['betterauth'],
                        ],
                    ],
                ],
            ],
            'when@prod' => [
                'monolog' => [
                    'handlers' => [
                        'betterauth_file' => [
                            'type' => 'stream',
                            'path' => '%kernel.logs_dir%/betterauth.log',
                            'level' => 'info',
                            'channels' => ['betterauth'],
                        ],
                    ],
                ],
            ],
            'when@test' => [
                'monolog' => [
                    'handlers' => [
                        'betterauth_file' => [
                            'type' => 'stream',
                            'path' => '%kernel.logs_dir%/betterauth.log',
                            'level' => 'error',
                            'channels' => ['betterauth'],
                        ],
                    ],
                ],
            ],
        ];
    }

    private function generateServicesConfig(): array
    {
        return [
            'services' => [
                '_defaults' => [
                    'autowire' => true,
                    'autoconfigure' => true,
                ],
                'BetterAuth\\' => [
                    'resource' => '../vendor/betterauth/symfony-bundle/src/*',
                    'exclude' => '../vendor/betterauth/symfony-bundle/src/{DependencyInjection,Entity,Tests,Kernel.php}',
                ],
                'BetterAuth\Symfony\Command\SetupLoggingCommand' => [
                    'arguments' => ['$projectDir' => '%kernel.project_dir%'],
                    'tags' => ['console.command'],
                ],
                'BetterAuth\Symfony\Command\SetupDependenciesCommand' => [
                    'arguments' => ['$projectDir' => '%kernel.project_dir%'],
                    'tags' => ['console.command'],
                ],
                'BetterAuth\Symfony\Command\UpdateConfigCommand' => [
                    'arguments' => ['$projectDir' => '%kernel.project_dir%'],
                    'tags' => ['console.command'],
                ],
            ],
        ];
    }
}
