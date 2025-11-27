<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Command;

use BetterAuth\Core\SessionService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'better-auth:cleanup:sessions',
    description: 'Clean up expired sessions from the database'
)]
class CleanupSessionsCommand extends Command
{
    public function __construct(
        private readonly ?SessionService $sessionService = null
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command removes all expired sessions from the database.')
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

        if ($this->sessionService === null) {
            $io->error('SessionService is not available. Make sure BetterAuth is properly configured.');

            return Command::FAILURE;
        }

        $dryRun = $input->getOption('dry-run');

        if ($dryRun) {
            $io->note('Dry-run mode: No sessions will be deleted.');
        }

        $io->info('Cleaning up expired sessions...');

        try {
            $deletedCount = $this->sessionService->cleanupExpired();

            if ($dryRun) {
                $io->success(sprintf('Would delete %d expired session(s)', $deletedCount));
            } else {
                $io->success(sprintf('Deleted %d expired session(s)', $deletedCount));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error(sprintf('Failed to cleanup sessions: %s', $e->getMessage()));

            return Command::FAILURE;
        }
    }
}

