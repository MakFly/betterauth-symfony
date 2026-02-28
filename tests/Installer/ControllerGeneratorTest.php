<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Installer;

use BetterAuth\Symfony\Installer\ControllerGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class ControllerGeneratorTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $filesystem;
    private ControllerGenerator $generator;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/betterauth_ctrl_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->filesystem = new Filesystem();
        $this->generator = new ControllerGenerator($this->filesystem);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpDir);
    }

    public function testDetectExistingControllersWhenNoneExist(): void
    {
        $found = $this->generator->detectExistingControllers($this->tmpDir);

        $this->assertEmpty($found);
    }

    public function testDetectExistingControllersInStandardLocation(): void
    {
        mkdir($this->tmpDir . '/src/Controller', 0777, true);
        file_put_contents($this->tmpDir . '/src/Controller/AuthController.php', '<?php');

        $found = $this->generator->detectExistingControllers($this->tmpDir);

        $this->assertArrayHasKey('AuthController', $found);
        $this->assertStringContainsString('src/Controller/AuthController.php', $found['AuthController']);
    }

    public function testDetectExistingControllersInLegacyLocation(): void
    {
        mkdir($this->tmpDir . '/src/Controller/Api', 0777, true);
        file_put_contents($this->tmpDir . '/src/Controller/Api/AuthController.php', '<?php');

        $found = $this->generator->detectExistingControllers($this->tmpDir);

        $this->assertArrayHasKey('AuthController', $found);
        $this->assertStringContainsString('src/Controller/Api/AuthController.php', $found['AuthController']);
    }

    public function testDetectExistingControllersPreferStandardOverLegacy(): void
    {
        mkdir($this->tmpDir . '/src/Controller', 0777, true);
        mkdir($this->tmpDir . '/src/Controller/Api', 0777, true);
        file_put_contents($this->tmpDir . '/src/Controller/AuthController.php', '<?php');
        file_put_contents($this->tmpDir . '/src/Controller/Api/AuthController.php', '<?php');

        $found = $this->generator->detectExistingControllers($this->tmpDir);

        $this->assertStringContainsString('src/Controller/AuthController.php', $found['AuthController']);
        $this->assertStringNotContainsString('Api/', $found['AuthController']);
    }

    public function testDetectExistingControllersMultiple(): void
    {
        mkdir($this->tmpDir . '/src/Controller', 0777, true);
        $controllers = ['AuthController', 'PasswordController', 'SessionsController'];
        foreach ($controllers as $ctrl) {
            file_put_contents($this->tmpDir . '/src/Controller/' . $ctrl . '.php', '<?php');
        }

        $found = $this->generator->detectExistingControllers($this->tmpDir);

        foreach ($controllers as $ctrl) {
            $this->assertArrayHasKey($ctrl, $found);
        }
    }

    public function testGenerateControllerSkipsWhenOptionSet(): void
    {
        $input = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input->method('getOption')->with('skip-controller')->willReturn(true);

        $io = $this->createMock(\Symfony\Component\Console\Style\SymfonyStyle::class);
        $io->expects($this->once())->method('section');
        $io->expects($this->never())->method('confirm');

        $state = [];

        $this->generator->generateController($io, $this->tmpDir, $state, $input);
    }

    public function testGenerateControllerCreatesDirectoriesAndAsksForOptionals(): void
    {
        $input = $this->createMock(\Symfony\Component\Console\Input\InputInterface::class);
        $input->method('getOption')->with('skip-controller')->willReturn(false);

        $io = $this->createMock(\Symfony\Component\Console\Style\SymfonyStyle::class);
        $io->method('section')->willReturnSelf();
        $io->method('writeln')->willReturnSelf();
        $io->method('confirm')->willReturn(false); // skip all optional, skip all overwrites

        $state = [];

        $this->generator->generateController($io, $this->tmpDir, $state, $input);

        // Directories should have been created even if no templates found
        $this->assertDirectoryExists($this->tmpDir . '/src/Controller');
        $this->assertDirectoryExists($this->tmpDir . '/src/Controller/Trait');
    }
}
