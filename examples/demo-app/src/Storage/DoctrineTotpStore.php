<?php

declare(strict_types=1);

namespace Demo\Storage;

use BetterAuth\Symfony\Feature\Port\TotpStoreInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineTotpStore implements TotpStoreInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function save(string $userIdentifier, string $ciphertext): void
    {
        $this->connection->executeStatement('INSERT INTO demo_totp_seed (user_identifier, ciphertext) VALUES (?, ?) ON CONFLICT(user_identifier) DO UPDATE SET ciphertext = excluded.ciphertext', [$userIdentifier, $ciphertext]);
    }

    public function load(string $userIdentifier): ?string
    {
        $ciphertext = $this->connection->fetchOne('SELECT ciphertext FROM demo_totp_seed WHERE user_identifier = ?', [$userIdentifier]);

        return is_string($ciphertext) ? $ciphertext : null;
    }

    public function savePending(string $userIdentifier, string $ciphertext, \DateTimeImmutable $expiresAt): void
    {
        $this->connection->executeStatement(
            "INSERT INTO demo_totp_seed (user_identifier, ciphertext, pending_ciphertext, pending_expires_at) VALUES (?, '', ?, ?) ON CONFLICT(user_identifier) DO UPDATE SET pending_ciphertext = excluded.pending_ciphertext, pending_expires_at = excluded.pending_expires_at",
            [$userIdentifier, $ciphertext, $expiresAt->format('Y-m-d H:i:s.u')],
        );
    }

    public function loadPending(string $userIdentifier, \DateTimeImmutable $now): ?string
    {
        return $this->snapshotPending($userIdentifier, $now)?->ciphertext;
    }

    public function snapshotPending(string $userIdentifier, \DateTimeImmutable $now): ?PendingTotpSnapshot
    {
        $ciphertext = $this->connection->fetchOne('SELECT pending_ciphertext FROM demo_totp_seed WHERE user_identifier = ? AND pending_expires_at > ?', [$userIdentifier, $now->format('Y-m-d H:i:s.u')]);

        return is_string($ciphertext) ? new PendingTotpSnapshot($ciphertext) : null;
    }

    public function activatePending(string $userIdentifier, string $expectedCiphertext, \DateTimeImmutable $now): bool
    {
        return $this->connection->executeStatement(
            'UPDATE demo_totp_seed SET ciphertext = pending_ciphertext, pending_ciphertext = NULL, pending_expires_at = NULL WHERE user_identifier = ? AND pending_ciphertext = ? AND pending_expires_at > ?',
            [$userIdentifier, $expectedCiphertext, $now->format('Y-m-d H:i:s.u')],
        ) === 1;
    }
}
