<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Command;

use BetterAuth\Core\Interfaces\RefreshTokenRepositoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'better-auth:cleanup:tokens',
    description: 'Clean up expired refresh tokens from the database'
)]
class CleanupTokensCommand extends Command
{
    public function __construct(
        private readonly ?RefreshTokenRepositoryInterface $refreshTokenRepository = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command removes all expired refresh tokens from the database.')
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Show what would be deleted without actually deleting'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($this->refreshTokenRepository === null) {
            $io->error('RefreshTokenRepository is not available. Make sure BetterAuth is properly configured.');

            return Command::FAILURE;
        }

        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Dry-run mode: No tokens will be deleted.');
        }

        $io->info('Cleaning up expired refresh tokens...');

        try {
            $deletedCount = $this->refreshTokenRepository->deleteExpired();

            if ($dryRun) {
                $io->success(sprintf('Would delete %d expired refresh token(s)', $deletedCount));
            } else {
                $io->success(sprintf('Deleted %d expired refresh token(s)', $deletedCount));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to cleanup tokens: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}

