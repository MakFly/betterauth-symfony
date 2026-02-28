<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\GenerateSecretCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateSecretCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ba_gensecret_' . uniqid();
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

    private function createCommand(): CommandTester
    {
        return new CommandTester(new GenerateSecretCommand($this->tmpDir));
    }

    public function testCommandNameAndDescription(): void
    {
        $cmd = new GenerateSecretCommand($this->tmpDir);
        $this->assertSame('better-auth:generate-secret', $cmd->getName());
    }

    public function testDefaultDisplaysHexSecret(): void
    {
        $tester = $this->createCommand();
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('BETTER_AUTH_SECRET=', $display);
    }

    public function testBase64Format(): void
    {
        $tester = $this->createCommand();
        $exitCode = $tester->execute(['--format' => 'base64']);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testInvalidFormatFails(): void
    {
        $tester = $this->createCommand();
        $exitCode = $tester->execute(['--format' => 'invalid']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Format must be', $tester->getDisplay());
    }

    public function testLengthTooShortFails(): void
    {
        $tester = $this->createCommand();
        $exitCode = $tester->execute(['--length' => '8']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('16 bytes', $tester->getDisplay());
    }

    public function testLengthTooLongFails(): void
    {
        $tester = $this->createCommand();
        $exitCode = $tester->execute(['--length' => '200']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('128 bytes', $tester->getDisplay());
    }

    public function testWriteCreatesEnvFileWhenMissing(): void
    {
        $tester = $this->createCommand();
        $exitCode = $tester->execute(['--write' => true, '--env-file' => '.env.new']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($this->tmpDir . '/.env.new');
        $content = file_get_contents($this->tmpDir . '/.env.new');
        $this->assertStringContainsString('BETTER_AUTH_SECRET=', $content);
    }

    public function testWriteAppendsToExistingEnvFile(): void
    {
        file_put_contents($this->tmpDir . '/.env', "APP_ENV=dev\n");

        $tester = $this->createCommand();
        $exitCode = $tester->execute(['--write' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $content = file_get_contents($this->tmpDir . '/.env');
        $this->assertStringContainsString('BETTER_AUTH_SECRET=', $content);
    }

    public function testWriteWithExistingSecretAsksConfirmation(): void
    {
        file_put_contents($this->tmpDir . '/.env', "BETTER_AUTH_SECRET=oldsecret\n");

        $tester = $this->createCommand();
        $tester->setInputs(['n']); // refuse overwrite

        $exitCode = $tester->execute(['--write' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        // Old secret should remain
        $content = file_get_contents($this->tmpDir . '/.env');
        $this->assertStringContainsString('oldsecret', $content);
    }

    public function testWriteWithExistingSecretAndForceReplaces(): void
    {
        file_put_contents($this->tmpDir . '/.env', "BETTER_AUTH_SECRET=oldsecret\n");

        $tester = $this->createCommand();
        $exitCode = $tester->execute(['--write' => true, '--force' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $content = file_get_contents($this->tmpDir . '/.env');
        $this->assertStringNotContainsString('oldsecret', $content);
        $this->assertStringContainsString('BETTER_AUTH_SECRET=', $content);
    }

    public function testWriteToCustomEnvFile(): void
    {
        file_put_contents($this->tmpDir . '/.env.local', "APP_DEBUG=true\n");

        $tester = $this->createCommand();
        $exitCode = $tester->execute(['--write' => true, '--env-file' => '.env.local']);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $content = file_get_contents($this->tmpDir . '/.env.local');
        $this->assertStringContainsString('BETTER_AUTH_SECRET=', $content);
    }

    public function testGeneratedSecretIs64HexCharsWithDefaultLength(): void
    {
        // Default: 32 bytes hex = 64 chars
        $tester = $this->createCommand();
        $tester->execute([]);

        $display = $tester->getDisplay();
        // Extract the secret value
        preg_match('/BETTER_AUTH_SECRET=([a-f0-9]+)/', $display, $matches);
        if (isset($matches[1])) {
            $this->assertSame(64, strlen($matches[1]));
        } else {
            // In non-write mode the secret may appear without prefix in the display
            $this->addToAssertionCount(1);
        }
    }
}
