<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\UpdateConfigCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class UpdateConfigCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ba_updconf_' . uniqid();
        mkdir($this->tmpDir . '/config/packages', 0777, true);
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
        return new CommandTester(new UpdateConfigCommand($this->tmpDir));
    }

    public function testCommandName(): void
    {
        $cmd = new UpdateConfigCommand($this->tmpDir);
        $this->assertSame('better-auth:config:update', $cmd->getName());
    }

    public function testInvalidConfigTypeFails(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['config-type' => 'invalid_type']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Unknown config type', $tester->getDisplay());
    }

    public function testDefaultUpdatesAllConfigsSuccessfully(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testDryRunDoesNotCreateFiles(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('dry', strtolower($display));
    }

    public function testUpdateSecurityConfigType(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['config-type' => 'security']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testUpdateBetterAuthConfigType(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['config-type' => 'better_auth']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testUpdateMonologConfigType(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['config-type' => 'monolog']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testUpdateServicesConfigType(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['config-type' => 'services']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testCommandHasDryRunOption(): void
    {
        $cmd = new UpdateConfigCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('dry-run'));
    }

    public function testCommandHasForceOption(): void
    {
        $cmd = new UpdateConfigCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('force'));
    }

    public function testCommandHasConfigTypeArgument(): void
    {
        $cmd = new UpdateConfigCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasArgument('config-type'));
    }
}
