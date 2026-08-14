<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Token;

use BetterAuth\Symfony\Core\TokenService;
use BetterAuth\Symfony\Token\RefreshTokenManager;
use BetterAuth\Symfony\Token\RefreshTokenRecord;
use BetterAuth\Symfony\Token\RefreshTokenStoreInterface;
use BetterAuth\Symfony\Token\RefreshRotationStatus;
use BetterAuth\Symfony\Token\RefreshStoreRotationResult;
use PHPUnit\Framework\TestCase;

final class RefreshTokenManagerTest extends TestCase
{
    public function testConcurrentConsumersYieldOneAtomicRotationAndOneReplay(): void
    {
        $store = new InMemoryRefreshTokenStore();
        $manager = new RefreshTokenManager(new TokenService(str_repeat('a', 32)), $store, 60, 3600);

        $issued = $manager->issue('user-42');
        self::assertNotNull($issued->refreshToken);
        self::assertFalse(in_array($issued->refreshToken, array_keys($store->records), true));

        $rotated = $manager->rotate($issued->refreshToken);
        self::assertSame(RefreshRotationStatus::Rotated, $rotated->status);
        self::assertNotNull($rotated->tokens);
        self::assertNotSame($issued->refreshToken, $rotated->tokens->refreshToken);
        self::assertCount(2, $store->records);

        $replayed = $manager->rotate($issued->refreshToken);
        self::assertSame(RefreshRotationStatus::Replayed, $replayed->status);
        self::assertTrue($store->allRevokedFor('user-42'));
    }

    public function testItDistinguishesAnUnknownTokenAndSupportsTargetedRevocation(): void
    {
        $store = new InMemoryRefreshTokenStore();
        $manager = new RefreshTokenManager(new TokenService(str_repeat('a', 32)), $store, 60, 3600);

        self::assertSame(RefreshRotationStatus::Invalid, $manager->rotate('unknown')->status);
        $issued = $manager->issue('user-42');
        self::assertNotNull($issued->refreshToken);
        self::assertTrue($manager->revoke($issued->refreshToken));
        self::assertSame(RefreshRotationStatus::Replayed, $manager->rotate($issued->refreshToken)->status);
    }
}

final class InMemoryRefreshTokenStore implements RefreshTokenStoreInterface
{
    /** @var array<string, RefreshTokenRecord> */
    public array $records = [];

    public function store(RefreshTokenRecord $record): void
    {
        $this->records[$record->tokenHash] = $record;
    }

    public function find(string $tokenHash): ?RefreshTokenRecord
    {
        return $this->records[$tokenHash] ?? null;
    }

    public function rotate(string $tokenHash, string $replacementHash, \DateTimeImmutable $replacementExpiresAt, \DateTimeImmutable $now): RefreshStoreRotationResult
    {
        $current = $this->records[$tokenHash] ?? null;
        if ($current === null || $current->expiresAt <= $now) {
            return RefreshStoreRotationResult::invalid();
        }
        if ($current->revoked) {
            return RefreshStoreRotationResult::replayed($current);
        }

        $this->records[$tokenHash] = new RefreshTokenRecord(
            $current->tokenHash,
            $current->userIdentifier,
            $current->expiresAt,
            $replacementHash,
            true,
        );
        $this->records[$replacementHash] = new RefreshTokenRecord(
            $replacementHash,
            $current->userIdentifier,
            $replacementExpiresAt,
        );

        return RefreshStoreRotationResult::rotated($current);
    }

    public function revoke(string $tokenHash): bool
    {
        $record = $this->records[$tokenHash] ?? null;
        if ($record === null || $record->revoked) {
            return false;
        }
        $this->records[$tokenHash] = new RefreshTokenRecord($record->tokenHash, $record->userIdentifier, $record->expiresAt, $record->replacedByHash, true);

        return true;
    }

    public function revokeForUser(string $userIdentifier): void
    {
        foreach ($this->records as $hash => $record) {
            if ($record->userIdentifier === $userIdentifier) {
                $this->records[$hash] = new RefreshTokenRecord(
                    $record->tokenHash,
                    $record->userIdentifier,
                    $record->expiresAt,
                    $record->replacedByHash,
                    true,
                );
            }
        }
    }

    public function allRevokedFor(string $userIdentifier): bool
    {
        foreach ($this->records as $record) {
            if ($record->userIdentifier === $userIdentifier && !$record->revoked) {
                return false;
            }
        }

        return true;
    }
}
