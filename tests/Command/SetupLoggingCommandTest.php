<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\SetupLoggingCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SetupLoggingCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ba_logging_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tmpDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        foreach (array_diff(scandir($dir), ['.', '..']) as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    private function createTester(): CommandTester
    {
        return new CommandTester(new SetupLoggingCommand($this->tmpDir));
    }

    public function testCommandName(): void
    {
        $cmd = new SetupLoggingCommand($this->tmpDir);
        $this->assertSame('better-auth:setup:logging', $cmd->getName());
    }

    public function testDefaultExecutionCreatesMonologConfig(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($this->tmpDir . '/config/packages/monolog.yaml');
    }

    public function testUpdatesExistingMonologConfig(): void
    {
        mkdir($this->tmpDir . '/config/packages', 0777, true);
        file_put_contents($this->tmpDir . '/config/packages/monolog.yaml', "monolog:\n    handlers: []\n");

        $tester = $this->createTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testInvalidLogLevelFails(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--level' => 'invalid_level']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid log level', $tester->getDisplay());
    }

    public function testValidLogLevels(): void
    {
        $validLevels = ['debug', 'info', 'warning', 'error', 'critical'];

        foreach ($validLevels as $level) {
            $tester = $this->createTester();
            $exitCode = $tester->execute(['--level' => $level]);
            $this->assertSame(Command::SUCCESS, $exitCode, "Level '$level' should be valid");

            // Cleanup for next iteration
            if (file_exists($this->tmpDir . '/config/packages/monolog.yaml')) {
                unlink($this->tmpDir . '/config/packages/monolog.yaml');
            }
        }
    }

    public function testCommandHasChannelOption(): void
    {
        $cmd = new SetupLoggingCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('channel'));
    }

    public function testCommandHasLevelOption(): void
    {
        $cmd = new SetupLoggingCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('level'));
    }

    public function testCommandHasPathOption(): void
    {
        $cmd = new SetupLoggingCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('path'));
    }
}
