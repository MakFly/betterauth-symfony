<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\SwitchModeCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SwitchModeCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ba_switchmode_' . uniqid();
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

    private function createConfigFile(string $mode = 'api'): void
    {
        file_put_contents(
            $this->tmpDir . '/config/packages/better_auth.yaml',
            "better_auth:\n    mode: '$mode'\n"
        );
    }

    private function createTester(): CommandTester
    {
        return new CommandTester(new SwitchModeCommand($this->tmpDir));
    }

    public function testCommandName(): void
    {
        $cmd = new SwitchModeCommand($this->tmpDir);
        $this->assertSame('better-auth:switch-mode', $cmd->getName());
    }

    public function testFailsWhenNoConfigFile(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['mode' => 'session']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testAlreadyInTargetMode(): void
    {
        $this->createConfigFile('api');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['mode' => 'api']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('already', strtolower($tester->getDisplay()));
    }

    public function testInvalidModeArgument(): void
    {
        $this->createConfigFile('api');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['mode' => 'invalid']);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function testDryRunDoesNotModifyFile(): void
    {
        $this->createConfigFile('api');
        $originalContent = file_get_contents($this->tmpDir . '/config/packages/better_auth.yaml');

        $tester = $this->createTester();
        $tester->setInputs(['y']); // confirm
        $exitCode = $tester->execute(['mode' => 'session', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertSame($originalContent, file_get_contents($this->tmpDir . '/config/packages/better_auth.yaml'));
        $this->assertStringContainsString('dry', strtolower($tester->getDisplay()));
    }

    public function testSwitchModeWithForce(): void
    {
        $this->createConfigFile('api');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['mode' => 'session', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $content = file_get_contents($this->tmpDir . '/config/packages/better_auth.yaml');
        $this->assertStringContainsString('session', $content);
    }

    public function testSwitchModeToHybridWithForce(): void
    {
        $this->createConfigFile('api');

        $tester = $this->createTester();
        $exitCode = $tester->execute(['mode' => 'hybrid', '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $content = file_get_contents($this->tmpDir . '/config/packages/better_auth.yaml');
        $this->assertStringContainsString('hybrid', $content);
    }

    public function testCommandHasModeArgument(): void
    {
        $cmd = new SwitchModeCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasArgument('mode'));
    }

    public function testCommandHasDryRunOption(): void
    {
        $cmd = new SwitchModeCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('dry-run'));
    }

    public function testCommandHasForceOption(): void
    {
        $cmd = new SwitchModeCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('force'));
    }
}
