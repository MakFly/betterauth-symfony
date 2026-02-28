<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Symfony\Command\PublishTemplatesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PublishTemplatesCommandTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/ba_publish_' . uniqid();
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
        return new CommandTester(new PublishTemplatesCommand($this->tmpDir));
    }

    public function testCommandName(): void
    {
        $cmd = new PublishTemplatesCommand($this->tmpDir);
        $this->assertSame('better-auth:publish-templates', $cmd->getName());
    }

    public function testFailsWhenBundleTemplatesDirMissing(): void
    {
        $tester = $this->createTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Bundle templates directory not found', $tester->getDisplay());
    }

    public function testPublishesTemplatesWhenSourceExists(): void
    {
        // Create fake bundle templates directory
        $bundleTemplatesDir = $this->tmpDir . '/vendor/betterauth/symfony-bundle/src/Resources/views/emails';
        mkdir($bundleTemplatesDir, 0777, true);

        $templates = ['magic_link.html.twig', 'email_verification.html.twig', 'password_reset.html.twig', 'two_factor_code.html.twig'];
        foreach ($templates as $tpl) {
            file_put_contents($bundleTemplatesDir . '/' . $tpl, '<html>{{ content }}</html>');
        }

        $tester = $this->createTester();
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);

        // Templates should be published
        $targetDir = $this->tmpDir . '/templates/emails/betterauth';
        $this->assertDirectoryExists($targetDir);
        $this->assertFileExists($targetDir . '/magic_link.html.twig');
    }

    public function testSkipsExistingTemplatesWithoutForce(): void
    {
        $bundleTemplatesDir = $this->tmpDir . '/vendor/betterauth/symfony-bundle/src/Resources/views/emails';
        mkdir($bundleTemplatesDir, 0777, true);

        $targetDir = $this->tmpDir . '/templates/emails/betterauth';
        mkdir($targetDir, 0777, true);

        // Pre-existing template
        file_put_contents($bundleTemplatesDir . '/magic_link.html.twig', '<html>new</html>');
        file_put_contents($targetDir . '/magic_link.html.twig', '<html>existing</html>');

        $tester = $this->createTester();
        $tester->execute([]);

        // Should have kept the existing file
        $content = file_get_contents($targetDir . '/magic_link.html.twig');
        $this->assertSame('<html>existing</html>', $content);
    }

    public function testForceOverwritesExistingTemplates(): void
    {
        $bundleTemplatesDir = $this->tmpDir . '/vendor/betterauth/symfony-bundle/src/Resources/views/emails';
        mkdir($bundleTemplatesDir, 0777, true);

        $targetDir = $this->tmpDir . '/templates/emails/betterauth';
        mkdir($targetDir, 0777, true);

        file_put_contents($bundleTemplatesDir . '/magic_link.html.twig', '<html>new</html>');
        file_put_contents($targetDir . '/magic_link.html.twig', '<html>existing</html>');

        $tester = $this->createTester();
        $tester->execute(['--force' => true]);

        $content = file_get_contents($targetDir . '/magic_link.html.twig');
        $this->assertSame('<html>new</html>', $content);
    }

    public function testCommandHasForceOption(): void
    {
        $cmd = new PublishTemplatesCommand($this->tmpDir);
        $this->assertTrue($cmd->getDefinition()->hasOption('force'));
    }
}
