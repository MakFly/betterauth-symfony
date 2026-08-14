<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Token;

/**
 * Application-owned persistence boundary for refresh tokens.
 *
 * Implementations receive hashes only. `rotate()` MUST use one transaction or
 * compare-and-swap: it succeeds only where the current hash is both unspent and
 * unexpired, marks that record spent, and persists exactly one replacement for
 * the same user. A spent match is a replay, while no match is invalid.
 */
interface RefreshTokenStoreInterface
{
    public function store(RefreshTokenRecord $record): void;

    public function find(string $tokenHash): ?RefreshTokenRecord;

    public function rotate(
        string $tokenHash,
        string $replacementHash,
        \DateTimeImmutable $replacementExpiresAt,
        \DateTimeImmutable $now,
    ): RefreshStoreRotationResult;

    /** Revoke one refresh token hash supplied by the manager, never a raw token. */
    public function revoke(string $tokenHash): bool;

    public function revokeForUser(string $userIdentifier): void;
}
