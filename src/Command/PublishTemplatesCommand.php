<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

#[AsCommand(
    name: 'better-auth:publish-templates',
    description: 'Publish email templates to your project for customization'
)]
class PublishTemplatesCommand extends Command
{
    private const TEMPLATES = [
        'magic_link.html.twig',
        'email_verification.html.twig',
        'password_reset.html.twig',
        'two_factor_code.html.twig',
    ];

    public function __construct(
        private readonly string $projectDir
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->setHelp('This command copies email templates from the bundle to your project for customization.')
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Overwrite existing templates'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filesystem = new Filesystem();
        $force = $input->getOption('force');

        $bundleTemplatesDir = $this->projectDir . '/vendor/betterauth/symfony-bundle/src/Resources/views/emails';
        $targetDir = $this->projectDir . '/templates/emails/betterauth';

        if (!is_dir($bundleTemplatesDir)) {
            $io->error('Bundle templates directory not found. Make sure BetterAuth bundle is installed.');

            return Command::FAILURE;
        }

        $io->info('Publishing email templates...');

        $filesystem->mkdir($targetDir, 0755);

        $published = 0;
        $skipped = 0;

        foreach (self::TEMPLATES as $template) {
            $source = $bundleTemplatesDir . '/' . $template;
            $target = $targetDir . '/' . $template;

            if (!file_exists($source)) {
                $io->warning(sprintf('Template not found: %s', $template));
                continue;
            }

            if (file_exists($target) && !$force) {
                $io->comment(sprintf('Skipped (already exists): %s (use --force to overwrite)', $template));
                $skipped++;
                continue;
            }

            try {
                $filesystem->copy($source, $target, $force);
                $io->text(sprintf('  <fg=green>✓</> Published: %s', $template));
                $published++;
            } catch (\Exception $e) {
                $io->error(sprintf('Failed to copy %s: %s', $template, $e->getMessage()));

                return Command::FAILURE;
            }
        }

        if ($published > 0) {
            $io->success(sprintf('Published %d template(s) to %s', $published, $targetDir));
        }

        if ($skipped > 0) {
            $io->note(sprintf('Skipped %d template(s) (already exist). Use --force to overwrite.', $skipped));
        }

        if ($published === 0 && $skipped === 0) {
            $io->warning('No templates were published.');

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}

