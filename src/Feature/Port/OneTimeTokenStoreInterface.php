<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Feature\Port;

interface OneTimeTokenStoreInterface
{
    public function store(string $purpose, string $tokenHash, string $userIdentifier, \DateTimeImmutable $expiresAt): void;

    /**
     * Atomically consume one row matching purpose and token hash only when it
     * is still unexpired and unconsumed at $now. A second consumption, an
     * expired token, or a mismatched purpose/hash MUST return null.
     */
    public function consume(string $purpose, string $tokenHash, \DateTimeImmutable $now): ?string;
}
