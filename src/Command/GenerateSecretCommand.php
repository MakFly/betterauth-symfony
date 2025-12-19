<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'better-auth:generate-secret',
    description: 'Generate a secure random secret for BetterAuth'
)]
class GenerateSecretCommand extends Command
{
    private const ENV_VAR_NAME = 'BETTER_AUTH_SECRET';

    public function __construct(
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp(<<<'HELP'
                This command generates a cryptographically secure random secret for BETTER_AUTH_SECRET.

                By default, it displays the secret. Use --write to save it to your .env file:

                  <info>php %command.full_name% --write</info>

                To write to a specific env file (e.g., .env.local):

                  <info>php %command.full_name% --write --env-file=.env.local</info>

                HELP)
            ->addOption(
                'length',
                'l',
                InputOption::VALUE_OPTIONAL,
                'Secret length in bytes (default: 32 = 64 hex characters)',
                32
            )
            ->addOption(
                'format',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Output format: hex (default) or base64',
                'hex'
            )
            ->addOption(
                'write',
                'w',
                InputOption::VALUE_NONE,
                'Write the secret to the .env file (creates or updates BETTER_AUTH_SECRET)'
            )
            ->addOption(
                'env-file',
                null,
                InputOption::VALUE_OPTIONAL,
                'Target env file (default: .env, use .env.local for local overrides)',
                '.env'
            )
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Overwrite existing BETTER_AUTH_SECRET without confirmation'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $length = (int) $input->getOption('length');
        $format = $input->getOption('format');
        $write = $input->getOption('write');
        $envFile = $input->getOption('env-file');
        $force = $input->getOption('force');

        if ($length < 16) {
            $io->error('Length must be at least 16 bytes (32 hex characters) for security.');

            return Command::FAILURE;
        }

        if ($length > 128) {
            $io->error('Length must not exceed 128 bytes.');

            return Command::FAILURE;
        }

        if (!in_array($format, ['hex', 'base64'], true)) {
            $io->error('Format must be either "hex" or "base64".');

            return Command::FAILURE;
        }

        try {
            $randomBytes = random_bytes($length);

            if ($format === 'hex') {
                $secret = bin2hex($randomBytes);
            } else {
                $secret = base64_encode($randomBytes);
            }

            if ($write) {
                return $this->writeToEnvFile($io, $secret, $envFile, $force);
            }

            $io->success('Generated secure secret:');
            $io->writeln('');
            $io->writeln(sprintf('  <fg=cyan>%s</>', $secret));
            $io->writeln('');
            $io->note('Add this to your .env file:');
            $io->writeln(sprintf('  <fg=yellow>%s=%s</>', self::ENV_VAR_NAME, $secret));
            $io->writeln('');
            $io->info('Or use --write to automatically update your .env file');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to generate secret: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }

    private function writeToEnvFile(SymfonyStyle $io, string $secret, string $envFile, bool $force): int
    {
        $envPath = $this->projectDir . '/' . $envFile;
        $envLine = sprintf('%s=%s', self::ENV_VAR_NAME, $secret);

        // Check if file exists
        if (!file_exists($envPath)) {
            // Create the file with the secret
            if (false === file_put_contents($envPath, $envLine . "\n")) {
                $io->error(sprintf('Failed to create %s', $envFile));

                return Command::FAILURE;
            }

            $io->success(sprintf('Created %s with BETTER_AUTH_SECRET', $envFile));

            return Command::SUCCESS;
        }

        // Read existing content
        $content = file_get_contents($envPath);
        if (false === $content) {
            $io->error(sprintf('Failed to read %s', $envFile));

            return Command::FAILURE;
        }

        // Check if BETTER_AUTH_SECRET already exists
        $pattern = '/^' . preg_quote(self::ENV_VAR_NAME, '/') . '=.*/m';

        if (preg_match($pattern, $content)) {
            // Variable exists - ask for confirmation unless --force
            if (!$force) {
                $existingMatch = [];
                preg_match($pattern, $content, $existingMatch);
                $io->warning(sprintf('BETTER_AUTH_SECRET already exists in %s:', $envFile));
                $io->writeln(sprintf('  Current: <fg=yellow>%s</>', $existingMatch[0] ?? ''));
                $io->writeln(sprintf('  New:     <fg=cyan>%s</>', $envLine));

                if (!$io->confirm('Do you want to replace it?', false)) {
                    $io->note('Operation cancelled. Use --force to skip this confirmation.');

                    return Command::SUCCESS;
                }
            }

            // Replace existing value
            $newContent = preg_replace($pattern, $envLine, $content);
            $action = 'Updated';
        } else {
            // Append to file
            $newContent = rtrim($content, "\n") . "\n\n" . $envLine . "\n";
            $action = 'Added';
        }

        if (false === file_put_contents($envPath, $newContent)) {
            $io->error(sprintf('Failed to write to %s', $envFile));

            return Command::FAILURE;
        }

        $io->success(sprintf('%s BETTER_AUTH_SECRET in %s', $action, $envFile));
        $io->writeln('');
        $io->writeln(sprintf('  <fg=cyan>%s</>', $envLine));

        // Security reminder for .env
        if ($envFile === '.env') {
            $io->writeln('');
            $io->note([
                'The .env file is typically committed to version control.',
                'For production secrets, consider using .env.local instead:',
                sprintf('  php bin/console better-auth:generate-secret --write --env-file=.env.local'),
            ]);
        }

        return Command::SUCCESS;
    }
}

