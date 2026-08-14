<?php

declare(strict_types=1);

namespace Demo\Storage;

use BetterAuth\Symfony\Feature\Port\OneTimeTokenStoreInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineOneTimeTokenStore implements OneTimeTokenStoreInterface
{
    public function __construct(private Connection $connection)
    {
    }

    public function store(string $purpose, string $tokenHash, string $userIdentifier, \DateTimeImmutable $expiresAt): void
    {
        $this->connection->insert('demo_one_time_token', [
            'purpose' => $purpose,
            'token_hash' => $tokenHash,
            'user_identifier' => $userIdentifier,
            'expires_at' => $this->date($expiresAt),
            'consumed_at' => null,
        ]);
    }

    public function consume(string $purpose, string $tokenHash, \DateTimeImmutable $now): ?string
    {
        return $this->connection->transactional(function () use ($purpose, $tokenHash, $now): ?string {
            $user = $this->connection->fetchOne('SELECT user_identifier FROM demo_one_time_token WHERE purpose = ? AND token_hash = ?', [$purpose, $tokenHash]);
            if (!is_string($user)) {
                return null;
            }
            $consumed = $this->connection->executeStatement(
                'UPDATE demo_one_time_token SET consumed_at = ? WHERE purpose = ? AND token_hash = ? AND consumed_at IS NULL AND expires_at > ?',
                [$this->date($now), $purpose, $tokenHash, $this->date($now)],
            );

            return $consumed === 1 ? $user : null;
        });
    }

    private function date(\DateTimeImmutable $date): string
    {
        return $date->format('Y-m-d H:i:s.u');
    }
}
