<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Token;

/**
 * Metadata persisted by an application refresh-token store.
 *
 * The record intentionally contains only a SHA-256 token hash. The raw refresh
 * token is returned to the caller once and must never be persisted.
 */
final readonly class RefreshTokenRecord
{
    public function __construct(
        public string $tokenHash,
        public string $userIdentifier,
        public \DateTimeImmutable $expiresAt,
        public ?string $replacedByHash = null,
        public bool $revoked = false,
    ) {
    }

    public function isUsable(\DateTimeImmutable $now): bool
    {
        return !$this->revoked && $this->expiresAt > $now;
    }
}
