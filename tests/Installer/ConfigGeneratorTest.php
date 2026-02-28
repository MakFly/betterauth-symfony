<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Installer;

use BetterAuth\Symfony\Installer\ConfigGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;

class ConfigGeneratorTest extends TestCase
{
    private string $tmpDir;
    private Filesystem $filesystem;
    private ConfigGenerator $generator;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/betterauth_cfg_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->filesystem = new Filesystem();
        $this->generator = new ConfigGenerator($this->filesystem);
    }

    protected function tearDown(): void
    {
        $this->filesystem->remove($this->tmpDir);
    }

    private function createMockIo(bool $confirmResult = true): SymfonyStyle
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('section')->willReturnSelf();
        $io->method('writeln')->willReturnSelf();
        $io->method('confirm')->willReturn($confirmResult);

        return $io;
    }

    public function testGenerateOAuthConfigWithNoProviders(): void
    {
        $result = $this->generator->generateOAuthConfig([]);

        $this->assertStringContainsString('No OAuth providers enabled', $result);
    }

    public function testGenerateOAuthConfigWithGoogle(): void
    {
        $result = $this->generator->generateOAuthConfig(['google']);

        $this->assertStringContainsString('google:', $result);
        $this->assertStringContainsString('enabled: true', $result);
        $this->assertStringContainsString('GOOGLE_CLIENT_ID', $result);
        $this->assertStringContainsString('GOOGLE_CLIENT_SECRET', $result);
        $this->assertStringContainsString('/auth/oauth/google/callback', $result);
    }

    public function testGenerateOAuthConfigWithMultipleProviders(): void
    {
        $result = $this->generator->generateOAuthConfig(['google', 'github']);

        $this->assertStringContainsString('google:', $result);
        $this->assertStringContainsString('github:', $result);
        $this->assertStringContainsString('GOOGLE_CLIENT_ID', $result);
        $this->assertStringContainsString('GITHUB_CLIENT_ID', $result);
    }

    public function testRegisterBundleSkipsWhenNoBundlesFile(): void
    {
        $io = $this->createMockIo();
        $io->expects($this->once())->method('writeln');

        $this->generator->registerBundle($io, $this->tmpDir);

        // No bundles.php => should write "skipping" message only
    }

    public function testRegisterBundleSkipsWhenAlreadyRegistered(): void
    {
        mkdir($this->tmpDir . '/config', 0777, true);
        file_put_contents(
            $this->tmpDir . '/config/bundles.php',
            "<?php return [\n    BetterAuth\\Symfony\\BetterAuthBundle::class => ['all' => true],\n];"
        );

        $io = $this->createMockIo();

        $this->generator->registerBundle($io, $this->tmpDir);

        // File should not change
        $content = file_get_contents($this->tmpDir . '/config/bundles.php');
        $this->assertStringContainsString('BetterAuthBundle', $content);
    }

    public function testRegisterBundleAddsBundleToFile(): void
    {
        mkdir($this->tmpDir . '/config', 0777, true);
        file_put_contents($this->tmpDir . '/config/bundles.php', "<?php return [\n];");

        $io = $this->createMockIo();

        $this->generator->registerBundle($io, $this->tmpDir);

        $content = file_get_contents($this->tmpDir . '/config/bundles.php');
        $this->assertStringContainsString('BetterAuthBundle', $content);
    }

    public function testConfigureServicesSkipsWhenNoServicesFile(): void
    {
        $io = $this->createMockIo();
        $io->expects($this->once())->method('writeln');

        $this->generator->configureServices($io, $this->tmpDir, 'uuid');
    }

    public function testConfigureServicesSkipsWhenAlreadyConfigured(): void
    {
        mkdir($this->tmpDir . '/config', 0777, true);
        file_put_contents(
            $this->tmpDir . '/config/services.yaml',
            "services:\n    DoctrineUserRepository:\n"
        );

        $io = $this->createMockIo();

        $this->generator->configureServices($io, $this->tmpDir, 'uuid');

        // Content should remain unchanged
        $content = file_get_contents($this->tmpDir . '/config/services.yaml');
        $this->assertStringContainsString('DoctrineUserRepository', $content);
    }

    public function testConfigureServicesAddsUuidRepositories(): void
    {
        mkdir($this->tmpDir . '/config', 0777, true);
        file_put_contents($this->tmpDir . '/config/services.yaml', "services:\n    _defaults:\n        autowire: true\n");

        $io = $this->createMockIo();

        $this->generator->configureServices($io, $this->tmpDir, 'uuid');

        $content = file_get_contents($this->tmpDir . '/config/services.yaml');
        $this->assertStringContainsString('DoctrineUserRepository', $content);
        $this->assertStringContainsString('App\Entity\User', $content);
    }

    public function testConfigureServicesSkipsForIntStrategy(): void
    {
        mkdir($this->tmpDir . '/config', 0777, true);
        file_put_contents($this->tmpDir . '/config/services.yaml', "services:\n");

        $io = $this->createMockIo();

        $this->generator->configureServices($io, $this->tmpDir, 'int');

        $content = file_get_contents($this->tmpDir . '/config/services.yaml');
        $this->assertStringNotContainsString('DoctrineUserRepository', $content);
    }

    public function testUpdateEnvFileSkipsWhenNoFile(): void
    {
        $io = $this->createMockIo();
        $io->expects($this->once())->method('writeln');

        $this->generator->updateEnvFile($io, $this->tmpDir);
    }

    public function testUpdateEnvFileAddsSecretWhenMissing(): void
    {
        file_put_contents($this->tmpDir . '/.env', "APP_ENV=dev\n");

        $io = $this->createMockIo();

        $this->generator->updateEnvFile($io, $this->tmpDir, [], 'My App');

        $content = file_get_contents($this->tmpDir . '/.env');
        $this->assertStringContainsString('BETTER_AUTH_SECRET=', $content);
        $this->assertStringContainsString('APP_URL=', $content);
        $this->assertStringContainsString('APP_NAME=', $content);
    }

    public function testUpdateEnvFileSkipsSecretWhenAlreadyPresent(): void
    {
        file_put_contents($this->tmpDir . '/.env', "BETTER_AUTH_SECRET=existingsecret\n");

        $io = $this->createMockIo();

        $this->generator->updateEnvFile($io, $this->tmpDir, [], 'My App');

        $content = file_get_contents($this->tmpDir . '/.env');
        // Should only have one occurrence
        $this->assertSame(1, substr_count($content, 'BETTER_AUTH_SECRET='));
    }

    public function testUpdateEnvFileAddsOauthProviders(): void
    {
        file_put_contents($this->tmpDir . '/.env', "APP_ENV=dev\n");

        $io = $this->createMockIo();

        $this->generator->updateEnvFile($io, $this->tmpDir, ['google', 'github'], 'My App');

        $content = file_get_contents($this->tmpDir . '/.env');
        $this->assertStringContainsString('GOOGLE_CLIENT_ID=', $content);
        $this->assertStringContainsString('GOOGLE_CLIENT_SECRET=', $content);
        $this->assertStringContainsString('GITHUB_CLIENT_ID=', $content);
        $this->assertStringContainsString('GITHUB_CLIENT_SECRET=', $content);
    }

    public function testUpdateEnvFileEscapesAppNameWithSpaces(): void
    {
        file_put_contents($this->tmpDir . '/.env', "APP_ENV=dev\n");

        $io = $this->createMockIo();

        $this->generator->updateEnvFile($io, $this->tmpDir, [], 'My Awesome App');

        $content = file_get_contents($this->tmpDir . '/.env');
        $this->assertStringContainsString('APP_NAME="My Awesome App"', $content);
    }
}
