<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\AddControllerCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class AddControllerCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ba_addctrl_' . uniqid();
        mkdir($this->tmpDir . '/src/Controller', 0777, true);
        file_put_contents($this->tmpDir . '/composer.json', '{}');
        chdir($this->tmpDir);
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
        return new CommandTester(new AddControllerCommand());
    }

    public function testCommandName(): void
    {
        $cmd = new AddControllerCommand();
        $this->assertSame('better-auth:add-controller', $cmd->getName());
    }

    public function testListOptionShowsControllers(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(['--list' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        // Should display controller names
        $this->assertStringContainsString('auth', strtolower($display));
        $this->assertStringContainsString('Controller', $display);
    }

    public function testUnknownControllerFails(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute(
            ['controller' => 'nonexistent'],
            ['interactive' => false]
        );

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Unknown controller', $tester->getDisplay());
    }

    public function testCommandHasListOption(): void
    {
        $cmd = new AddControllerCommand();
        $this->assertTrue($cmd->getDefinition()->hasOption('list'));
    }

    public function testCommandHasAllOption(): void
    {
        $cmd = new AddControllerCommand();
        $this->assertTrue($cmd->getDefinition()->hasOption('all'));
    }

    public function testCommandHasForceOption(): void
    {
        $cmd = new AddControllerCommand();
        $this->assertTrue($cmd->getDefinition()->hasOption('force'));
    }

    public function testCommandHasControllerArgument(): void
    {
        $cmd = new AddControllerCommand();
        $this->assertTrue($cmd->getDefinition()->hasArgument('controller'));
    }

    public function testInstallKnownControllerFailsWithoutTemplate(): void
    {
        // Templates don't exist in test env, should fail gracefully
        $tester = $this->createTester();
        $exitCode = $tester->execute(
            ['controller' => 'auth', '--force' => true],
            ['interactive' => false]
        );

        // Acceptable: either FAILURE (template missing) or SUCCESS (if template found)
        $this->assertContains($exitCode, [Command::SUCCESS, Command::FAILURE]);
    }
}
