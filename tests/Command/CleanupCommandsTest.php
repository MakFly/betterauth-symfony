<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Command;

use BetterAuth\Core\Interfaces\RefreshTokenRepositoryInterface;
use BetterAuth\Core\SessionService;
use BetterAuth\Symfony\Command\CleanupSessionsCommand;
use BetterAuth\Symfony\Command\CleanupTokensCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupCommandsTest extends TestCase
{
    // ─── CleanupSessionsCommand ──────────────────────────────────────────────

    public function testCleanupSessionsCommandName(): void
    {
        $cmd = new CleanupSessionsCommand();
        $this->assertSame('better-auth:cleanup:sessions', $cmd->getName());
    }

    public function testCleanupSessionsFailsWhenServiceNull(): void
    {
        $tester = new CommandTester(new CleanupSessionsCommand(null));
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not available', $tester->getDisplay());
    }

    public function testCleanupSessionsSucceedsWithService(): void
    {
        $sessionService = $this->createMock(SessionService::class);
        $sessionService->method('cleanupExpired')->willReturn(5);

        $tester = new CommandTester(new CleanupSessionsCommand($sessionService));
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('5', $tester->getDisplay());
    }

    public function testCleanupSessionsDryRun(): void
    {
        $sessionService = $this->createMock(SessionService::class);
        $sessionService->method('cleanupExpired')->willReturn(3);

        $tester = new CommandTester(new CleanupSessionsCommand($sessionService));
        $exitCode = $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('3', $display);
        $this->assertStringContainsString('dry', strtolower($display));
    }

    public function testCleanupSessionsHandlesException(): void
    {
        $sessionService = $this->createMock(SessionService::class);
        $sessionService->method('cleanupExpired')->willThrowException(new \RuntimeException('DB error'));

        $tester = new CommandTester(new CleanupSessionsCommand($sessionService));
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('DB error', $tester->getDisplay());
    }

    public function testCleanupSessionsZeroDeleted(): void
    {
        $sessionService = $this->createMock(SessionService::class);
        $sessionService->method('cleanupExpired')->willReturn(0);

        $tester = new CommandTester(new CleanupSessionsCommand($sessionService));
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    // ─── CleanupTokensCommand ────────────────────────────────────────────────

    public function testCleanupTokensCommandName(): void
    {
        $cmd = new CleanupTokensCommand();
        $this->assertSame('better-auth:cleanup:tokens', $cmd->getName());
    }

    public function testCleanupTokensFailsWhenRepositoryNull(): void
    {
        $tester = new CommandTester(new CleanupTokensCommand(null));
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('not available', $tester->getDisplay());
    }

    public function testCleanupTokensSucceedsWithRepository(): void
    {
        $repo = $this->createMock(RefreshTokenRepositoryInterface::class);
        $repo->method('deleteExpired')->willReturn(7);

        $tester = new CommandTester(new CleanupTokensCommand($repo));
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('7', $tester->getDisplay());
    }

    public function testCleanupTokensDryRun(): void
    {
        $repo = $this->createMock(RefreshTokenRepositoryInterface::class);
        $repo->method('deleteExpired')->willReturn(2);

        $tester = new CommandTester(new CleanupTokensCommand($repo));
        $exitCode = $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('2', $display);
        $this->assertStringContainsString('dry', strtolower($display));
    }

    public function testCleanupTokensHandlesException(): void
    {
        $repo = $this->createMock(RefreshTokenRepositoryInterface::class);
        $repo->method('deleteExpired')->willThrowException(new \RuntimeException('Connection lost'));

        $tester = new CommandTester(new CleanupTokensCommand($repo));
        $exitCode = $tester->execute([]);

        $this->assertSame(Command::FAILURE, $exitCode);
        $this->assertStringContainsString('Connection lost', $tester->getDisplay());
    }

    public function testCleanupTokensHasDryRunOption(): void
    {
        $cmd = new CleanupTokensCommand();
        $this->assertTrue($cmd->getDefinition()->hasOption('dry-run'));
    }

    public function testCleanupSessionsHasDryRunOption(): void
    {
        $cmd = new CleanupSessionsCommand();
        $this->assertTrue($cmd->getDefinition()->hasOption('dry-run'));
    }
}
