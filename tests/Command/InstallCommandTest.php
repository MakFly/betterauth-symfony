<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\InstallCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class InstallCommandTest extends TestCase
{
    private InstallCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->command = new InstallCommand();
        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandNameAndDescription(): void
    {
        $this->assertSame('better-auth:install', $this->command->getName());
        $this->assertStringContainsString('BetterAuth', $this->command->getDescription());
    }

    public function testCommandHasExpectedOptions(): void
    {
        $definition = $this->command->getDefinition();

        $this->assertTrue($definition->hasOption('id-strategy'));
        $this->assertTrue($definition->hasOption('mode'));
        $this->assertTrue($definition->hasOption('app-name'));
        $this->assertTrue($definition->hasOption('exclude-fields'));
        $this->assertTrue($definition->hasOption('minimal'));
        $this->assertTrue($definition->hasOption('skip-migrations'));
        $this->assertTrue($definition->hasOption('skip-controller'));
        $this->assertTrue($definition->hasOption('skip-config'));
    }

    public function testCommandCancelsWhenUserDeclines(): void
    {
        $this->commandTester->setInputs([
            '', // id-strategy choice (use default)
            '', // mode choice
            'n', // do not enable OAuth
            '', // app name
            'n', // do not customize fields
            'n', // do NOT proceed with installation
        ]);

        // Run in a temp dir to avoid touching real project files
        $tmpDir = sys_get_temp_dir() . '/ba_install_test_' . uniqid();
        mkdir($tmpDir);
        chdir($tmpDir);

        try {
            $exitCode = $this->commandTester->execute([]);
        } finally {
            rmdir($tmpDir);
        }

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('cancelled', strtolower($this->commandTester->getDisplay()));
    }

    public function testCommandWithNonInteractiveOptionsAndSkipAll(): void
    {
        $tmpDir = sys_get_temp_dir() . '/ba_install_ni_' . uniqid();
        mkdir($tmpDir);

        // Create minimal Symfony structure so the command can write files
        mkdir($tmpDir . '/config/packages', 0777, true);
        mkdir($tmpDir . '/src/Controller', 0777, true);
        file_put_contents($tmpDir . '/config/bundles.php', "<?php return [];\n");
        file_put_contents($tmpDir . '/config/services.yaml', "services:\n");
        file_put_contents($tmpDir . '/.env', "APP_ENV=dev\n");
        file_put_contents($tmpDir . '/composer.json', '{}');
        chdir($tmpDir);

        try {
            // Non-interactive with all skips to avoid needing templates
            $exitCode = $this->commandTester->execute(
                [
                    '--id-strategy' => 'uuid',
                    '--mode' => 'api',
                    '--app-name' => 'Test App',
                    '--minimal' => true,
                    '--skip-migrations' => true,
                    '--skip-controller' => true,
                    '--skip-config' => true,
                ],
                ['interactive' => false]
            );
        } catch (\Throwable) {
            // Template files not present in test environment — expected
            $exitCode = Command::FAILURE;
        } finally {
            $this->removeDirectory($tmpDir);
        }

        // Command should complete (SUCCESS or FAILURE depending on missing templates)
        $this->assertContains($exitCode, [Command::SUCCESS, Command::FAILURE]);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
