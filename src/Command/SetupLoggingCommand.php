<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;

#[AsCommand(
    name: 'better-auth:setup:logging',
    description: 'Configure Monolog for BetterAuth logging'
)]
class SetupLoggingCommand extends Command
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
            ->addOption('channel', 'c', InputOption::VALUE_OPTIONAL, 'Log channel name', 'betterauth')
            ->addOption('level', 'l', InputOption::VALUE_OPTIONAL, 'Log level', 'info')
            ->addOption('path', 'p', InputOption::VALUE_OPTIONAL, 'Log file path', '%kernel.logs_dir%/betterauth.log')
            ->setHelp(
                <<<'HELP'
                    The <info>%command.name%</info> command configures Monolog for BetterAuth logging.

                    It will create or update config/packages/monolog.yaml with BetterAuth-specific channels.

                    Usage:
                      <info>php %command.full_name%</info>

                    Options:
                      <info>--channel</info>  Log channel name (default: betterauth)
                      <info>--level</info>    Log level: debug|info|notice|warning|error (default: info)
                      <info>--path</info>     Log file path (default: %kernel.logs_dir%/betterauth.log)

                    Examples:
                      <info>php %command.full_name%</info>
                      <info>php %command.full_name% --level=debug</info>
                      <info>php %command.full_name% --channel=auth --path=/var/log/auth.log</info>
                    HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $channel = $input->getOption('channel');
        $level = $input->getOption('level');
        $path = $input->getOption('path');

        $io->title('BetterAuth Logging Setup');

        // Validate log level
        $validLevels = ['debug', 'info', 'notice', 'warning', 'error', 'critical', 'alert', 'emergency'];
        if (!in_array($level, $validLevels, true)) {
            $io->error("Invalid log level: {$level}. Valid levels: " . implode(', ', $validLevels));

            return Command::FAILURE;
        }

        $configPath = $this->projectDir . '/config/packages/monolog.yaml';
        $configDir = dirname($configPath);

        // Create config directory if it doesn't exist
        if (!is_dir($configDir)) {
            if (!mkdir($configDir, 0o755, true) && !is_dir($configDir)) {
                $io->error("Failed to create directory: {$configDir}");

                return Command::FAILURE;
            }
        }

        // Load existing config or create new
        $config = [];
        if (file_exists($configPath)) {
            $config = Yaml::parseFile($configPath);
            $io->info('Updating existing monolog.yaml configuration...');
        } else {
            $io->info('Creating new monolog.yaml configuration...');
        }

        // Add BetterAuth logging configuration
        $config = $this->addBetterAuthLogging($config, $channel, $level, $path);

        // Write configuration
        $yaml = Yaml::dump($config, 4, 2);
        file_put_contents($configPath, $yaml);

        $io->success('Monolog configuration updated successfully!');

        // Show configuration summary
        $io->section('Configuration Summary');
        $io->table(
            ['Setting', 'Value'],
            [
                ['Channel', $channel],
                ['Level', strtoupper($level)],
                ['File Path', $path],
                ['Config File', 'config/packages/monolog.yaml'],
            ]
        );

        // Show usage examples
        $io->section('Usage in Your Code');
        $io->writeln([
            'Use BetterAuth logger in your services:',
            '',
            '<info>use Psr\Log\LoggerInterface;</info>',
            '',
            '<info>class YourService</info>',
            '<info>{</info>',
            '    <info>public function __construct(</info>',
            "        <info>#[Autowire(service: 'monolog.logger.{$channel}')] private LoggerInterface \$logger</info>",
            '    <info>) {}</info>',
            '',
            '    <info>public function someMethod(): void</info>',
            '    <info>{</info>',
            "        <info>\$this->logger->info('Authentication successful', ['user_id' => \$userId]);</info>",
            '    <info>}</info>',
            '<info>}</info>',
        ]);

        $io->note([
            'BetterAuth will now log authentication events to: ' . $path,
            'You can view logs in real-time with: tail -f var/log/betterauth.log',
        ]);

        return Command::SUCCESS;
    }

    private function addBetterAuthLogging(array $config, string $channel, string $level, string $path): array
    {
        // Initialize monolog config if not exists
        if (!isset($config['monolog'])) {
            $config['monolog'] = [];
        }

        // Add channels
        if (!isset($config['monolog']['channels'])) {
            $config['monolog']['channels'] = [];
        }

        if (!in_array($channel, $config['monolog']['channels'], true)) {
            $config['monolog']['channels'][] = $channel;
        }

        // Configure handlers per environment
        foreach (['dev', 'prod', 'test'] as $env) {
            $envKey = "when@{$env}";

            if (!isset($config[$envKey])) {
                $config[$envKey] = ['monolog' => ['handlers' => []]];
            }

            if (!isset($config[$envKey]['monolog'])) {
                $config[$envKey]['monolog'] = ['handlers' => []];
            }

            if (!isset($config[$envKey]['monolog']['handlers'])) {
                $config[$envKey]['monolog']['handlers'] = [];
            }

            // Add BetterAuth handler
            $handlerName = "{$channel}_file";
            $config[$envKey]['monolog']['handlers'][$handlerName] = [
                'type' => 'stream',
                'path' => $path,
                'level' => $level,
                'channels' => [$channel],
            ];

            // In dev, also add a console handler for better visibility
            if ($env === 'dev') {
                $consoleHandler = "{$channel}_console";
                $config[$envKey]['monolog']['handlers'][$consoleHandler] = [
                    'type' => 'console',
                    'process_psr_3_messages' => false,
                    'channels' => [$channel],
                ];
            }
        }

        return $config;
    }
}
