<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\ConfigureCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ConfigureCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ba_configure_' . uniqid();
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
        return new CommandTester(new ConfigureCommand($this->tmpDir));
    }

    public function testCommandName(): void
    {
        $cmd = new ConfigureCommand($this->tmpDir);
        $this->assertSame('better-auth:configure', $cmd->getName());
    }

    public function testCommandHasSectionOption(): void
    {
        $cmd = new ConfigureCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('section'));
    }

    public function testCreatesConfigFromScratchWhenMissing(): void
    {
        $tester = $this->createTester();

        // Non-interactive with section to avoid full wizard
        $exitCode = $tester->execute(
            ['--section' => 'mode'],
            ['interactive' => false]
        );

        // Should succeed and create config
        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($this->tmpDir . '/config/packages/better_auth.yaml');
    }

    public function testLoadsExistingConfigFile(): void
    {
        file_put_contents(
            $this->tmpDir . '/config/packages/better_auth.yaml',
            "better_auth:\n    mode: session\n    secret: '%env(BETTER_AUTH_SECRET)%'\n"
        );

        $tester = $this->createTester();
        $exitCode = $tester->execute(
            ['--section' => 'mode'],
            ['interactive' => false]
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testSavesConfigAfterExecution(): void
    {
        $tester = $this->createTester();
        $tester->execute(
            ['--section' => 'mode'],
            ['interactive' => false]
        );

        $this->assertFileExists($this->tmpDir . '/config/packages/better_auth.yaml');
        $content = file_get_contents($this->tmpDir . '/config/packages/better_auth.yaml');
        $this->assertStringContainsString('better_auth', $content);
    }

    public function testConfigureSectionTokens(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(
            ['--section' => 'tokens'],
            ['interactive' => false]
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testConfigureSection2fa(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(
            ['--section' => '2fa'],
            ['interactive' => false]
        );

        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
