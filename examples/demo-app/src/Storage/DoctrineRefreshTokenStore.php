<?php

declare(strict_types=1);

namespace Demo\Storage;

use BetterAuth\Symfony\Token\RefreshRotationStatus;
use BetterAuth\Symfony\Token\RefreshStoreRotationResult;
use BetterAuth\Symfony\Token\RefreshTokenRecord;
use BetterAuth\Symfony\Token\RefreshTokenStoreInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineRefreshTokenStore implements RefreshTokenStoreInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function store(RefreshTokenRecord $record): void
    {
        $this->connection->insert('demo_refresh_token', [
            'token_hash' => $record->tokenHash,
            'user_identifier' => $record->userIdentifier,
            'expires_at' => $this->date($record->expiresAt),
            'replaced_by_hash' => $record->replacedByHash,
            'revoked' => $record->revoked ? 1 : 0,
        ]);
    }

    public function find(string $tokenHash): ?RefreshTokenRecord
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM demo_refresh_token WHERE token_hash = ?', [$tokenHash]);

        return is_array($row) ? $this->record($row) : null;
    }

    public function rotate(string $tokenHash, string $replacementHash, \DateTimeImmutable $replacementExpiresAt, \DateTimeImmutable $now): RefreshStoreRotationResult
    {
        return $this->connection->transactional(function () use ($tokenHash, $replacementHash, $replacementExpiresAt, $now): RefreshStoreRotationResult {
            $row = $this->connection->fetchAssociative('SELECT * FROM demo_refresh_token WHERE token_hash = ?', [$tokenHash]);
            if (!is_array($row)) {
                return RefreshStoreRotationResult::invalid();
            }
            $record = $this->record($row);
            if ($record->replacedByHash !== null) {
                return RefreshStoreRotationResult::replayed($record);
            }
            if (!$record->isUsable($now)) {
                return RefreshStoreRotationResult::invalid();
            }

            $updated = $this->connection->executeStatement(
                'UPDATE demo_refresh_token SET replaced_by_hash = ? WHERE token_hash = ? AND replaced_by_hash IS NULL AND revoked = 0 AND expires_at > ?',
                [$replacementHash, $tokenHash, $this->date($now)],
            );
            if ($updated !== 1) {
                $after = $this->find($tokenHash);

                return $after !== null && $after->replacedByHash !== null
                    ? RefreshStoreRotationResult::replayed($after)
                    : RefreshStoreRotationResult::invalid();
            }
            $this->store(new RefreshTokenRecord($replacementHash, $record->userIdentifier, $replacementExpiresAt));

            return RefreshStoreRotationResult::rotated($record);
        });
    }

    public function revoke(string $tokenHash): bool
    {
        return $this->connection->executeStatement('UPDATE demo_refresh_token SET revoked = 1 WHERE token_hash = ? AND revoked = 0', [$tokenHash]) === 1;
    }

    public function revokeForUser(string $userIdentifier): void
    {
        $this->connection->executeStatement('UPDATE demo_refresh_token SET revoked = 1 WHERE user_identifier = ?', [$userIdentifier]);
    }

    /** @param array<string, mixed> $row */
    private function record(array $row): RefreshTokenRecord
    {
        $hash = $row['token_hash'] ?? null;
        $user = $row['user_identifier'] ?? null;
        $expiresAt = $row['expires_at'] ?? null;
        if (!is_string($hash) || !is_string($user) || !is_string($expiresAt)) {
            throw new \LogicException('Malformed refresh token row.');
        }
        $revoked = $row['revoked'] ?? 0;
        if (!is_int($revoked) && !is_string($revoked) && !is_bool($revoked)) {
            throw new \LogicException('Malformed refresh token revocation state.');
        }

        return new RefreshTokenRecord($hash, $user, new \DateTimeImmutable($expiresAt), is_string($row['replaced_by_hash'] ?? null) ? $row['replaced_by_hash'] : null, $revoked === true || $revoked === 1 || $revoked === '1');
    }

    private function date(\DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }
}
