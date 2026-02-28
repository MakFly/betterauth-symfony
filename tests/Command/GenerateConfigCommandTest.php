<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\GenerateConfigCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class GenerateConfigCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ba_genconf_' . uniqid();
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
        return new CommandTester(new GenerateConfigCommand($this->tmpDir));
    }

    public function testCommandName(): void
    {
        $cmd = new GenerateConfigCommand($this->tmpDir);
        $this->assertSame('better-auth:generate-config', $cmd->getName());
    }

    public function testGeneratesMinimalPreset(): void
    {
        $outputPath = $this->tmpDir . '/config/packages/better_auth_test.yaml';

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--preset' => 'minimal',
            '--output' => $outputPath,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($outputPath);
    }

    public function testGeneratesStandardPreset(): void
    {
        $outputPath = $this->tmpDir . '/config/packages/better_auth_std.yaml';

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--preset' => 'standard',
            '--output' => $outputPath,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($outputPath);
    }

    public function testGeneratesEnterprisePreset(): void
    {
        $outputPath = $this->tmpDir . '/config/packages/better_auth_ent.yaml';

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--preset' => 'enterprise',
            '--output' => $outputPath,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertFileExists($outputPath);
    }

    public function testInvalidPresetFails(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--preset' => 'invalid']);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Invalid preset', $tester->getDisplay());
    }

    public function testSkipsExistingFileWithoutForce(): void
    {
        $outputPath = $this->tmpDir . '/config/packages/better_auth.yaml';
        file_put_contents($outputPath, 'existing: config');

        $tester = $this->createTester();
        $tester->setInputs(['n']); // refuse overwrite

        $tester->execute(['--preset' => 'minimal', '--output' => $outputPath]);

        $content = file_get_contents($outputPath);
        $this->assertSame('existing: config', $content);
    }

    public function testForceOverwritesExistingFile(): void
    {
        $outputPath = $this->tmpDir . '/config/packages/better_auth.yaml';
        file_put_contents($outputPath, 'existing: config');

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--preset' => 'minimal',
            '--output' => $outputPath,
            '--force' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $content = file_get_contents($outputPath);
        $this->assertNotSame('existing: config', $content);
    }

    public function testWithCommentsOption(): void
    {
        $outputPath = $this->tmpDir . '/config/packages/better_auth_comments.yaml';

        $tester = $this->createTester();
        $exitCode = $tester->execute([
            '--preset' => 'standard',
            '--output' => $outputPath,
            '--with-comments' => true,
        ]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testCommandHasPresetOption(): void
    {
        $cmd = new GenerateConfigCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('preset'));
    }

    public function testCommandHasForceOption(): void
    {
        $cmd = new GenerateConfigCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('force'));
    }
}
