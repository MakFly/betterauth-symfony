<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'better-auth:setup:dependencies',
    description: 'Install and configure required dependencies for BetterAuth'
)]
class SetupDependenciesCommand extends Command
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
            ->addOption('skip-install', null, InputOption::VALUE_NONE, 'Skip composer install')
            ->addOption('with-dev', null, InputOption::VALUE_NONE, 'Include dev dependencies')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command installs and configures all required dependencies for BetterAuth.

                    It will:
                      - Check and install required Composer packages
                      - Configure Monolog logging
                      - Update config/packages files
                      - Create necessary directories

                    Usage:
                      <info>php %command.full_name%</info>

                    Options:
                      <info>--skip-install</info>  Skip composer install (only show what would be installed)
                      <info>--with-dev</info>      Include dev dependencies

                    Examples:
                      <info>php %command.full_name%</info>
                      <info>php %command.full_name% --skip-install</info>
                      <info>php %command.full_name% --with-dev</info>
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $skipInstall = $input->getOption('skip-install');
        $withDev = $input->getOption('with-dev');

        $io->title('BetterAuth Dependencies Setup');

        // Required packages
        $requiredPackages = [
            'symfony/security-bundle' => '^6.0|^7.0',
            'symfony/validator' => '^6.0|^7.0',
            'symfony/monolog-bundle' => '^3.0',
            'doctrine/doctrine-bundle' => '^2.11',
            'doctrine/orm' => '^3.0',
        ];

        // Dev packages
        $devPackages = [
            'symfony/maker-bundle' => '^1.0',
            'symfony/debug-bundle' => '^6.0|^7.0',
        ];

        // Check installed packages
        $composerPath = $this->projectDir . '/composer.json';
        if (!file_exists($composerPath)) {
            $io->error('composer.json not found. Please run this command from your Symfony project root.');

            return Command::FAILURE;
        }

        $composer = json_decode(file_get_contents($composerPath), true);
        $installed = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        // Determine packages to install
        $toInstall = [];
        $toInstallDev = [];

        foreach ($requiredPackages as $package => $version) {
            if (!isset($installed[$package])) {
                $toInstall[$package] = $version;
            }
        }

        if ($withDev) {
            foreach ($devPackages as $package => $version) {
                if (!isset($installed[$package])) {
                    $toInstallDev[$package] = $version;
                }
            }
        }

        // Show packages status
        $io->section('Package Status');
        $this->showPackageStatus($io, $requiredPackages, $installed, 'Required Packages');

        if ($withDev) {
            $this->showPackageStatus($io, $devPackages, $installed, 'Dev Packages');
        }

        // Install missing packages
        if (!empty($toInstall) || !empty($toInstallDev)) {
            if ($skipInstall) {
                $io->warning('Skipping installation (--skip-install flag)');
                $io->note('Run "composer require ' . implode(' ', array_keys($toInstall)) . '" to install');

                return Command::SUCCESS;
            }

            $io->section('Installing Packages');

            if (!empty($toInstall)) {
                if (!$this->installPackages($io, $toInstall, false)) {
                    return Command::FAILURE;
                }
            }

            if (!empty($toInstallDev)) {
                if (!$this->installPackages($io, $toInstallDev, true)) {
                    return Command::FAILURE;
                }
            }
        } else {
            $io->success('All required packages are already installed!');
        }

        // Configure services
        $io->section('Configuring Services');
        $this->configureServices($io);

        // Create directories
        $io->section('Creating Directories');
        $this->createDirectories($io);

        $io->success('BetterAuth dependencies setup completed successfully!');

        $io->note([
            'Next steps:',
            '1. Run: php bin/console better-auth:setup:logging',
            '2. Run: php bin/console doctrine:migrations:diff',
            '3. Run: php bin/console doctrine:migrations:migrate',
        ]);

        return Command::SUCCESS;
    }

    private function showPackageStatus(SymfonyStyle $io, array $packages, array $installed, string $title): void
    {
        $rows = [];
        foreach ($packages as $package => $version) {
            $status = isset($installed[$package]) ? '✅ Installed' : '❌ Missing';
            $currentVersion = $installed[$package] ?? '-';
            $rows[] = [$package, $version, $currentVersion, $status];
        }

        $io->table(['Package', 'Required', 'Current', 'Status'], $rows);
    }

    private function installPackages(SymfonyStyle $io, array $packages, bool $dev = false): bool
    {
        $packagesStr = implode(' ', array_map(
            fn ($pkg, $ver) => "{$pkg}:{$ver}",
            array_keys($packages),
            array_values($packages)
        ));

        $cmd = ['composer', 'require'];
        if ($dev) {
            $cmd[] = '--dev';
        }
        $cmd = array_merge($cmd, array_keys($packages));

        $io->writeln('Running: ' . implode(' ', $cmd));

        $process = new Process($cmd, $this->projectDir, null, null, 300);
        $process->run(function ($type, $buffer) use ($io) {
            $io->write($buffer);
        });

        if (!$process->isSuccessful()) {
            $io->error('Failed to install packages');

            return false;
        }

        $io->success('Packages installed successfully');

        return true;
    }

    private function configureServices(SymfonyStyle $io): void
    {
        $servicesPath = $this->projectDir . '/config/services.yaml';

        if (!file_exists($servicesPath)) {
            $io->warning('config/services.yaml not found, skipping service configuration');

            return;
        }

        $services = Yaml::parseFile($servicesPath);

        // Add BetterAuth service configuration if not exists
        if (!isset($services['services']['BetterAuth\\'])) {
            $services['services']['BetterAuth\\'] = [
                'resource' => '../vendor/betterauth/symfony-bundle/src/*',
                'exclude' => '../vendor/betterauth/symfony-bundle/src/{DependencyInjection,Entity,Tests,Kernel.php}',
            ];

            file_put_contents($servicesPath, Yaml::dump($services, 4, 2));
            $io->writeln('✅ Updated services.yaml');
        } else {
            $io->writeln('✅ services.yaml already configured');
        }
    }

    private function createDirectories(SymfonyStyle $io): void
    {
        $directories = [
            'var/log',
            'var/cache/betterauth',
            'config/packages',
        ];

        foreach ($directories as $dir) {
            $path = $this->projectDir . '/' . $dir;
            if (!is_dir($path)) {
                mkdir($path, 0o755, true);
                $io->writeln("✅ Created {$dir}");
            } else {
                $io->writeln("✅ {$dir} exists");
            }
        }
    }
}
