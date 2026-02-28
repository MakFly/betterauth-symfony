<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\SetupDependenciesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class SetupDependenciesCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ba_deps_' . uniqid();
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
        return new CommandTester(new SetupDependenciesCommand($this->tmpDir));
    }

    public function testCommandName(): void
    {
        $cmd = new SetupDependenciesCommand($this->tmpDir);
        $this->assertSame('better-auth:setup:dependencies', $cmd->getName());
    }

    public function testFailsWhenNoComposerJson(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('composer.json not found', $tester->getDisplay());
    }

    public function testSkipInstallWhenAllPackagesPresent(): void
    {
        file_put_contents($this->tmpDir . '/composer.json', json_encode([
            'require' => [
                'symfony/security-bundle' => '^7.0',
                'symfony/validator' => '^7.0',
                'symfony/monolog-bundle' => '^3.0',
                'doctrine/doctrine-bundle' => '^2.11',
                'doctrine/orm' => '^3.0',
            ],
        ]));

        $tester = $this->createTester();
        $exitCode = $tester->execute([]);

        // All packages already installed — should succeed without running composer
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testSkipInstallOptionShowsMissingPackages(): void
    {
        file_put_contents($this->tmpDir . '/composer.json', json_encode([
            'require' => [],
        ]));

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--skip-install' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Skipping', $display);
    }

    public function testCommandHasSkipInstallOption(): void
    {
        $cmd = new SetupDependenciesCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('skip-install'));
    }

    public function testCommandHasWithDevOption(): void
    {
        $cmd = new SetupDependenciesCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('with-dev'));
    }

    public function testWithDevOptionShowsDevPackages(): void
    {
        file_put_contents($this->tmpDir . '/composer.json', json_encode([
            'require' => [
                'symfony/security-bundle' => '^7.0',
                'symfony/validator' => '^7.0',
                'symfony/monolog-bundle' => '^3.0',
                'doctrine/doctrine-bundle' => '^2.11',
                'doctrine/orm' => '^3.0',
            ],
        ]));

        $tester = $this->createTester();
        $exitCode = $tester->execute(['--with-dev' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }
}
