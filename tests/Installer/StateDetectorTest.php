<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Installer;

use BetterAuth\Symfony\Installer\StateDetector;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

class StateDetectorTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $filesystem;
    private StateDetector $detector;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/betterauth_test_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->filesystem = new Filesystem();
        $this->detector = new StateDetector($this->filesystem);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpDir);
    }

    public function testDetectCurrentStateWithNoFiles(): void
    {
        $state = $this->detector->detectCurrentState($this->tmpDir);

        $this->assertFalse($state['entities']['User']);
        $this->assertFalse($state['entities']['Session']);
        $this->assertFalse($state['entities']['RefreshToken']);
        $this->assertFalse($state['controller']);
        $this->assertFalse($state['config']);
        $this->assertFalse($state['bundle_registered']);
        $this->assertFalse($state['env_has_secret']);
        $this->assertFalse($state['migrations_dir']);
    }

    public function testDetectCurrentStateWithUserEntity(): void
    {
        mkdir($this->tmpDir . '/src/Entity', 0777, true);
        file_put_contents($this->tmpDir . '/src/Entity/User.php', '<?php class User {}');

        $state = $this->detector->detectCurrentState($this->tmpDir);

        $this->assertTrue($state['entities']['User']);
        $this->assertFalse($state['entities']['Session']);
    }

    public function testDetectCurrentStateWithAllEntities(): void
    {
        mkdir($this->tmpDir . '/src/Entity', 0777, true);
        $entities = ['User', 'Session', 'RefreshToken', 'AccountLink', 'DeviceInfo'];
        foreach ($entities as $entity) {
            file_put_contents($this->tmpDir . '/src/Entity/' . $entity . '.php', '<?php');
        }

        $state = $this->detector->detectCurrentState($this->tmpDir);

        foreach ($entities as $entity) {
            $this->assertTrue($state['entities'][$entity], "Entity $entity should be detected");
        }
    }

    public function testDetectCurrentStateWithController(): void
    {
        mkdir($this->tmpDir . '/src/Controller', 0777, true);
        file_put_contents($this->tmpDir . '/src/Controller/AuthController.php', '<?php');

        $state = $this->detector->detectCurrentState($this->tmpDir);

        $this->assertTrue($state['controller']);
    }

    public function testDetectCurrentStateWithConfig(): void
    {
        mkdir($this->tmpDir . '/config/packages', 0777, true);
        file_put_contents($this->tmpDir . '/config/packages/better_auth.yaml', 'better_auth: ~');

        $state = $this->detector->detectCurrentState($this->tmpDir);

        $this->assertTrue($state['config']);
    }

    public function testDetectCurrentStateWithMigrationsDir(): void
    {
        mkdir($this->tmpDir . '/migrations', 0777, true);

        $state = $this->detector->detectCurrentState($this->tmpDir);

        $this->assertTrue($state['migrations_dir']);
    }

    public function testIsBundleRegisteredWhenFileAbsent(): void
    {
        $result = $this->detector->isBundleRegistered($this->tmpDir);

        $this->assertFalse($result);
    }

    public function testIsBundleRegisteredWhenNotInFile(): void
    {
        mkdir($this->tmpDir . '/config', 0777, true);
        file_put_contents($this->tmpDir . '/config/bundles.php', '<?php return [];');

        $result = $this->detector->isBundleRegistered($this->tmpDir);

        $this->assertFalse($result);
    }

    public function testIsBundleRegisteredWhenPresent(): void
    {
        mkdir($this->tmpDir . '/config', 0777, true);
        file_put_contents(
            $this->tmpDir . '/config/bundles.php',
            '<?php return [BetterAuth\Symfony\BetterAuthBundle::class => ["all" => true]];'
        );

        $result = $this->detector->isBundleRegistered($this->tmpDir);

        $this->assertTrue($result);
    }

    public function testEnvHasSecretWhenFileAbsent(): void
    {
        $result = $this->detector->envHasSecret($this->tmpDir);

        $this->assertFalse($result);
    }

    public function testEnvHasSecretWhenMissing(): void
    {
        file_put_contents($this->tmpDir . '/.env', "APP_ENV=dev\n");

        $result = $this->detector->envHasSecret($this->tmpDir);

        $this->assertFalse($result);
    }

    public function testEnvHasSecretWhenPresent(): void
    {
        file_put_contents($this->tmpDir . '/.env', "BETTER_AUTH_SECRET=abc123\n");

        $result = $this->detector->envHasSecret($this->tmpDir);

        $this->assertTrue($result);
    }

    public function testDisplayCurrentStateWithNoSetup(): void
    {
        $state = [
            'entities' => ['User' => false, 'Session' => false],
            'controller' => false,
            'config' => false,
            'bundle_registered' => false,
            'env_has_secret' => false,
            'migrations_dir' => false,
        ];

        // Should not throw — just no output for empty state
        $io = $this->createMock(\Symfony\Component\Console\Style\SymfonyStyle::class);
        $io->expects($this->never())->method('section');

        $this->detector->displayCurrentState($io, $state);
    }

    public function testDisplayCurrentStateWithExistingSetup(): void
    {
        $state = [
            'entities' => ['User' => true, 'Session' => false],
            'controller' => true,
            'config' => false,
            'bundle_registered' => false,
            'env_has_secret' => false,
            'migrations_dir' => false,
        ];

        $io = $this->createMock(\Symfony\Component\Console\Style\SymfonyStyle::class);
        $io->expects($this->once())->method('section');
        $io->expects($this->once())->method('note');

        $this->detector->displayCurrentState($io, $state);
    }
}
