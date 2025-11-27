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
    protected function configure(): void
    {
        $this
            ->setHelp('This command generates a cryptographically secure random secret (64 characters) for BETTER_AUTH_SECRET.')
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $length = (int) $input->getOption('length');
        $format = $input->getOption('format');

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

            $io->success('Generated secure secret:');
            $io->writeln('');
            $io->writeln(sprintf('  <fg=cyan>%s</>', $secret));
            $io->writeln('');
            $io->note('Add this to your .env file:');
            $io->writeln(sprintf('  <fg=yellow>BETTER_AUTH_SECRET=%s</>', $secret));

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to generate secret: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}

