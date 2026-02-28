<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Installer;

use BetterAuth\Symfony\Installer\MigrationHandler;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Style\SymfonyStyle;

class MigrationHandlerTest extends TestCase
{
    private string $tmpDir;
    private MigrationHandler $handler;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/betterauth_mig_' . uniqid();
        mkdir($this->tmpDir, 0777, true);
        $this->handler = new MigrationHandler();
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            rmdir($this->tmpDir);
        }
    }

    private function createMockIo(bool $confirmResult = true): SymfonyStyle
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('section')->willReturnSelf();
        $io->method('writeln')->willReturnSelf();
        $io->method('confirm')->willReturn($confirmResult);
        $io->method('info')->willReturnSelf();

        return $io;
    }

    public function testHandleMigrationsSkipsWhenUserDeclines(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('section')->willReturnSelf();
        $io->method('confirm')->willReturn(false); // user says no
        $io->expects($this->once())->method('info');

        $this->handler->handleMigrations($io, $this->tmpDir);
    }

    public function testHandleMigrationsReportsConsoleNotFound(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->method('section')->willReturnSelf();
        $io->method('confirm')->willReturn(true); // user says yes
        $io->expects($this->once())->method('error'); // console not found

        $this->handler->handleMigrations($io, $this->tmpDir);
    }

    public function testDisplayFinalSummaryWithNoProviders(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('success');
        $io->method('writeln')->willReturnSelf();

        $this->handler->displayFinalSummary($io, 'uuid', ['User', 'Session'], 'api', [], 'My App');
    }

    public function testDisplayFinalSummaryWithOauthProviders(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('success');
        $io->method('writeln')->willReturnSelf();
        $io->method('newLine')->willReturnSelf();

        $this->handler->displayFinalSummary($io, 'uuid', ['User'], 'api', ['google', 'github'], 'My App');
    }

    public function testDisplayFinalSummaryWithIntStrategy(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('success');
        $io->method('writeln')->willReturnSelf();

        $this->handler->displayFinalSummary($io, 'int', [], 'session', [], 'Test App');
    }

    public function testDisplayFinalSummaryWithEmptyEntities(): void
    {
        $io = $this->createMock(SymfonyStyle::class);
        $io->expects($this->once())->method('success');
        $io->method('writeln')->willReturnSelf();

        $this->handler->displayFinalSummary($io, 'uuid', [], 'api', [], 'My App');
    }
}
