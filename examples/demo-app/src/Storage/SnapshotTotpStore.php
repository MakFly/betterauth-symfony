<?php

declare(strict_types=1);

namespace Demo\Storage;

use BetterAuth\Symfony\Feature\Port\TotpStoreInterface;

final readonly class SnapshotTotpStore implements TotpStoreInterface
{
    public function __construct(private string $ciphertext)
    {
    }

    public function save(string $userIdentifier, string $ciphertext): void
    {
        throw new \LogicException('A TOTP snapshot is read-only.');
    }

    public function load(string $userIdentifier): string
    {
        return $this->ciphertext;
    }
}
