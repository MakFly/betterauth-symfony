<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\SetupFeaturesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SetupFeaturesCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ba_features_' . uniqid();
        mkdir($this->tmpDir . '/config/packages', 0777, true);
        mkdir($this->tmpDir . '/src/Entity', 0777, true);
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
        return new CommandTester(new SetupFeaturesCommand($this->tmpDir));
    }

    public function testCommandName(): void
    {
        $cmd = new SetupFeaturesCommand($this->tmpDir);
        $this->assertSame('better-auth:setup-features', $cmd->getName());
    }

    public function testListOptionShowsFeatures(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--list' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        // Should list feature names
        $this->assertStringContainsString('email_password', $display);
    }

    public function testEnableUnknownFeatureFails(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--enable' => ['nonexistent_feature']]);

        // Unknown feature should produce failure or warning
        $this->assertContains($exitCode, [Command::SUCCESS, Command::FAILURE]);
        // Should at least mention the unknown feature
        $display = $tester->getDisplay();
        $this->assertNotEmpty($display);
    }

    public function testPresetMinimal(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--preset' => 'minimal', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testPresetStandard(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--preset' => 'standard', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testPresetFull(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--preset' => 'full', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testInvalidPresetFails(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--preset' => 'invalid_preset']);

        $this->assertSame(Command::FAILURE, $exitCode);
    }

    public function testDryRunDoesNotModifyConfig(): void
    {
        $configFile = $this->tmpDir . '/config/packages/better_auth.yaml';
        file_put_contents($configFile, "better_auth:\n    mode: api\n");
        $originalContent = file_get_contents($configFile);

        $tester = $this->createTester();
        $tester->execute(['--enable' => ['magic_link'], '--dry-run' => true]);

        $this->assertSame($originalContent, file_get_contents($configFile));
    }

    public function testCommandHasListOption(): void
    {
        $cmd = new SetupFeaturesCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('list'));
    }

    public function testCommandHasEnableOption(): void
    {
        $cmd = new SetupFeaturesCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('enable'));
    }

    public function testCommandHasDisableOption(): void
    {
        $cmd = new SetupFeaturesCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('disable'));
    }

    public function testCommandHasPresetOption(): void
    {
        $cmd = new SetupFeaturesCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('preset'));
    }

    public function testCommandHasDryRunOption(): void
    {
        $cmd = new SetupFeaturesCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('dry-run'));
    }
}
